<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('auth_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('action')->nullable();
            $table->string('subject')->nullable();
            $table->string('description')->nullable();
            $table->string('table_name')->nullable();
            $table->boolean('always_allow')->default(false);
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->index('key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auth_permissions');
    }
};
