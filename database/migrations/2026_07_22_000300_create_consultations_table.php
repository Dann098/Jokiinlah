<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('request_code', 40)->unique();
            $table->string('name');
            $table->string('email')->index();
            $table->string('phone', 30);
            $table->string('project_title');
            $table->longText('description');
            $table->dateTime('deadline')->nullable()->index();
            $table->string('technology')->nullable();
            $table->decimal('budget', 15, 2)->nullable();
            $table->string('attachment_original_name')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_mime', 150)->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
            $table->string('status', 40)->default('new')->index();
            $table->text('admin_note')->nullable();
            $table->dateTime('privacy_accepted_at');
            $table->string('privacy_policy_version')->nullable();
            $table->string('terms_version')->nullable();
            $table->string('source')->nullable();
            $table->dateTime('archived_at')->nullable()->index();
            $table->dateTime('retention_until')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultations');
    }
};
