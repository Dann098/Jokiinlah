<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
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

    public function test_cv_builder_routes_remain_read_only_when_server_converter_is_added(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (LaravelRoute $route): bool => str_starts_with($route->uri(), 'fitur-gratis'));

        $this->assertSame(['GET', 'HEAD'], $routes->firstWhere('uri', 'fitur-gratis')->methods());
        $this->assertSame(['GET', 'HEAD'], $routes->firstWhere('uri', 'fitur-gratis/pembuat-cv')->methods());
        $this->assertSame(['GET', 'HEAD'], $routes->firstWhere('uri', 'fitur-gratis/pembersih-data')->methods());
    }

    public function test_cv_builder_exposes_accessible_fields_repeaters_and_local_photo_rules(): void
    {
        $content = $this->get(route('free-tools.cv-builder'))
            ->assertOk()
            ->assertSee('Informasi Pribadi')
            ->assertSee('Ringkasan Profesional')
            ->assertSee('Tambah Pengalaman')
            ->assertSee('Tambah Pendidikan')
            ->assertSee('Tambah Proyek')
            ->assertSee('Tambah Sertifikasi')
            ->assertSee('Tambah Kategori')
            ->assertSee('Gunakan Foto')
            ->assertSee("accept='.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp'", false)
            ->assertSee("maxlength='900'", false)
            ->assertSee("maxlength='250'", false)
            ->assertSee("role='tablist'", false)
            ->assertSee('x-bind:aria-selected', false)
            ->assertSee("aria-live='polite'", false)
            ->assertSee('otomatis kedaluwarsa setelah 30 hari')
            ->assertSee('Reset')
            ->getContent();

        $this->assertStringNotContainsString('.svg', strtolower($content));
        $this->assertStringNotContainsString('type=\'submit\'', strtolower($content));
    }

    public function test_cv_builder_assets_are_print_ready_and_make_no_cv_network_request(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $javascript = file_get_contents(resource_path('js/cv-builder.js'));

        $this->assertIsString($css);
        $this->assertStringContainsString('@page { size: 210mm 297mm; margin: 12mm; }', $css);
        $this->assertStringContainsString('@media print', $css);
        $this->assertStringContainsString('break-inside: avoid', $css);
        $this->assertStringContainsString('page-break-inside: avoid', $css);
        $this->assertStringContainsString('orphans: 2', $css);
        $this->assertStringContainsString('widows: 2', $css);
        $this->assertStringContainsString('.cv-document-section--skills', $css);
        $this->assertStringContainsString('min-width: max-content', $css);
        $this->assertStringContainsString('overflow-wrap: normal', $css);
        $this->assertMatchesRegularExpression('/\.cv-document-date--freeform\s*\{[^}]*min-width:\s*5ch/s', $css);
        $this->assertStringContainsString('aspect-ratio: 3 / 4', $css);
        $this->assertStringContainsString('align-self: start', $css);
        $this->assertStringContainsString("font-family: Arial, Helvetica, 'Liberation Sans'", $css);

        $this->assertIsString($javascript);
        $this->assertStringContainsString('window.localStorage', $javascript);
        $this->assertStringContainsString("new Set(['image/jpeg', 'image/png', 'image/webp'])", $javascript);
        $this->assertStringNotContainsString('fetch(', $javascript);
        $this->assertStringNotContainsString('axios', strtolower($javascript));
        $this->assertStringNotContainsString('photoPreview:', substr($javascript, strpos($javascript, 'persistedData()'), 700));
    }
}
