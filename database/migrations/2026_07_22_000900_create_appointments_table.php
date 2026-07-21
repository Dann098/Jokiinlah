<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->dateTime('appointment_date');
            $table->string('meeting_link')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('scheduled');
            $table->timestamps();
            $table->index(['customer_id', 'appointment_date']);
            $table->index(['staff_id', 'appointment_date']);
            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void { Schema::dropIfExists('appointments'); }
};
