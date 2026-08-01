<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('portfolios')) {
            throw new RuntimeException('Portfolio schema repair requires the portfolios table.');
        }

        if (! Schema::hasColumn('portfolios', 'is_demo')) {
            Schema::table('portfolios', function (Blueprint $table): void {
                $table->boolean('is_demo')->default(false);
            });
        }

        if (! Schema::hasColumn('portfolios', 'repository_url')) {
            Schema::table('portfolios', function (Blueprint $table): void {
                $table->string('repository_url', 2048)->nullable();
            });
        }

        if (! Schema::hasIndex('portfolios', 'portfolios_published_demo')) {
            Schema::table('portfolios', function (Blueprint $table): void {
                $table->index(['is_published', 'is_demo'], 'portfolios_published_demo');
            });
        }
    }

    public function down(): void
    {
        // Forward-only repair: these columns may belong to earlier migrations.
    }
};
