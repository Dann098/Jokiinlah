<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FreeDataCleanerTest extends TestCase
{
    use RefreshDatabase;

    public function test_data_cleaner_is_public_and_linked_from_free_tools(): void
    {
        $this->get('/fitur-gratis/pembersih-data')
            ->assertOk()
            ->assertSee('Bersihkan Data CSV & Excel Secara Gratis');

        $this->assertSame('/fitur-gratis/pembersih-data', route('free-tools.data-cleaner', absolute: false));

        $this->get(route('free-tools.index'))
            ->assertOk()
            ->assertSee('Pembersih CSV & Excel Gratis')
            ->assertSee('Gratis')
            ->assertSee('CSV & XLSX')
            ->assertSee('Tanpa Upload Server')
            ->assertSee('Bersihkan Data')
            ->assertSee(route('free-tools.data-cleaner'), false);
    }

    public function test_data_cleaner_exposes_private_accessible_workflow_without_upload_form(): void
    {
        $response = $this->get('/fitur-gratis/pembersih-data')
            ->assertOk()
            ->assertSee('File diproses langsung di perangkat Anda dan tidak dikirim atau disimpan di server Jokiinlah.')
            ->assertSee('Pilih File')
            ->assertSee('Pilih Sheet')
            ->assertSee('Pilih Pembersihan')
            ->assertSee('Periksa Hasil')
            ->assertSee('Unduh Data')
            ->assertSee('Hapus Baris Kosong')
            ->assertSee('Hapus Data Duplikat')
            ->assertSee('Rapikan Spasi pada Teks')
            ->assertSee('Normalisasi Nama Kolom')
            ->assertSee('Hapus Kolom Kosong')
            ->assertSee('Proses Pembersihan')
            ->assertSee('Reset Data')
            ->assertSee('Format Unduhan')
            ->assertSee('Download Hasil')
            ->assertSee('Batasan Fitur')
            ->assertSee('role=\'alert\'', false)
            ->assertSee('aria-live=\'polite\'', false)
            ->assertSee('role=\'tablist\'', false)
            ->assertSee('accept=\'.csv,.xlsx\'', false)
            ->assertDontSee('x-html', false);

        $content = $response->getContent();

        $this->assertSame(1, substr_count($content, '<h1'));
        $this->assertStringNotContainsString('<form', strtolower($content));
        $this->assertStringNotContainsString('type=\'submit\'', strtolower($content));
    }

    public function test_data_cleaner_has_seo_structured_data_and_sitemap_entry(): void
    {
        $content = $this->get('/fitur-gratis/pembersih-data')
            ->assertOk()
            ->assertSee('<title>Pembersih CSV &amp; Excel Gratis | Jokiinlah</title>', false)
            ->assertSee('Bersihkan file CSV dan Excel dari baris kosong, data duplikat, spasi berlebih, dan nama kolom yang tidak konsisten langsung di browser tanpa upload ke server.')
            ->assertSee("<link rel='canonical' href='".url('/fitur-gratis/pembersih-data')."'>", false)
            ->getContent();

        preg_match_all("#<script type='application/ld\\+json'>(.*?)</script>#s", $content, $matches);
        $structuredData = array_map(
            fn (string $json): array => json_decode($json, true, flags: JSON_THROW_ON_ERROR),
            $matches[1],
        );

        $this->assertTrue(collect($structuredData)->contains(
            fn (array $data): bool => ($data['@type'] ?? null) === 'WebApplication'
                && ($data['url'] ?? null) === url('/fitur-gratis/pembersih-data'),
        ));

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertSee(url('/fitur-gratis/pembersih-data'), false);
    }

    public function test_free_tools_have_get_routes_only_and_no_cleaner_submission_endpoint(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (LaravelRoute $route): bool => str_starts_with($route->uri(), 'fitur-gratis'));

        $this->assertCount(3, $routes);
        foreach ($routes as $route) {
            $this->assertSame(['GET', 'HEAD'], $route->methods());
        }
    }

    public function test_data_cleaner_source_never_persists_or_transmits_file_data(): void
    {
        $javascript = file_get_contents(resource_path('js/data-cleaner.js'));

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
