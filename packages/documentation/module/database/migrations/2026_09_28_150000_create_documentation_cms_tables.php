<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentation_collections', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug', 80)->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('default_edition_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
        Schema::create('documentation_editions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained('documentation_collections');
            $table->string('title');
            $table->string('slug', 80);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['collection_id', 'slug']);
        });
        Schema::create('documentation_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edition_id')->constrained('documentation_editions');
            $table->unsignedBigInteger('current_revision_id')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
        Schema::create('documentation_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('documentation_pages');
            $table->foreignId('parent_id')->nullable()->constrained('documentation_pages');
            $table->string('title');
            $table->string('path', 180);
            $table->unsignedInteger('position')->default(0);
            $table->longText('markdown');
            $table->foreignId('author_id')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->timestamp('created_at');
        });
        Schema::create('documentation_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained('documentation_collections');
            $table->string('name');
            $table->string('file');
            $table->string('mime', 40);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedBigInteger('size');
            $table->foreignId('author_id')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
        Schema::create('documentation_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edition_id')->nullable()->constrained('documentation_editions');
            $table->foreignId('author_id')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->string('kind', 20)->default('publish');
            $table->string('status', 20)->default('queued')->index();
            $table->json('snapshot');
            $table->json('portal')->nullable();
            $table->longText('log')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['publications', 'images', 'revisions', 'pages', 'editions', 'collections'] as $table) {
            Schema::dropIfExists('documentation_'.$table);
        }
    }
};
