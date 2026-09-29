<?php

namespace Tests\Feature\Modules\Auth\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserProfileAvatarTest extends TestCase
{
    public function test_profile_upload_stores_the_avatar_and_returns_its_public_url(): void
    {
        Storage::fake('public');
        $administrator = $this->actingAsAdministrator('avatar-admin@starter.local');

        $response = $this->post('/api/v1/auth/users/update-profile/'.$administrator->id, [
            'name' => 'Avatar Administrator',
            'avatar_file' => UploadedFile::fake()->image('profile.jpg', 320, 320),
        ], ['Accept' => 'application/json']);

        $response->assertOk();

        $path = $administrator->fresh()->getRawOriginal('avatar');
        $this->assertIsString($path);
        Storage::disk('public')->assertExists($path);
        $response->assertJsonPath('data.avatar', Storage::disk('public')->url($path));
        $response->assertJsonPath('data.avatarUrl', Storage::disk('public')->url($path));
    }

    public function test_replacing_an_avatar_deletes_the_previous_file_by_its_storage_path(): void
    {
        Storage::fake('public');
        $administrator = $this->actingAsAdministrator('avatar-replace-admin@starter.local');
        Storage::disk('public')->put('avatars/old.jpg', 'old avatar');
        $administrator->forceFill(['avatar' => 'avatars/old.jpg'])->save();

        $response = $this->post('/api/v1/auth/users/update-profile/'.$administrator->id, [
            'avatar_file' => UploadedFile::fake()->image('replacement.png', 240, 240),
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        Storage::disk('public')->assertMissing('avatars/old.jpg');
        Storage::disk('public')->assertExists($administrator->fresh()->getRawOriginal('avatar'));
    }

    public function test_authenticated_user_response_exposes_the_public_avatar_url(): void
    {
        Storage::fake('public');
        $administrator = $this->actingAsAdministrator('avatar-session-admin@starter.local');
        Storage::disk('public')->put('avatars/session.jpg', 'avatar');
        $administrator->forceFill(['avatar' => 'avatars/session.jpg'])->save();

        $this->getJson('/api/v1/auth/user')
            ->assertOk()
            ->assertJsonPath('data.avatar', Storage::disk('public')->url('avatars/session.jpg'))
            ->assertJsonPath('data.avatarUrl', Storage::disk('public')->url('avatars/session.jpg'));
    }
}
