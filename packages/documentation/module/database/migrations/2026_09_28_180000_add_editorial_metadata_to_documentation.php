<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentation_revisions', function (Blueprint $table) {
            $table->string('note', 255)->nullable();
        });
        Schema::table('documentation_images', function (Blueprint $table) {
            $table->string('alt', 240)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('documentation_revisions', function (Blueprint $table) {
            $table->dropColumn('note');
        });
        Schema::table('documentation_images', function (Blueprint $table) {
            $table->dropColumn('alt');
        });
    }
};
