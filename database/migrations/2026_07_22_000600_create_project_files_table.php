<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('document_uuid');
            $table->unsignedInteger('version')->default(1);
            $table->string('category', 40);
            $table->string('original_name');
            $table->uuid('stored_name');
            $table->string('disk', 30)->default('local');
            $table->string('file_path');
            $table->string('file_type', 150);
            $table->unsignedBigInteger('file_size');
            $table->string('checksum', 64)->nullable();
            $table->text('description')->nullable();
            $table->dateTime('archived_at')->nullable()->index();
            $table->dateTime('retention_until')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['project_id', 'document_uuid', 'version'], 'project_document_version_unique');
            $table->index(['project_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_files');
    }
};
