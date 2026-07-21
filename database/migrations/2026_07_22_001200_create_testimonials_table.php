<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name');
            $table->string('customer_role')->nullable();
            $table->text('content');
            $table->unsignedTinyInteger('rating');
            $table->string('photo')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->index(['is_published', 'is_demo']);
        });
    }

    public function down(): void { Schema::dropIfExists('testimonials'); }
};
