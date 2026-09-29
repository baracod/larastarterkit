<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedure_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('operation_type', 40);
            $table->unsignedBigInteger('operation_id');
            $table->string('document_key', 80);
            $table->string('reference');
            $table->string('issuer');
            $table->date('issued_at');
            $table->string('source', 20);
            $table->string('path');
            $table->string('original_name');
            $table->string('sha256', 64);
            $table->foreignId('created_by')->constrained('auth_users');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('supersedes_id')->nullable()->constrained('procedure_documents')->restrictOnDelete();
            $table->string('template_version')->nullable();
            $table->json('generation_snapshot')->nullable();
            $table->timestamps();
            $table->unique(['operation_type', 'operation_id', 'document_key', 'version'], 'documents_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_documents');
    }
};
