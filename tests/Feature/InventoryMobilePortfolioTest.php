<?php

namespace Tests\Feature;

use App\Models\Portfolio;
use Database\Seeders\PortfolioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryMobilePortfolioTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_mobile_portfolio_is_updated_with_real_project_content(): void
    {
        Portfolio::factory()->create([
            'title' => 'Aplikasi Inventaris Mobile',
            'slug' => 'aplikasi-inventaris-mobile',
            'description' => 'Konten demo lama.',
            'technologies' => ['Laravel', 'MySQL', 'Tailwind CSS'],
            'is_demo' => true,
        ]);
        $unrelatedPortfolio = Portfolio::factory()->create([
            'title' => 'Dashboard Penjualan Kustom',
            'slug' => 'dashboard-monitoring-penjualan',
            'description' => 'Konten yang sudah dikurasi.',
            'technologies' => ['Python'],
        ]);

        $this->seed(PortfolioSeeder::class);

        $portfolio = Portfolio::query()
            ->where('slug', 'aplikasi-inventaris-mobile')
            ->sole();

        $this->assertSame('Aplikasi Inventaris Mobile', $portfolio->title);
        $this->assertSame('Mobile', $portfolio->category);
        $this->assertFalse($portfolio->is_demo);
        $this->assertTrue($portfolio->is_published);
        $this->assertSame(
            ['Flutter', 'Dart', 'SQLite', 'Provider', 'Material 3'],
            $portfolio->technologies,
        );
        $this->assertSame(
            'images/portfolios/aplikasi-inventaris-mobile/01-dashboard-inventaris.png',
            $portfolio->thumbnail,
        );
        $this->assertCount(9, $portfolio->gallery);
        $this->assertNull($portfolio->repository_url);
        $this->assertStringContainsString('pencatatan inventaris secara manual', $portfolio->problem);
        $this->assertStringContainsString('transaksi stok', $portfolio->solution);
        $this->assertStringNotContainsString('%', $portfolio->result);

        $unrelatedPortfolio->refresh();
        $this->assertSame('Dashboard Penjualan Kustom', $unrelatedPortfolio->title);
        $this->assertSame('Konten yang sudah dikurasi.', $unrelatedPortfolio->description);
        $this->assertSame(['Python'], $unrelatedPortfolio->technologies);
    }

    public function test_inventory_mobile_assets_and_content_are_publicly_available(): void
    {
        $this->seed(PortfolioSeeder::class);
        $portfolio = Portfolio::query()
            ->where('slug', 'aplikasi-inventaris-mobile')
            ->sole();

        foreach ([$portfolio->thumbnail, ...$portfolio->gallery] as $path) {
            $this->assertFileExists(public_path($path));
        }

        $this->get(route('portfolios.index'))
            ->assertOk()
            ->assertSee('Aplikasi Inventaris Mobile')
            ->assertSee('Flutter')
            ->assertDontSee('Laravel');

        $this->get(route('portfolios.show', $portfolio))
            ->assertOk()
            ->assertSee('Aplikasi Inventaris Mobile')
            ->assertSee('pencatatan inventaris secara manual')
            ->assertSee('Flutter')
            ->assertSee('SQLite')
            ->assertDontSee('Data Demo')
            ->assertDontSee('Lihat Repository GitHub');
    }
}
