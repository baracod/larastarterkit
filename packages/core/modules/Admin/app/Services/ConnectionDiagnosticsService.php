<?php

namespace Modules\Admin\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Throwable;

class ConnectionDiagnosticsService
{
    public function catalog(): array
    {
        $connection = (string) config('database.default');
        try {
            $mailTransport = $this->mailConfiguration()['transport'] ?? null;
        } catch (Throwable) {
            $mailTransport = null;
        }

        return [
            'database' => ['connection' => $connection, 'driver' => config("database.connections.{$connection}.driver")],
            'storage' => [
                'defaultDisk' => config('filesystems.default'),
                'disks' => collect(config('filesystems.disks', []))->map(fn ($disk, $name) => [
                    'name' => $name, 'driver' => $disk['driver'] ?? 'unknown',
                ])->values()->all(),
            ],
            'mail' => ['mailer' => config('mail.default'), 'transport' => $mailTransport],
            'push' => [
                'connection' => config('broadcasting.default', 'null'),
                'driver' => config('broadcasting.connections.'.config('broadcasting.default').'.driver', 'null'),
            ],
            'scheduler' => ['cache' => config('cache.default')],
            'horizon' => [
                'connection' => config('queue.default'),
                'queue' => config('queue.connections.'.config('queue.default').'.queue', 'default'),
            ],
        ];
    }

    public function check(string $service, ?string $disk = null): array
    {
        $started = hrtime(true);
        try {
            $result = match ($service) {
                'database' => $this->database(),
                'storage' => $this->storage((string) $disk),
                'mail' => $this->mail(),
                'push' => $this->push(),
                'horizon' => $this->horizon(),
                'scheduler' => $this->scheduler(),
            };
        } catch (Throwable) {
            $result = ['status' => 'error', 'code' => $service.'_unavailable'];
        }

        return $result + [
            'service' => $service,
            'durationMs' => (int) round((hrtime(true) - $started) / 1_000_000),
            'checkedAt' => now()->toIso8601String(),
        ];
    }

    private function scheduler(): array
    {
        $store = config('cache.default');
        if (! in_array(config("cache.stores.{$store}.driver"), ['redis', 'database', 'memcached'], true)) {
            return ['status' => 'not_configured', 'code' => 'scheduler_shared_cache_required'];
        }
        $heartbeat = \Illuminate\Support\Facades\Cache::get('starter:scheduler:heartbeat');
        if (! is_numeric($heartbeat) || now()->timestamp - (int) $heartbeat > 180 || (int) $heartbeat > now()->timestamp) {
            return ['status' => 'error', 'code' => 'scheduler_inactive'];
        }

        return ['status' => 'ok', 'code' => 'scheduler_ok'];
    }

    private function database(): array
    {
        DB::connection()->select('SELECT 1', [], false);

        return ['status' => 'ok', 'code' => 'database_ok'];
    }

    private function mailConfiguration(): array
    {
        $config = config('mail.mailers.'.config('mail.default'), []);
        // Resolve MAIL_URL with the same parser and precedence as Laravel's MailManager.
        if (isset($config['url'])) {
            $config = array_merge($config, (new ConfigurationUrlParser)->parseConfiguration($config));
            $config['transport'] = Arr::pull($config, 'driver');
        }

        return $config;
    }

    private function push(): array
    {
        $name = config('broadcasting.default');
        $config = config("broadcasting.connections.{$name}", []);
        $driver = $config['driver'] ?? 'null';
        if (! $name || in_array($name, ['null', 'log'], true) || in_array($driver, ['null', 'log'], true)) {
            return ['status' => 'not_configured', 'code' => 'push_configuration_missing'];
        }
        if (! in_array($driver, ['pusher', 'reverb'], true)) {
            return ['status' => 'not_configured', 'code' => 'push_unsupported'];
        }
        if (empty($config['key']) || empty($config['secret']) || empty($config['app_id'])) {
            return ['status' => 'not_configured', 'code' => 'push_configuration_missing'];
        }
        $config['client_options'] = array_replace($config['client_options'] ?? [], ['connect_timeout' => 3, 'timeout' => 5]);
        $config['options']['timeout'] = 5;
        try {
            // The authenticated channels API is read-only; no notification is published.
            $response = Broadcast::pusher($config)->getChannels();
        } catch (Throwable) {
            return class_exists(\Pusher\Pusher::class)
                ? ['status' => 'error', 'code' => 'push_unavailable']
                : ['status' => 'not_configured', 'code' => 'push_driver_missing'];
        }
        if (! is_object($response) || ! isset($response->channels)) {
            return ['status' => 'error', 'code' => 'push_unavailable'];
        }

        return ['status' => 'ok', 'code' => 'push_ok'];
    }

    private function horizon(): array
    {
        $connection = (string) config('queue.default');
        $queue = (string) config("queue.connections.{$connection}.queue", 'default');
        if (config("queue.connections.{$connection}.driver") !== 'redis') {
            return ['status' => 'not_configured', 'code' => 'horizon_queue_not_redis'];
        }
        $pong = Redis::connection(config("queue.connections.{$connection}.connection", 'default'))->ping();
        if (! in_array(strtoupper((string) $pong), ['1', 'PONG', '+PONG'], true)) {
            return ['status' => 'error', 'code' => 'horizon_unavailable'];
        }
        // Horizon's repositories only return supervisors with recent heartbeats.
        $masters = collect(app(MasterSupervisorRepository::class)->all())
            ->filter(fn ($master) => $master->environment === app()->environment());
        if ($masters->isEmpty()) {
            return ['status' => 'error', 'code' => 'horizon_inactive'];
        }
        if ($masters->contains(fn ($master) => $master->status !== 'running')) {
            return ['status' => 'error', 'code' => 'horizon_paused'];
        }
        $supervisors = collect(app(SupervisorRepository::class)->all())
            ->filter(fn ($supervisor) => $masters->contains('name', $supervisor->master)
                && $supervisor->status === 'running'
                && ($supervisor->options['connection'] ?? null) === $connection);
        $workers = (int) $supervisors->sum(fn ($supervisor) => collect($supervisor->processes)
            ->filter(function ($count, $pool) use ($connection, $queue) {
                [$poolConnection, $queues] = array_pad(explode(':', $pool, 2), 2, '');

                return $poolConnection === $connection && in_array($queue, array_map('trim', explode(',', $queues)), true);
            })->sum());
        if ($workers < 1) {
            return ['status' => 'error', 'code' => 'horizon_no_workers'];
        }

        return ['status' => 'ok', 'code' => 'horizon_ok', 'workers' => $workers];
    }

    private function mail(): array
    {
        $config = $this->mailConfiguration();
        $driver = $config['transport'] ?? null;
        if (! $driver) {
            return ['status' => 'not_configured', 'code' => 'mail_configuration_missing'];
        }
        if (in_array($driver, ['log', 'array'], true)) {
            return ['status' => 'not_configured', 'code' => 'mail_local_only'];
        }
        if ($driver !== 'smtp') {
            return ['status' => 'not_configured', 'code' => 'mail_unsupported'];
        }
        if (empty($config['host']) || empty($config['port'])) {
            return ['status' => 'not_configured', 'code' => 'mail_configuration_missing'];
        }

        // A separate transport keeps the application's cached mailer untouched.
        $transport = Mail::createSymfonyTransport(array_replace($config, ['timeout' => 5]));
        if (! $transport instanceof EsmtpTransport) {
            return ['status' => 'not_configured', 'code' => 'mail_unsupported'];
        }
        try {
            // Symfony negotiates TLS and SMTP authentication using the configured options.
            $transport->start();
            $transport->executeCommand("NOOP\r\n", [250]);
        } finally {
            try {
                $transport->stop();
            } finally {
                // stop() alone does not close the socket when start() fails during authentication.
                $transport->getStream()->terminate();
            }
        }

        return ['status' => 'ok', 'code' => 'mail_ok'];
    }

    private function storage(string $name): array
    {
        $config = config("filesystems.disks.{$name}");
        if (! is_array($config) || empty($config['driver'])) {
            return ['status' => 'not_configured', 'code' => 'storage_configuration_missing'];
        }
        if ($config['driver'] === 's3') {
            if (empty($config['bucket']) || empty($config['region'])) {
                return ['status' => 'not_configured', 'code' => 'storage_configuration_missing'];
            }
            if (! class_exists(\League\Flysystem\AwsS3V3\AwsS3V3Adapter::class)) {
                return ['status' => 'not_configured', 'code' => 'storage_driver_missing'];
            }
            $config['http'] = array_replace($config['http'] ?? [], ['connect_timeout' => 3, 'timeout' => 5]);
            $config['retries'] = 0;
        }
        $config['timeout'] = 5;
        $config['throw'] = true;
        $disk = Storage::build($config);
        $path = '.starter-diagnostics/'.Str::uuid().'.txt';
        $content = 'STARTER connection diagnostic '.Str::random(32);
        $result = ['status' => 'error', 'code' => 'storage_write_failed'];
        try {
            if ($disk->put($path, $content)) {
                $result = $disk->get($path) === $content
                    ? ['status' => 'ok', 'code' => 'storage_ok']
                    : ['status' => 'error', 'code' => 'storage_read_failed'];
            }
        } catch (Throwable) {
            $result = ['status' => 'error', 'code' => 'storage_unavailable'];
        } finally {
            try {
                if (! $disk->delete($path)) {
                    $result = ['status' => 'error', 'code' => 'storage_cleanup_failed'];
                }
            } catch (Throwable) {
                $result = ['status' => 'error', 'code' => 'storage_cleanup_failed'];
            }
        }

        return $result;
    }
}
