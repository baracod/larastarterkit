<?php

namespace Tests\Feature\Modules\Auth\Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Modules\Auth\Models\User;
use Modules\Auth\Notifications\PasswordChanged;
use Modules\Auth\Notifications\ResetPasswordLink;
use Modules\Auth\Services\PasswordService;
use Tests\TestCase;

class PasswordServiceTest extends TestCase
{
    private PasswordService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        // Rediriger le canal 'auth' vers null pour éviter les erreurs de permission en test
        config(['logging.channels.auth.driver' => 'null']);

        $this->service = app(PasswordService::class);

        $this->user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('OldPass@123'),
            'active' => true,
        ]);
    }

    // ─── sendResetLink ────────────────────────────────────────────────────────

    public function test_send_reset_link_stores_token_and_sends_notification(): void
    {
        Notification::fake();

        $this->service->sendResetLink(['email' => $this->user->email]);

        $this->assertDatabaseHas('auth_password_reset_tokens', [
            'email' => $this->user->email,
        ]);

        Notification::assertSentTo($this->user, ResetPasswordLink::class);
    }

    public function test_send_reset_link_silently_succeeds_for_unknown_email(): void
    {
        Notification::fake();

        $this->service->sendResetLink(['email' => 'unknown@example.com']);

        Notification::assertNothingSent();
    }

    // ─── resetPassword ────────────────────────────────────────────────────────

    public function test_reset_password_updates_password_and_clears_token(): void
    {
        $rawToken = \Illuminate\Support\Str::random(64);

        \DB::table('auth_password_reset_tokens')->insert([
            'email' => $this->user->email,
            'token' => Hash::make($rawToken),
            'created_at' => now(),
        ]);

        $this->service->resetPassword([
            'email' => $this->user->email,
            'token' => $rawToken,
            'new_password' => 'NewPass@999',
            'new_password_confirmation' => 'NewPass@999',
        ]);

        $this->user->refresh();
        $this->assertTrue(Hash::check('NewPass@999', $this->user->password));
        $this->assertFalse((bool) $this->user->must_change_password);
        $this->assertDatabaseMissing('auth_password_reset_tokens', ['email' => $this->user->email]);
        Notification::assertSentTo($this->user, PasswordChanged::class);
    }

    public function test_reset_password_fails_with_invalid_token(): void
    {
        \DB::table('auth_password_reset_tokens')->insert([
            'email' => $this->user->email,
            'token' => Hash::make('valid-token'),
            'created_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);

        $this->service->resetPassword([
            'email' => $this->user->email,
            'token' => 'wrong-token',
            'new_password' => 'NewPass@999',
        ]);
    }

    public function test_reset_password_fails_with_expired_token(): void
    {
        $rawToken = 'some-token';
        \DB::table('auth_password_reset_tokens')->insert([
            'email' => $this->user->email,
            'token' => Hash::make($rawToken),
            'created_at' => now()->subHours(2),
        ]);

        $this->expectException(\RuntimeException::class);

        $this->service->resetPassword([
            'email' => $this->user->email,
            'token' => $rawToken,
            'new_password' => 'NewPass@999',
        ]);
    }

    // ─── changePassword ───────────────────────────────────────────────────────

    public function test_change_password_updates_password_when_current_is_correct(): void
    {
        $this->service->changePassword($this->user, [
            'current_password' => 'OldPass@123',
            'new_password' => 'Updated@456!',
        ]);

        $this->user->refresh();
        $this->assertTrue(Hash::check('Updated@456!', $this->user->password));
        $this->assertFalse((bool) $this->user->must_change_password);
        Notification::assertSentTo($this->user, PasswordChanged::class);
    }

    public function test_change_password_fails_when_current_is_wrong(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->service->changePassword($this->user, [
            'current_password' => 'WrongPass@123',
            'new_password' => 'Updated@456!',
        ]);
    }

    // ─── forceChangePassword ──────────────────────────────────────────────────

    public function test_admin_password_reset_sends_link_without_setting_password(): void
    {
        Notification::fake();
        $this->actingAsAdministrator();
        $this->user->createToken('existing-session');

        $this->postJson('/api/v1/auth/users/force-change-password', [
            'user_id' => $this->user->id,
            'email_locale' => 'en',
        ])->assertOk();

        $this->assertSame($this->user->password, $this->user->fresh()->password);
        Notification::assertSentTo($this->user, ResetPasswordLink::class, fn ($mail) => $mail->locale === 'en');
        $this->assertTrue((bool) $this->user->fresh()->must_change_password);
        $this->assertSame(0, $this->user->tokens()->count());
    }

    public function test_admin_email_reset_rejects_administrator_supplied_password(): void
    {
        $this->actingAsAdministrator();
        $this->user->createToken('existing-session');
        $originalPassword = $this->user->password;

        foreach ([[], ['new_password_confirmation' => 'Different@999']] as $confirmation) {
            $this->postJson('/api/v1/auth/users/force-change-password', $confirmation + [
                'user_id' => $this->user->id,
                'new_password' => 'NewPass@999',
                'must_change_password' => true,
            ])->assertUnprocessable()->assertJsonValidationErrors('new_password');

            $this->assertSame($originalPassword, $this->user->fresh()->password);
            $this->assertSame(1, $this->user->tokens()->count());
        }
    }

    public function test_non_admin_cannot_request_a_password_reset_for_another_user(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs(User::factory()->create(['active' => true]));
        $originalPassword = $this->user->password;

        $this->postJson('/api/v1/auth/users/force-change-password', [
            'user_id' => $this->user->id,
            'new_password' => 'NewPass@999',
            'new_password_confirmation' => 'NewPass@999',
        ])->assertForbidden();

        $this->assertSame($originalPassword, $this->user->fresh()->password);
    }

    public function test_force_change_password_updates_target_user_password(): void
    {
        $admin = User::factory()->create(['active' => true]);

        $updated = $this->service->forceChangePassword($admin, [
            'user_id' => $this->user->id,
            'new_password' => 'Force@Pass!9',
            'must_change_password' => true,
        ]);

        $this->assertTrue(Hash::check('Force@Pass!9', $updated->password));
        $this->assertTrue((bool) $updated->must_change_password);
        Notification::assertSentTo($this->user, PasswordChanged::class);
    }

    public function test_force_change_password_revokes_target_tokens(): void
    {
        $admin = User::factory()->create(['active' => true]);
        $this->user->createToken('test-token');
        $this->assertCount(1, $this->user->tokens);

        $this->service->forceChangePassword($admin, [
            'user_id' => $this->user->id,
            'new_password' => 'Force@Pass!9',
        ]);

        $this->assertCount(0, $this->user->fresh()->tokens);
        Notification::assertSentTo($this->user, PasswordChanged::class);
    }
}
