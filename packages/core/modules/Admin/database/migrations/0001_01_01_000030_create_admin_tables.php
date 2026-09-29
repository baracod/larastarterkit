<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_notifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('type', 255)->default('info');
            $table->string('title', 255);
            $table->text('message')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['user_id', 'read_at'], 'admin_notifications_user_id_read_at_index');
        });
        Schema::create('admin_notification_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('notification_type', 255);
            $table->json('channels')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['notification_type'], 'admin_notification_settings_notification_type_unique');
        });
        Schema::create('admin_module_configs', function (Blueprint $table): void {
            $table->id();
            $table->string('module_name', 255);
            $table->string('key', 255);
            $table->json('value')->nullable();
            $table->string('type', 255)->default('string');
            $table->text('description')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['module_name', 'key'], 'admin_module_configs_module_name_key_unique');
            $table->index(['module_name'], 'admin_module_configs_module_name_index');
        });
        Schema::create('admin_settings', function (Blueprint $table): void {
            $table->id();
            $table->enum('type', ['system', 'module', 'user'])->default('system');
            $table->string('module', 255)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('key', 255);
            $table->longText('value')->nullable();
            $table->string('value_type', 255)->default('string');
            $table->text('label')->nullable();
            $table->text('description')->nullable();
            $table->string('input_type', 255)->default('text');
            $table->json('options')->nullable();
            $table->longText('default_value')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['type', 'key', 'module', 'user_id'], 'unique_setting');
            $table->index(['type', 'module'], 'admin_settings_type_module_index');
            $table->index(['type', 'user_id'], 'admin_settings_type_user_id_index');
            $table->index(['type'], 'admin_settings_type_index');
            $table->index(['module'], 'admin_settings_module_index');
            $table->index(['user_id'], 'admin_settings_user_id_index');
            $table->index(['key'], 'admin_settings_key_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_settings');
        Schema::dropIfExists('admin_module_configs');
        Schema::dropIfExists('admin_notification_settings');
        Schema::dropIfExists('admin_notifications');
    }
};
