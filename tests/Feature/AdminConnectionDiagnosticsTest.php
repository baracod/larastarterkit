<?php

namespace Tests\Feature;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Modules\Admin\Services\ConnectionDiagnosticsService;
use Modules\Auth\Models\User;
use RuntimeException;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\AbstractStream;
use Tests\TestCase;

class AdminConnectionDiagnosticsTest extends TestCase
{
    public function test_scheduler_requires_recent_heartbeat_in_shared_cache(): void
    {
        config(['cache.default' => 'redis']);
        \Illuminate\Support\Facades\Cache::shouldReceive('get')->with('starter:scheduler:heartbeat')->andReturn(null, now()->subMinutes(4)->timestamp, now()->timestamp);
        $service = app(ConnectionDiagnosticsService::class);
        $this->assertSame('scheduler_inactive', $service->check('scheduler')['code']);
        $this->assertSame('scheduler_inactive', $service->check('scheduler')['code']);
        $this->assertSame('scheduler_ok', $service->check('scheduler')['code']);
    }

    public function test_scheduler_rejects_container_local_cache(): void
    {
        config(['cache.default' => 'array']);
        $this->assertSame('scheduler_shared_cache_required', app(ConnectionDiagnosticsService::class)->check('scheduler')['code']);
    }

    public function test_notification_channels_are_private_to_the_recipient(): void
    {
        $user = User::factory()->create();
        $broadcaster = Broadcast::connection();
        require base_path('routes/channels.php');
        $callback = $broadcaster->getChannels()['user.{id}'];
        $this->assertTrue($callback($user, (string) $user->id));
        $this->assertFalse($callback($user, (string) ($user->id + 1)));
    }

    public function test_browser_configuration_exposes_only_public_pusher_values(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'public-pusher-key',
            'broadcasting.connections.pusher.secret' => 'never-expose-pusher-secret',
            'broadcasting.connections.pusher.options.cluster' => 'eu',
        ]);
        $this->withoutVite();
        $this->get('/')->assertOk()->assertSee('public-pusher-key')->assertDontSee('never-expose-pusher-secret');
    }

    public function test_scheduler_heartbeat_is_scheduled_every_minute(): void
    {
        $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->first(fn ($event) => $event->description === 'starter-scheduler-heartbeat');
        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $event->run(app());
        $this->assertSame(now()->timestamp, \Illuminate\Support\Facades\Cache::get('starter:scheduler:heartbeat'));
    }

    public function test_broadcast_auth_only_signs_the_current_users_channel(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'test-key',
            'broadcasting.connections.pusher.secret' => 'test-secret',
            'broadcasting.connections.pusher.app_id' => '123',
        ]);
        Broadcast::purge('pusher');
        require base_path('routes/channels.php');
        $this->postJson('/api/broadcasting/auth', ['socket_id' => '1.1', 'channel_name' => 'private-user.1'])->assertUnauthorized();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->postJson('/api/broadcasting/auth', ['socket_id' => '1.1', 'channel_name' => 'private-user.'.$user->id])
            ->assertOk()->assertJsonStructure(['auth'])->assertDontSee('test-secret');
        $this->postJson('/api/broadcasting/auth', ['socket_id' => '1.1', 'channel_name' => 'private-user.'.($user->id + 1)])
            ->assertForbidden();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config([
        ]);
    }

    public function test_only_administrators_can_view_and_run_diagnostics(): void
    {
        $this->getJson('/api/v1/admin/connections')->assertUnauthorized();
        $this->postJson('/api/v1/admin/connections/check', ['service' => 'database'])->assertUnauthorized();
        $this->postJson('/api/v1/admin/connections/check', ['service' => 'mail'])->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/connections')->assertForbidden();
        $this->postJson('/api/v1/admin/connections/check', ['service' => 'database'])->assertForbidden();
        $this->postJson('/api/v1/admin/connections/check', ['service' => 'mail'])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_catalog_never_exposes_connection_credentials_or_endpoints(): void
    {
        $this->actingAsAdministrator();
        config([
            'filesystems.disks.s3.key' => 'storage-secret',
            'filesystems.disks.s3.endpoint' => 'https://private-endpoint.test',
            'mail.default' => 'smtp',
            'mail.mailers.smtp.url' => 'smtp://private-mail-user:private-mail-password@smtp-private.test:587',
        ]);
        $response = $this->getJson('/api/v1/admin/connections')->assertOk()
            ->assertJsonPath('database.connection', config('database.default'))
            ->assertJsonPath('mail', ['mailer' => 'smtp', 'transport' => 'smtp']);
        foreach (['secret-not-for-the-browser', 'storage-secret', 'private-endpoint.test', 'http://notary.test', 'private-mail-user', 'private-mail-password', 'smtp-private.test'] as $secret) {
            $response->assertDontSee($secret);
        }
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        Http::assertNothingSent();
    }

    public function test_database_check_executes_successfully(): void
    {
        $this->actingAsAdministrator();
        $this->postJson('/api/v1/admin/connections/check', ['service' => 'database'])
            ->assertOk()->assertJsonPath('status', 'ok')->assertJsonPath('code', 'database_ok')
            ->assertJsonStructure(['durationMs', 'checkedAt']);
    }

    public function test_database_failure_returns_a_safe_error(): void
    {
        $connection = config('database.default');
        try {
            config(['database.default' => 'secret-invalid-connection']);
            $result = app(ConnectionDiagnosticsService::class)->check('database');
            $this->assertSame('database_unavailable', $result['code']);
            $this->assertStringNotContainsString('secret-invalid-connection', json_encode($result));
        } finally {
            config(['database.default' => $connection]);
        }
    }

    public function test_smtp_check_uses_mail_url_credentials_and_closes_without_sending_mail(): void
    {
        $this->actingAsAdministrator();
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.url' => 'smtp://diagnostic-user:diagnostic-password@smtp.test:587?require_tls=true&timeout=90',
        ]);
        $stream = Mockery::mock(AbstractStream::class);
        $stream->shouldReceive('terminate')->once();
        $transport = Mockery::mock(EsmtpTransport::class);
        $transport->shouldReceive('start')->once();
        $transport->shouldReceive('executeCommand')->once()->with("NOOP\r\n", [250])->andReturn("250 OK\r\n");
        $transport->shouldReceive('stop')->once();
        $transport->shouldReceive('getStream')->once()->andReturn($stream);
        $transport->shouldNotReceive('send');
        Mail::shouldReceive('createSymfonyTransport')->once()->with(Mockery::on(fn ($config) => $config['host'] === 'smtp.test' && $config['port'] === 587
            && $config['username'] === 'diagnostic-user' && $config['password'] === 'diagnostic-password'
            && $config['require_tls'] === true && $config['timeout'] === 5
        ))->andReturn($transport);

        $this->postJson('/api/v1/admin/connections/check', ['service' => 'mail', 'host' => 'untrusted.test'])
            ->assertOk()->assertJsonPath('status', 'ok')->assertJsonPath('code', 'mail_ok');
    }

    public function test_smtp_authentication_failure_closes_the_socket_and_hides_credentials(): void
    {
        $this->actingAsAdministrator();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.url' => null]);
        $stream = Mockery::mock(AbstractStream::class);
        $stream->shouldReceive('terminate')->once();
        $transport = Mockery::mock(EsmtpTransport::class);
        $transport->shouldReceive('start')->once()->andThrow(new RuntimeException('535 private-smtp-password'));
        $transport->shouldReceive('stop')->once();
        $transport->shouldReceive('getStream')->once()->andReturn($stream);
        $transport->shouldNotReceive('send');
        $transport->shouldNotReceive('executeCommand');
        Mail::shouldReceive('createSymfonyTransport')->once()->andReturn($transport);

        $this->postJson('/api/v1/admin/connections/check', ['service' => 'mail'])
            ->assertOk()->assertJsonPath('status', 'error')->assertJsonPath('code', 'mail_unavailable')
            ->assertDontSee('private-smtp-password');
    }

    public function test_local_and_unsupported_mail_transports_are_not_reported_as_connected(): void
    {
        $this->actingAsAdministrator();
        Mail::shouldReceive('createSymfonyTransport')->never();
        foreach (['log', 'array', 'sendmail', 'ses', 'postmark', 'resend', 'failover', 'roundrobin'] as $driver) {
            config(['mail.default' => $driver]);
            $this->postJson('/api/v1/admin/connections/check', ['service' => 'mail'])
                ->assertOk()->assertJsonPath('status', 'not_configured')
                ->assertJsonPath('code', in_array($driver, ['log', 'array'], true) ? 'mail_local_only' : 'mail_unsupported');
        }
    }

    public function test_missing_smtp_configuration_does_not_attempt_a_connection(): void
    {
        $this->actingAsAdministrator();
        Mail::shouldReceive('createSymfonyTransport')->never();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.url' => null, 'mail.mailers.smtp.host' => '']);
        $this->postJson('/api/v1/admin/connections/check', ['service' => 'mail'])
            ->assertOk()->assertJsonPath('code', 'mail_configuration_missing');
        config(['mail.default' => 'missing-mailer']);
        $this->postJson('/api/v1/admin/connections/check', ['service' => 'mail'])
            ->assertOk()->assertJsonPath('code', 'mail_configuration_missing');
    }

    public function test_malformed_mail_url_does_not_break_other_service_diagnostics(): void
    {
        $this->actingAsAdministrator();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.url' => 'smtp://private-user:private-password@smtp.test:99999']);
        $this->getJson('/api/v1/admin/connections')->assertOk()->assertJsonPath('mail.transport', null)
            ->assertDontSee('private-password');
        $this->postJson('/api/v1/admin/connections/check', ['service' => 'mail'])
            ->assertOk()->assertJsonPath('status', 'error')->assertJsonPath('code', 'mail_unavailable')
            ->assertDontSee('private-password');
    }

    public function test_push_without_an_external_provider_is_not_reported_as_connected(): void
    {
        $this->actingAsAdministrator();
        Broadcast::shouldReceive('pusher')->never();
        foreach (['null', 'log', 'pusher'] as $driver) {
            config(['broadcasting.default' => $driver, "broadcasting.connections.{$driver}" => ['driver' => $driver]]);
            $this->postJson('/api/v1/admin/connections/check', ['service' => 'push'])
                ->assertOk()->assertJsonPath('status', 'not_configured')->assertJsonPath('code', 'push_configuration_missing');
        }
    }

    public function test_push_uses_a_read_only_authenticated_api_and_hides_channel_data(): void
    {
        $this->actingAsAdministrator();
        config(['broadcasting.default' => 'pusher', 'broadcasting.connections.pusher' => [
            'driver' => 'pusher', 'key' => 'push-key', 'secret' => 'push-secret', 'app_id' => 'private-app',
        ]]);
        $client = Mockery::mock();
        $client->shouldReceive('getChannels')->once()->withNoArgs()->andReturn((object) ['channels' => ['private-user-123' => []]]);
        $client->shouldNotReceive('trigger');
        Broadcast::shouldReceive('pusher')->once()->with(Mockery::on(fn ($config) => $config['secret'] === 'push-secret'
            && $config['client_options']['timeout'] === 5 && $config['client_options']['connect_timeout'] === 3))->andReturn($client);
        $this->postJson('/api/v1/admin/connections/check', ['service' => 'push'])
            ->assertOk()->assertJsonPath('code', 'push_ok')->assertDontSee('private-user-123')->assertDontSee('push-secret');
        $this->getJson('/api/v1/admin/connections')->assertOk()->assertDontSee('push-secret')->assertDontSee('private-app');
    }

    public function test_push_rejects_an_invalid_provider_response(): void
    {
        $this->actingAsAdministrator();
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb' => [
            'driver' => 'reverb', 'key' => 'push-key', 'secret' => 'push-secret', 'app_id' => 'private-app',
        ]]);
        $client = Mockery::mock();
        $client->shouldReceive('getChannels')->once()->andReturn((object) ['error' => 'private-provider-error']);
        Broadcast::shouldReceive('pusher')->once()->andReturn($client);
        $this->postJson('/api/v1/admin/connections/check', ['service' => 'push'])
            ->assertOk()->assertJsonPath('status', 'error')->assertJsonPath('code', 'push_unavailable')
            ->assertDontSee('private-provider-error');
    }

    public function test_horizon_requires_a_recent_running_worker_for_the_main_queue(): void
    {
        $this->actingAsAdministrator();
        config(['queue.default' => 'redis', 'queue.connections.redis.queue' => 'default']);
        Redis::shouldReceive('connection->ping')->andReturn(true);
        $masters = Mockery::mock(MasterSupervisorRepository::class);
        $supervisors = Mockery::mock(SupervisorRepository::class);
        $this->app->instance(MasterSupervisorRepository::class, $masters);
        $this->app->instance(SupervisorRepository::class, $supervisors);
        $master = (object) ['name' => 'private-host', 'environment' => app()->environment(), 'status' => 'running'];
        $masters->shouldReceive('all')->andReturn([$master]);
        $supervisor = (object) ['master' => 'private-host', 'status' => 'running', 'options' => ['connection' => 'redis'], 'processes' => ['redis:default' => 2]];
        $supervisors->shouldReceive('all')->andReturn([$supervisor]);
        $this->postJson('/api/v1/admin/connections/check', ['service' => 'horizon'])
            ->assertOk()->assertJsonPath('code', 'horizon_ok')->assertJsonPath('workers', 2)->assertDontSee('private-host');
        $supervisor->processes = ['redis:default,notifications' => 2];
        $this->postJson('/api/v1/admin/connections/check', ['service' => 'horizon'])
            ->assertOk()->assertJsonPath('code', 'horizon_ok')->assertJsonPath('workers', 2);
        $supervisor->processes = ['redis:other-queue' => 2];
        $this->postJson('/api/v1/admin/connections/check', ['service' => 'horizon'])
            ->assertOk()->assertJsonPath('code', 'horizon_no_workers');
        $supervisor->processes = ['redis:default' => 2];
        $supervisor->status = 'paused';
        $this->postJson('/api/v1/admin/connections/check', ['service' => 'horizon'])
            ->assertOk()->assertJsonPath('code', 'horizon_no_workers');
        $master->status = 'paused';
        $this->postJson('/api/v1/admin/connections/check', ['service' => 'horizon'])
            ->assertOk()->assertJsonPath('code', 'horizon_paused');
        $master->environment = 'another-environment';
        $this->postJson('/api/v1/admin/connections/check', ['service' => 'horizon'])
            ->assertOk()->assertJsonPath('code', 'horizon_inactive');
    }

    public function test_horizon_checks_redis_queue_configuration_and_reports_connection_errors_safely(): void
    {
        $this->actingAsAdministrator();
        config(['queue.default' => 'sync']);
        $this->postJson('/api/v1/admin/connections/check', ['service' => 'horizon'])
            ->assertOk()->assertJsonPath('status', 'not_configured')->assertJsonPath('code', 'horizon_queue_not_redis');
        config(['queue.default' => 'redis']);
        Redis::shouldReceive('connection')->once()->andThrow(new RuntimeException('redis-private-password'));
        $this->postJson('/api/v1/admin/connections/check', ['service' => 'horizon'])
            ->assertOk()->assertJsonPath('status', 'error')->assertJsonPath('code', 'horizon_unavailable')
            ->assertDontSee('redis-private-password');
    }

    public function test_storage_probe_is_removed_and_business_files_are_untouched(): void
    {
        $this->actingAsAdministrator();
        $disk = Storage::fake('diagnostics-test');
        config(['filesystems.disks.diagnostics-test' => ['driver' => 'local', 'root' => $disk->path('')]]);
        $disk->put('business.txt', 'unchanged');
        $this->postJson('/api/v1/admin/connections/check', ['service' => 'storage', 'disk' => 'diagnostics-test'])
            ->assertOk()->assertJsonPath('status', 'ok')->assertJsonPath('code', 'storage_ok');
        $this->assertSame('unchanged', $disk->get('business.txt'));
        $this->assertSame(['business.txt'], $disk->allFiles());
    }

    public function test_storage_cleanup_runs_even_after_a_read_failure(): void
    {
        $this->actingAsAdministrator();
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('put')->once()->andReturnTrue();
        $disk->shouldReceive('get')->once()->andThrow(new RuntimeException('secret-storage-error'));
        $disk->shouldReceive('delete')->once()->with(Mockery::on(fn ($path) => str_starts_with($path, '.starter-diagnostics/')))->andReturnTrue();
        Storage::shouldReceive('build')->once()->andReturn($disk);
        $this->postJson('/api/v1/admin/connections/check', ['service' => 'storage', 'disk' => 'local'])
            ->assertOk()->assertJsonPath('code', 'storage_unavailable')->assertDontSee('secret-storage-error');
    }

    public function test_storage_cleanup_failure_is_reported_as_a_failure(): void
    {
        $this->actingAsAdministrator();
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('put')->once()->andReturnFalse();
        $disk->shouldReceive('delete')->once()->andReturnFalse();
        Storage::shouldReceive('build')->once()->andReturn($disk);
        $this->postJson('/api/v1/admin/connections/check', ['service' => 'storage', 'disk' => 'local'])
            ->assertOk()->assertJsonPath('status', 'error')->assertJsonPath('code', 'storage_cleanup_failed');
    }

    public function test_unconfigured_s3_and_unknown_disks_are_not_probed(): void
    {
        $this->actingAsAdministrator();
        config(['filesystems.disks.s3.bucket' => null]);
        Storage::shouldReceive('build')->never();
        $this->postJson('/api/v1/admin/connections/check', ['service' => 'storage', 'disk' => 's3'])
            ->assertOk()->assertJsonPath('status', 'not_configured');
        $this->postJson('/api/v1/admin/connections/check', ['service' => 'storage', 'disk' => 'unknown'])->assertUnprocessable();
        $this->postJson('/api/v1/admin/connections/check', ['service' => 'storage'])->assertUnprocessable();
        $this->postJson('/api/v1/admin/connections/check', ['service' => 'unknown'])->assertUnprocessable();
    }

    private function ready(): array {}
}
