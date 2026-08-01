<?php

namespace Tests\Feature;

use Database\Seeders\PortfolioSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PortfolioSchemaRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_repair_restores_seeded_public_portfolios_after_schema_drift(): void
    {
        if (Schema::hasIndex('portfolios', 'portfolios_published_demo')) {
            Schema::table('portfolios', function (Blueprint $table): void {
                $table->dropIndex('portfolios_published_demo');
            });
        }

        Schema::table('portfolios', function (Blueprint $table): void {
            $table->dropColumn(['is_demo', 'repository_url']);
        });

        $migrationPath = database_path('migrations/2026_08_02_000100_repair_portfolio_operational_columns.php');

        $this->assertFileExists($migrationPath);

        $migration = require $migrationPath;
        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasColumn('portfolios', 'is_demo'));
        $this->assertTrue(Schema::hasColumn('portfolios', 'repository_url'));
        $this->assertTrue(Schema::hasIndex('portfolios', 'portfolios_published_demo'));

        $this->seed(PortfolioSeeder::class);

        $this->get(route('portfolios.index', ['q' => '', 'category' => '']))
            ->assertOk()
            ->assertSee('Analisis Survei Kepuasan')
            ->assertDontSee('Studi kasus tidak ditemukan');
    }
}
