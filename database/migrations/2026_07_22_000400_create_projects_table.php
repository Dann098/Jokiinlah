<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->string('project_code', 40)->unique();
            $table->string('title');
            $table->longText('description');
            $table->string('status', 40)->default('new_request')->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->dateTime('start_date')->nullable();
            $table->dateTime('deadline')->nullable()->index();
            $table->dateTime('completed_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->string('payment_status', 30)->default('unpaid')->index();
            $table->text('payment_note')->nullable();
            $table->dateTime('payment_updated_at')->nullable();
            $table->dateTime('archived_at')->nullable()->index();
            $table->dateTime('retention_until')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['customer_id', 'status']);
            $table->index(['assigned_staff_id', 'status']);
            $table->index(['service_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
