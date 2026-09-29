<?php

namespace Tests\Feature;

use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Modules\Auth\Models\User;
use Modules\Auth\Notifications\LoginAlert;
use Modules\Auth\Notifications\LoginInstructions;
use Modules\Auth\Notifications\PasswordChanged;
use Modules\Auth\Notifications\ResetPasswordLink;
use Tests\TestCase;

class AuthAccountMailTest extends TestCase
{
    private ChannelManager $notificationManager;

    protected function setUp(): void
    {
        parent::setUp();
        config(['logging.channels.auth' => ['driver' => 'monolog', 'handler' => \Monolog\Handler\NullHandler::class]]);
        $this->notificationManager = Notification::getFacadeRoot();
        Notification::fake();
    }

    private function account(): User
    {
        return User::factory()->create(['active' => true, 'password' => 'Initial@123']);
    }

    public function test_each_successful_login_queues_one_alert_but_failed_or_disabled_login_does_not(): void
    {
        $user = $this->account();
        $payload = ['email' => $user->email, 'password' => 'Initial@123', 'email_locale' => 'en'];
        config(['sanctum.stateful' => ['localhost']]);
        $this->withHeader('Origin', 'http://localhost');
        $this->postJson('/api/v1/auth/login', $payload)->assertOk();
        $this->postJson('/api/v1/auth/logout')->assertOk();
        $this->postJson('/api/v1/auth/login', $payload)->assertOk();
        Notification::assertSentToTimes($user, LoginAlert::class, 2);
        Notification::assertSentTo($user, LoginAlert::class, fn ($mail) => $mail->locale === 'en');
        $this->postJson('/api/v1/auth/logout')->assertOk();
        $this->postJson('/api/v1/auth/login', [...$payload, 'password' => 'Wrong@123'])->assertUnauthorized();
        $user->update(['active' => false]);
        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(423);
        Notification::assertSentToTimes($user, LoginAlert::class, 2);
        Notification::assertSentTo($user, LoginAlert::class, fn ($mail) => $mail->locale === 'en');
    }

    public function test_creation_and_admin_resend_send_instructions_without_changing_password(): void
    {
        $this->actingAsAdministrator();
        $this->postJson('/api/v1/auth/users', [
            'name' => 'Compte accueil', 'username' => 'accueil-mail', 'email' => 'accueil@example.test',
            'password' => 'Initial@123', 'active' => true, 'email_locale' => 'en',
        ])->assertSuccessful();
        $user = User::where('email', 'accueil@example.test')->firstOrFail();
        Notification::assertSentToTimes($user, LoginInstructions::class, 1);
        Notification::assertSentTo($user, LoginInstructions::class, fn ($mail) => $mail->locale === 'en');
        $hash = $user->password;
        $this->postJson('/api/v1/auth/users/'.$user->id.'/login-instructions', ['email_locale' => 'fr'])->assertOk();
        Notification::assertSentToTimes($user, LoginInstructions::class, 2);
        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_resend_requires_an_administrator(): void
    {
        $user = $this->account();
        $this->postJson('/api/v1/auth/users/'.$user->id.'/login-instructions')->assertUnauthorized();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/auth/users/'.$user->id.'/login-instructions')->assertForbidden();
        Notification::assertNothingSent();
    }

    public function test_force_reset_and_personal_change_notify_only_after_success(): void
    {
        $user = $this->account();
        $this->actingAsAdministrator();
        $this->postJson('/api/v1/auth/users/force-change-password', [
            'user_id' => $user->id, 'email_locale' => 'en',
        ])->assertOk();
        Notification::assertSentTo($user, ResetPasswordLink::class, fn ($mail) => $mail->locale === 'en');
        Notification::assertNotSentTo($user, PasswordChanged::class);
        $this->assertTrue($user->fresh()->must_change_password);
        $this->assertTrue(Hash::check('Initial@123', $user->fresh()->password));
        $link = Notification::sent($user, ResetPasswordLink::class)->first()->toMail($user)->viewData['url'];
        parse_str(parse_url($link, PHP_URL_QUERY), $query);
        $reset = $query + ['new_password' => 'Second@123', 'new_password_confirmation' => 'Second@123'];
        $this->patchJson('/api/v1/auth/reset-password', $reset)->assertOk();
        $this->assertFalse($user->fresh()->must_change_password);
        $this->patchJson('/api/v1/auth/reset-password', $reset)->assertStatus(400);
        Sanctum::actingAs($user->fresh());
        $this->putJson('/api/v1/auth/users/'.$user->id.'/security/password', [
            'current_password' => 'Wrong@123', 'new_password' => 'Third@123', 'new_password_confirmation' => 'Third@123',
        ])->assertUnprocessable();
        Notification::assertSentToTimes($user, PasswordChanged::class, 1);
        $this->putJson('/api/v1/auth/users/'.$user->id.'/security/password', [
            'current_password' => 'Second@123', 'new_password' => 'Third@123', 'new_password_confirmation' => 'Third@123',
        ])->assertOk();
        Notification::assertSentToTimes($user, PasswordChanged::class, 2);
        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_resending_admin_reset_invalidates_previous_link_and_blocks_normal_access(): void
    {
        $user = $this->account();
        $this->actingAsAdministrator();
        $payload = ['user_id' => $user->id, 'email_locale' => 'fr'];
        $this->postJson('/api/v1/auth/users/force-change-password', $payload)->assertOk();
        $first = Notification::sent($user, ResetPasswordLink::class)->first()->toMail($user)->viewData['url'];
        $this->postJson('/api/v1/auth/users/force-change-password', $payload)->assertOk();
        parse_str(parse_url($first, PHP_URL_QUERY), $query);
        $this->patchJson('/api/v1/auth/reset-password', $query + [
            'new_password' => 'Recovered@123', 'new_password_confirmation' => 'Recovered@123',
        ])->assertStatus(400);
        $this->assertTrue(Hash::check('Initial@123', $user->fresh()->password));
        $this->assertTrue($user->fresh()->must_change_password);
        Sanctum::actingAs($user->fresh());
        $this->getJson('/api/v1/auth/users')->assertForbidden();
        Notification::assertSentToTimes($user, ResetPasswordLink::class, 2);
        Notification::assertNotSentTo($user, PasswordChanged::class);
    }

    public function test_legacy_reset_also_notifies_and_checks_confirmation(): void
    {
        $user = $this->account();
        $this->actingAsAdministrator();
        $payload = ['user_id' => $user->id, 'new_password' => 'Second@123'];
        $this->putJson('/api/v1/auth/users/change-password', $payload)->assertUnprocessable();
        Notification::assertNothingSent();
        $this->putJson('/api/v1/auth/users/change-password', $payload + ['new_password_confirmation' => 'Second@123'])->assertOk();
        Notification::assertSentToTimes($user, PasswordChanged::class, 1);
    }

    public function test_forgotten_password_is_generic_and_reset_token_is_single_use(): void
    {
        $user = $this->account();
        $known = $this->postJson('/api/v1/auth/forgotten-password', ['email' => $user->email, 'email_locale' => 'en'])->assertOk();
        $unknown = $this->postJson('/api/v1/auth/forgotten-password', ['email' => 'absent@example.test'])->assertOk();
        $this->assertSame($known->json(), $unknown->json());
        Notification::assertSentToTimes($user, ResetPasswordLink::class, 1);
        $link = Notification::sent($user, ResetPasswordLink::class)->first()->toMail($user)->viewData['url'];
        parse_str(parse_url($link, PHP_URL_QUERY), $query);
        $this->assertSame('en', $query['email_locale']);
        $stored = DB::table('auth_password_reset_tokens')->where('email', $user->email)->first();
        $this->assertTrue(Hash::check($query['token'], $stored->token));
        $payload = $query + ['new_password' => 'Recovered@123', 'new_password_confirmation' => 'Recovered@123'];
        $this->patchJson('/api/v1/auth/reset-password', $payload)->assertOk();
        Notification::assertSentToTimes($user, PasswordChanged::class, 1);
        $this->assertTrue(Hash::check('Recovered@123', $user->fresh()->password));
        $this->patchJson('/api/v1/auth/reset-password', $payload)->assertStatus(400);
        Notification::assertSentToTimes($user, PasswordChanged::class, 1);
    }

    public function test_invalid_mail_language_is_rejected_without_sending(): void
    {
        $user = $this->account();
        $this->postJson('/api/v1/auth/forgotten-password', ['email' => $user->email, 'email_locale' => 'de'])
            ->assertUnprocessable()->assertJsonValidationErrors('email_locale');
        $this->actingAsAdministrator();
        $this->postJson('/api/v1/auth/users/'.$user->id.'/login-instructions', ['email_locale' => 'de'])
            ->assertUnprocessable()->assertJsonValidationErrors('email_locale');
        Notification::assertNothingSent();
    }

    public function test_both_languages_and_embedded_logo_survive_worker_locale(): void
    {
        $user = $this->account();
        config(['mail.default' => 'array', 'mail.from.name' => 'Unexpected legacy sender']);
        Mail::purge();
        Queue::fake();
        Notification::swap($this->notificationManager);
        foreach (['fr', 'en'] as $locale) {
            foreach ([new LoginInstructions($locale), new LoginAlert('2026-09-17', '192.0.2.1', 'Browser', $locale),
                new PasswordChanged('2026-09-17', true, true, $locale), new ResetPasswordLink('opaque-token', $locale)] as $notification) {
                $user->notify($notification);
            }
        }
        foreach (Queue::pushed(SendQueuedNotifications::class) as $job) {
            app()->setLocale($job->notification->locale === 'fr' ? 'en' : 'fr');
            $job->handle($this->notificationManager);
        }
        $delivered = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(8, $delivered);
        foreach ($delivered->values() as $index => $mail) {
            $locale = $index < 4 ? 'fr' : 'en';
            $message = $mail->getOriginalMessage();
            $this->assertSame(config('app.name'), array_values($message->getFrom())[0]->getName());
            $this->assertStringNotContainsString('Unexpected legacy sender', $message->toString());
            $html = $message->getHtmlBody();
            $this->assertStringContainsString('lang="'.$locale.'"', $html);
            $this->assertStringContainsString('alt="'.config('app.name').'"', $html);
            $this->assertStringContainsString('src="cid:', $html);
            $this->assertStringContainsString($locale === 'fr' ? 'Bonjour' : 'Hello', $html);
            $this->assertNotEmpty($message->getAttachments());
        }
    }

    public function test_login_queues_encrypted_mail_in_database_without_redis_or_smtp(): void
    {
        $user = $this->account();
        config(['auth.mail_connection' => 'database', 'queue.default' => 'sync', 'sanctum.stateful' => ['localhost']]);
        Notification::swap($this->notificationManager);

        $this->withHeader('Origin', 'http://localhost')
            ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Initial@123'])
            ->assertOk();

        $job = DB::table('jobs')->where('queue', 'auth-mail')->first();
        $this->assertNotNull($job);
        $payload = json_decode($job->payload, true);
        $notification = unserialize(decrypt($payload['data']['command']));
        $this->assertInstanceOf(SendQueuedNotifications::class, $notification);
        $this->assertInstanceOf(LoginAlert::class, $notification->notification);
        $this->assertTrue($notification->shouldBeEncrypted);
    }

    public function test_auth_messages_use_encrypted_redis_jobs_and_can_be_delivered_by_the_worker(): void
    {
        $user = $this->account();
        config(['app.url' => 'https://starter.example.test', 'mail.default' => 'array', 'auth.mail_connection' => 'redis']);
        Mail::purge();
        Queue::fake();
        Notification::swap($this->notificationManager);
        $messages = [new LoginAlert(now()->toIso8601String(), '192.0.2.1', 'Browser'), new LoginInstructions,
            new PasswordChanged(now()->toIso8601String(), true, true), new ResetPasswordLink('opaque-reset-token', 'fr')];
        foreach ($messages as $message) {
            $user->notify($message);
        }
        Queue::assertPushed(SendQueuedNotifications::class, 4);
        foreach (Queue::pushed(SendQueuedNotifications::class) as $job) {
            $this->assertSame('redis', $job->connection);
            $this->assertSame('auth-mail', $job->queue);
            $this->assertTrue($job->afterCommit);
            $this->assertTrue($job->shouldBeEncrypted);
            $this->assertSame(3, $job->tries);
            $job->handle($this->notificationManager);
        }
        $delivered = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(4, $delivered);
        foreach ($delivered as $mail) {
            $this->assertStringNotContainsString('Initial@123', $mail->getOriginalMessage()->toString());
        }
        $this->assertSame(['auth-mail'], config('horizon.defaults.supervisor-auth-mail.queue'));
        $this->assertSame('redis', config('horizon.defaults.supervisor-auth-mail.connection'));
        $this->assertArrayHasKey('supervisor-auth-mail', config('horizon.environments.production'));
    }
}
