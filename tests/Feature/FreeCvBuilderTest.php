<?php

namespace Tests\Feature;

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FreeCvBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_tools_index_is_public_and_links_to_the_cv_builder(): void
    {
        $this->get(route('free-tools.index'))
            ->assertOk()
            ->assertSee('Fitur Gratis')
            ->assertSee('Pembuat CV ATS Gratis')
            ->assertSee('Tanpa login')
            ->assertSee('Data tidak disimpan di server')
            ->assertSee(route('free-tools.cv-builder'), false);
    }

    public function test_cv_builder_is_public_private_by_design_and_print_ready(): void
    {
        $response = $this->get(route('free-tools.cv-builder'))
            ->assertOk()
            ->assertSee('Buat CV Profesional Secara Gratis')
            ->assertSee('Academic Classic')
            ->assertSee('Data CV diproses di perangkat Anda dan tidak dikirim ke server.')
            ->assertSee('Foto perlu dipilih kembali setelah halaman ditutup atau dimuat ulang.')
            ->assertSee('Muat Data Contoh')
            ->assertSee('Hapus Semua Data')
            ->assertSee('Cetak / Simpan PDF')
            ->assertSee('window.print()', false)
            ->assertSee('jokiinlah_cv_academic_classic_v1', false)
            ->assertSee('x-data="cvBuilder"', false)
            ->assertDontSee('x-html', false)
            ->assertDontSee('Template Harvard Resmi');

        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
        $this->assertStringNotContainsString('<form', strtolower($response->getContent()));
    }

    public function test_navigation_seo_structured_data_and_sitemap_include_free_tools(): void
    {
        $home = $this->get(route('home'))->assertOk();
        $home->assertSee('Fitur Gratis')->assertSee(route('free-tools.index'), false);

        $builderContent = $this->get(route('free-tools.cv-builder'))
            ->assertOk()
            ->assertSee('<title>Pembuat CV ATS Gratis | Jokiinlah</title>', false)
            ->assertSee("<meta name='description'", false)
            ->assertSee("<link rel='canonical' href='".route('free-tools.cv-builder')."'>", false)
            ->getContent();

        preg_match_all("#<script type='application/ld\\+json'>(.*?)</script>#s", $builderContent, $matches);
        $structuredData = array_map(
            fn (string $json): array => json_decode($json, true, flags: JSON_THROW_ON_ERROR),
            $matches[1],
        );

        $this->assertNotEmpty($structuredData);
        $this->assertTrue(collect($structuredData)->contains(fn (array $data): bool => ($data['@type'] ?? null) === 'WebApplication'));

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertSee(route('free-tools.index'), false)
            ->assertSee(route('free-tools.cv-builder'), false);
    }

    public function test_cv_builder_has_get_routes_only_and_no_server_submission_endpoint(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (LaravelRoute $route): bool => str_starts_with($route->uri(), 'fitur-gratis'));

        $this->assertCount(2, $routes);
        foreach ($routes as $route) {
            $this->assertSame(['GET', 'HEAD'], $route->methods());
        }
    }
}
