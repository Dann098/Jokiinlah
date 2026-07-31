<?php

namespace Tests\Feature;

use App\Models\Portfolio;
use Database\Seeders\PortfolioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SatisfactionSurveyPortfolioTest extends TestCase
{
    use RefreshDatabase;

    public function test_satisfaction_survey_portfolio_is_published_with_real_project_content(): void
    {
        $customPortfolio = Portfolio::factory()->create([
            'title' => 'Dashboard Penjualan Kustom',
            'slug' => 'dashboard-monitoring-penjualan',
            'description' => 'Konten yang sudah dikurasi.',
            'technologies' => ['Python'],
            'thumbnail' => 'images/portfolios/custom-thumbnail.png',
            'gallery' => ['images/portfolios/custom-gallery.png'],
            'repository_url' => 'https://github.com/Dann098/custom-dashboard',
            'is_demo' => true,
        ]);

        $this->seed(PortfolioSeeder::class);

        $portfolio = Portfolio::query()
            ->where('slug', 'analisis-survei-kepuasan')
            ->sole();

        $this->assertSame('Analisis Survei Kepuasan', $portfolio->title);
        $this->assertSame('Analisis Data', $portfolio->category);
        $this->assertFalse($portfolio->is_demo);
        $this->assertTrue($portfolio->is_published);
        $this->assertSame(
            ['Python', 'Pandas', 'Streamlit', 'Plotly', 'OpenPyXL'],
            $portfolio->technologies,
        );
        $this->assertSame(
            'images/portfolios/analisis-survei-kepuasan/01-ringkasan-dashboard.png',
            $portfolio->thumbnail,
        );
        $this->assertCount(8, $portfolio->gallery);
        $this->assertSame(
            'https://github.com/Dann098/analisis-survei-kepuasan',
            $portfolio->repository_url,
        );
        $this->assertStringContainsString('spreadsheet', $portfolio->problem);
        $this->assertStringContainsString('Net Promoter Score', $portfolio->solution);
        $this->assertStringNotContainsString('%', $portfolio->result);

        $customPortfolio->refresh();
        $this->assertSame('Dashboard Penjualan Kustom', $customPortfolio->title);
        $this->assertSame('Konten yang sudah dikurasi.', $customPortfolio->description);
        $this->assertSame(['Python'], $customPortfolio->technologies);
        $this->assertSame('images/portfolios/custom-thumbnail.png', $customPortfolio->thumbnail);
        $this->assertSame(['images/portfolios/custom-gallery.png'], $customPortfolio->gallery);
        $this->assertSame(
            'https://github.com/Dann098/custom-dashboard',
            $customPortfolio->repository_url,
        );
        $this->assertTrue($customPortfolio->is_demo);
    }

    public function test_satisfaction_survey_assets_and_repository_link_are_publicly_available(): void
    {
        $this->seed(PortfolioSeeder::class);
        $portfolio = Portfolio::query()
            ->where('slug', 'analisis-survei-kepuasan')
            ->sole();

        foreach ([$portfolio->thumbnail, ...$portfolio->gallery] as $path) {
            $this->assertFileExists(public_path($path));
        }

        $this->get(route('portfolios.show', $portfolio))
            ->assertOk()
            ->assertSee('Analisis Survei Kepuasan')
            ->assertSee('https://github.com/Dann098/analisis-survei-kepuasan', false)
            ->assertSee('Lihat Repository GitHub')
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertDontSee('Data Demo');
    }

    public function test_unsafe_repository_url_is_never_rendered(): void
    {
        $portfolio = Portfolio::factory()->create([
            'repository_url' => 'javascript:alert(1)',
        ]);

        $this->get(route('portfolios.show', $portfolio))
            ->assertOk()
            ->assertDontSee('javascript:', false)
            ->assertDontSee('Lihat Repository GitHub');
    }

    public function test_non_repository_github_url_is_never_rendered(): void
    {
        $portfolio = Portfolio::factory()->create([
            'repository_url' => 'https://github.com/orgs/acme',
        ]);

        $this->get(route('portfolios.show', $portfolio))
            ->assertOk()
            ->assertDontSee('https://github.com/orgs/acme', false)
            ->assertDontSee('Lihat Repository GitHub');
    }
}
