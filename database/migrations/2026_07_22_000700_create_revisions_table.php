<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->longText('description');
            $table->string('section_reference')->nullable();
            $table->string('priority', 20)->default('normal');
            $table->string('status', 40)->default('submitted');
            $table->text('admin_response')->nullable();
            $table->string('attachment_original_name')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_mime', 150)->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('archived_at')->nullable()->index();
            $table->dateTime('retention_until')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['project_id', 'status']);
            $table->index(['priority', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revisions');
    }
};
