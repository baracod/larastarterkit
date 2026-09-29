<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 255);
            $table->string('username', 255)->nullable();
            $table->string('email', 255);
            $table->text('additional_info')->nullable();
            $table->string('avatar', 255)->nullable()->default('photos/shares/default-user.png');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password', 255);
            $table->string('remember_token', 100)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['email'], 'auth_users_email_unique');
            $table->unique(['username'], 'auth_users_username_unique');
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('password_changed_at')->nullable();
        });
        Schema::create('auth_password_reset_tokens', function (Blueprint $table): void {
            $table->string('email', 255);
            $table->string('token', 255);
            $table->timestamp('created_at')->nullable();
            $table->primary('email');
        });
        Schema::create('auth_email_resets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('email', 255);
            $table->string('verification_token', 255)->nullable();
            $table->dateTime('expired_at')->nullable();
            $table->integer('count_incorrect')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['verification_token'], 'auth_email_resets_verification_token_unique');
        });
        Schema::create('auth_user_verifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('verification_token', 255)->nullable();
            $table->dateTime('expired_at')->nullable();
            $table->integer('count_incorrect')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['verification_token'], 'auth_user_verifications_verification_token_unique');
        });
        Schema::create('auth_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 255);
            $table->string('display_name', 255);
            $table->string('description', 255)->nullable();
            $table->integer('order')->nullable();
            $table->boolean('is_owner')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['name'], 'auth_roles_name_unique');
        });
        Schema::create('auth_permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 255);
            $table->string('action', 255)->nullable();
            $table->string('subject', 255)->nullable();
            $table->string('description', 255)->nullable();
            $table->string('table_name', 255)->nullable();
            $table->boolean('always_allow')->default(false);
            $table->boolean('is_public')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['key'], 'key');
            $table->index(['key'], 'auth_permissions_key_index');
        });
        Schema::create('auth_role_permissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['role_id'], 'auth_role_permissions_role_id_foreign');
            $table->index(['permission_id'], 'auth_role_permissions_permission_id_foreign');
        });
        Schema::create('auth_user_roles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['role_id'], 'auth_user_roles_role_id_foreign');
            $table->index(['user_id'], 'auth_user_roles_user_id_foreign');
        });
        Schema::create('auth_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('tokenable_type', 255)->nullable();
            $table->unsignedBigInteger('tokenable_id');
            $table->string('name', 255);
            $table->string('token', 64);
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['token'], 'personal_access_tokens_token_unique');
            $table->index(['tokenable_type', 'tokenable_id'], 'personal_access_tokens_tokenable_type_tokenable_id_index');
        });
        Schema::create('auth_sessions', function (Blueprint $table): void {
            $table->string('id', 255);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload')->nullable();
            $table->integer('last_activity');
            $table->index(['user_id'], 'auth_sessions_user_id_index');
            $table->index(['last_activity'], 'auth_sessions_last_activity_index');
            $table->primary('id');
        });
        Schema::create('auth_notifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('receiver_user_id');
            $table->string('type', 255);
            $table->string('title', 255);
            $table->text('content');
            $table->boolean('is_read')->default(false);
            $table->unsignedBigInteger('sender_user_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['receiver_user_id'], 'auth_notifications_receiver_user_id_foreign');
            $table->index(['sender_user_id'], 'auth_notifications_sender_user_id_foreign');
        });
        Schema::create('auth_user_module_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('module', 80);
            $table->string('key', 120);
            $table->json('value')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['user_id', 'module', 'key'], 'auth_user_module_settings_unique');
            $table->index(['module', 'key'], 'auth_user_module_settings_module_key_index');
        });
        Schema::create('auth_notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('auth_users')->cascadeOnDelete();
            $table->string('category');
            $table->json('channels');
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('cooldown_minutes')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_notification_preferences');
        Schema::dropIfExists('auth_user_module_settings');
        Schema::dropIfExists('auth_notifications');
        Schema::dropIfExists('auth_sessions');
        Schema::dropIfExists('auth_access_tokens');
        Schema::dropIfExists('auth_user_roles');
        Schema::dropIfExists('auth_role_permissions');
        Schema::dropIfExists('auth_permissions');
        Schema::dropIfExists('auth_roles');
        Schema::dropIfExists('auth_user_verifications');
        Schema::dropIfExists('auth_email_resets');
        Schema::dropIfExists('auth_password_reset_tokens');
        Schema::dropIfExists('auth_users');
    }
};
