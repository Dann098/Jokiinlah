<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolios', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('category', 50);
            $table->longText('description');
            $table->longText('problem')->nullable();
            $table->longText('solution')->nullable();
            $table->longText('result')->nullable();
            $table->json('technologies')->nullable();
            $table->string('thumbnail')->nullable();
            $table->json('gallery')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->index(['is_published', 'category', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolios');
    }
};
