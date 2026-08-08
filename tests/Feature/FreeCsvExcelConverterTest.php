<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FreeCsvExcelConverterTest extends TestCase
{
    use RefreshDatabase;

    public function test_converter_is_public_and_linked_from_free_tools(): void
    {
        $this->get('/fitur-gratis/konverter-csv-excel')
            ->assertOk()
            ->assertSee('Konversi CSV ke Excel & Excel ke CSV');

        $this->assertSame(
            '/fitur-gratis/konverter-csv-excel',
            route('free-tools.csv-excel-converter', absolute: false),
        );

        $this->get(route('free-tools.index'))
            ->assertOk()
            ->assertSee('Konverter CSV & Excel Gratis')
            ->assertSee('CSV ↔ XLSX')
            ->assertSee('Diproses di Browser')
            ->assertSee(route('free-tools.csv-excel-converter'), false);
    }

    public function test_converter_exposes_private_accessible_workflow_without_upload_form(): void
    {
        $response = $this->get('/fitur-gratis/konverter-csv-excel')
            ->assertOk()
            ->assertSee('File diproses langsung di perangkat Anda dan tidak dikirim atau disimpan di server Jokiinlah.')
            ->assertSee('1. Pilih File')
            ->assertSee('2. Periksa Data')
            ->assertSee('3. Konversi & Download')
            ->assertSee('Pilih Sheet yang akan dikonversi')
            ->assertSee('Formula Excel tidak dihitung ulang oleh fitur ini.')
            ->assertSee('Reset Data')
            ->assertSee('Batasan Fitur')
            ->assertSee("role='alert'", false)
            ->assertSee("aria-live='polite'", false)
            ->assertSee("accept='.csv,.xlsx'", false)
            ->assertDontSee('x-html', false);

        $content = $response->getContent();

        $this->assertSame(1, substr_count($content, '<h1'));
        $this->assertStringNotContainsString('<form', strtolower($content));
        $this->assertStringNotContainsString("type='submit'", strtolower($content));
    }

    public function test_converter_has_seo_structured_data_and_sitemap_entry(): void
    {
        $content = $this->get('/fitur-gratis/konverter-csv-excel')
            ->assertOk()
            ->assertSee('<title>Konverter CSV ke Excel &amp; Excel ke CSV Gratis | Jokiinlah</title>', false)
            ->assertSee('Konversi CSV ke Excel XLSX atau Excel ke CSV secara gratis langsung di browser tanpa upload file ke server.')
            ->assertSee("<link rel='canonical' href='".url('/fitur-gratis/konverter-csv-excel')."'>", false)
            ->assertSee("property='og:title'", false)
            ->getContent();

        preg_match_all("#<script type='application/ld\\+json'>(.*?)</script>#s", $content, $matches);
        $structuredData = array_map(
            fn (string $json): array => json_decode($json, true, flags: JSON_THROW_ON_ERROR),
            $matches[1],
        );

        $this->assertTrue(collect($structuredData)->contains(
            fn (array $data): bool => ($data['@type'] ?? null) === 'WebApplication'
                && ($data['url'] ?? null) === url('/fitur-gratis/konverter-csv-excel'),
        ));

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertSee(url('/fitur-gratis/konverter-csv-excel'), false);
    }

    public function test_converter_route_is_get_only(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn (LaravelRoute $route): bool => $route->uri() === 'fitur-gratis/konverter-csv-excel');

        $this->assertNotNull($route);
        $this->assertSame(['GET', 'HEAD'], $route->methods());
    }

    public function test_converter_source_never_persists_or_transmits_file_data(): void
    {
        $javascript = file_get_contents(resource_path('js/csv-excel-converter.js'));

        $this->assertIsString($javascript);
        $this->assertStringContainsString("from 'papaparse'", $javascript);
        $this->assertStringContainsString("from 'xlsx'", $javascript);
        $this->assertStringNotContainsString('fetch(', $javascript);
        $this->assertStringNotContainsString('axios', strtolower($javascript));
        $this->assertStringNotContainsString('localstorage', strtolower($javascript));
        $this->assertStringNotContainsString('sessionstorage', strtolower($javascript));
        $this->assertStringNotContainsString('indexeddb', strtolower($javascript));
        $this->assertStringNotContainsString('navigator.sendbeacon', strtolower($javascript));
    }
}
