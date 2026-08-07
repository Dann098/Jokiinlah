<?php

namespace Tests\Feature;

use App\Contracts\WordToPdfConverterInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\Fakes\FakeWordToPdfConverter;
use Tests\Support\CreatesWordDocuments;
use Tests\TestCase;

class FreeWordToPdfTest extends TestCase
{
    use CreatesWordDocuments, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearWordToPdfRateLimit();
        $this->app->instance(WordToPdfConverterInterface::class, new FakeWordToPdfConverter);
    }

    public function test_page_is_public_linked_accessible_and_has_expected_seo(): void
    {
        $this->get(route('free-tools.index'))
            ->assertOk()
            ->assertSee('Word ke PDF Gratis')
            ->assertSee('DOC &amp; DOCX', false)
            ->assertSee('Tanpa Login')
            ->assertSee('Convert ke PDF')
            ->assertSee(route('free-tools.word-to-pdf'), false);

        $content = $this->get(route('free-tools.word-to-pdf'))
            ->assertOk()
            ->assertSee('<title>Convert Word ke PDF Gratis | Jokiinlah</title>', false)
            ->assertSee('Convert Word ke PDF Gratis')
            ->assertSee('File digunakan sementara untuk proses konversi dan tidak disimpan secara permanen.')
            ->assertSee('Pilih Dokumen')
            ->assertSee('Download PDF')
            ->assertSee('Batasan Fitur')
            ->assertSee("accept='.doc,.docx'", false)
            ->assertSee("role='alert'", false)
            ->assertSee("aria-live='polite'", false)
            ->assertSee("<link rel='canonical' href='".route('free-tools.word-to-pdf')."'>", false)
            ->getContent();

        $this->assertSame(1, substr_count($content, '<h1'));
        $this->assertStringContainsString('WebApplication', $content);
        $this->get(route('sitemap'))->assertOk()->assertSee(route('free-tools.word-to-pdf'), false);
    }

    public function test_routes_are_public_named_and_conversion_is_protected(): void
    {
        $show = Route::getRoutes()->getByName('free-tools.word-to-pdf');
        $convert = Route::getRoutes()->getByName('free-tools.word-to-pdf.convert');

        $this->assertInstanceOf(LaravelRoute::class, $show);
        $this->assertInstanceOf(LaravelRoute::class, $convert);
        $this->assertSame(['GET', 'HEAD'], $show->methods());
        $this->assertSame(['POST'], $convert->methods());
        $this->assertContains('web', $convert->gatherMiddleware());
        $this->assertContains('throttle:word-to-pdf', $convert->gatherMiddleware());
    }

    public function test_valid_docx_and_doc_are_downloaded_as_pdf(): void
    {
        foreach ([$this->makeDocxUpload(), $this->makeDocUpload()] as $upload) {
            $response = $this->post(route('free-tools.word-to-pdf.convert'), ['document' => $upload]);

            $response->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->assertStringContainsString('attachment;', (string) $response->headers->get('content-disposition'));
            $this->assertStringContainsString('laporan.pdf', (string) $response->headers->get('content-disposition'));
            $this->consumeDownload($response);
        }
    }

    public function test_invalid_empty_and_oversized_files_are_rejected(): void
    {
        config(['converter.word_to_pdf_max_mb' => 1]);

        $invalidFiles = [
            'PDF' => UploadedFile::fake()->createWithContent('dokumen.pdf', '%PDF-1.4'),
            'DOCM' => UploadedFile::fake()->createWithContent('macro.docm', 'not allowed'),
            'EXE' => UploadedFile::fake()->createWithContent('program.exe', 'MZ'),
            'ZIP' => UploadedFile::fake()->createWithContent('arsip.zip', "PK\x03\x04"),
            'renamed ZIP' => $this->makeDisguisedArchiveUpload(),
            'renamed DOCM' => $this->makeDisguisedDocmUpload(),
            'empty DOCX' => UploadedFile::fake()->createWithContent('kosong.docx', ''),
            'oversized DOCX' => UploadedFile::fake()->create('besar.docx', 1025, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ];

        foreach ($invalidFiles as $label => $file) {
            $this->clearWordToPdfRateLimit();
            $response = $this->post(route('free-tools.word-to-pdf.convert'), ['document' => $file]);
            $this->assertTrue(session('errors')?->has('document') ?? false, $label);
        }
    }

    public function test_download_filename_cannot_inject_paths_or_headers(): void
    {
        $response = $this->post(route('free-tools.word-to-pdf.convert'), [
            'document' => $this->makeDocxUpload("../laporan\r\nX-Evil: yes.docx"),
            'binary' => 'malicious-command',
            'output_directory' => '../../public',
        ]);

        $response->assertOk();
        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertStringNotContainsString('..', $disposition);
        $this->assertStringNotContainsString("\r", $disposition);
        $this->assertStringNotContainsString("\n", $disposition);
        $this->assertStringNotContainsString('X-Evil:', $disposition);
        $this->assertStringEndsWith('.pdf"', $disposition);
        $this->consumeDownload($response);
    }

    public function test_streamed_download_removes_the_temporary_workspace(): void
    {
        $root = storage_path('app/private/conversions');
        File::deleteDirectory($root);

        $response = $this->post(route('free-tools.word-to-pdf.convert'), [
            'document' => $this->makeDocxUpload(),
        ]);

        $workspaces = File::directories($root);
        $this->assertCount(1, $workspaces);
        $this->assertStringStartsWith($root.DIRECTORY_SEPARATOR, $workspaces[0]);

        ob_start();
        $response->baseResponse->sendContent();
        ob_end_clean();

        $this->assertSame([], File::directories($root));
    }

    public function test_only_one_conversion_can_run_at_a_time(): void
    {
        $lock = Cache::lock('word-to-pdf:conversion', 70);
        $this->assertTrue($lock->get());

        try {
            $this->from(route('free-tools.word-to-pdf'))
                ->post(route('free-tools.word-to-pdf.convert'), ['document' => $this->makeDocxUpload()])
                ->assertRedirect(route('free-tools.word-to-pdf'))
                ->assertSessionHasErrors([
                    'document' => 'Server sedang memproses dokumen lain. Silakan coba kembali beberapa saat lagi.',
                ]);
        } finally {
            $lock->release();
        }
    }

    public function test_converter_failures_and_timeouts_show_only_safe_messages(): void
    {
        foreach (['failure', 'timeout'] as $outcome) {
            $this->app->instance(WordToPdfConverterInterface::class, new FakeWordToPdfConverter($outcome));

            $this->from(route('free-tools.word-to-pdf'))
                ->post(route('free-tools.word-to-pdf.convert'), ['document' => $this->makeDocxUpload()])
                ->assertRedirect(route('free-tools.word-to-pdf'))
                ->assertSessionHasErrors([
                    'document' => 'Dokumen belum berhasil dikonversi. Pastikan file Word tidak rusak lalu coba kembali.',
                ]);
        }

        $this->app->instance(WordToPdfConverterInterface::class, new FakeWordToPdfConverter('unavailable'));
        $this->from(route('free-tools.word-to-pdf'))
            ->post(route('free-tools.word-to-pdf.convert'), ['document' => $this->makeDocxUpload()])
            ->assertSessionHasErrors([
                'document' => 'Layanan konversi sedang tidak tersedia. Silakan coba kembali nanti.',
            ]);

        $this->app->instance(WordToPdfConverterInterface::class, new FakeWordToPdfConverter('missing'));
        $this->from(route('free-tools.word-to-pdf'))
            ->post(route('free-tools.word-to-pdf.convert'), ['document' => $this->makeDocxUpload()])
            ->assertRedirect(route('free-tools.word-to-pdf'))
            ->assertSessionHasErrors('document');
    }

    public function test_rate_limiter_blocks_the_sixth_conversion_for_an_ip(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = $this->post(route('free-tools.word-to-pdf.convert'), ['document' => $this->makeDocxUpload("laporan-{$attempt}.docx")])
                ->assertOk();
            $this->consumeDownload($response);
        }

        $this->post(route('free-tools.word-to-pdf.convert'), ['document' => $this->makeDocxUpload('laporan-6.docx')])
            ->assertStatus(429)
            ->assertSee('Terlalu banyak proses konversi. Silakan coba kembali beberapa saat lagi.');
    }

    private function clearWordToPdfRateLimit(): void
    {
        $key = 'word-to-pdf:'.hash('sha256', '127.0.0.1');
        RateLimiter::clear(md5('word-to-pdf'.$key));
    }

    private function consumeDownload(TestResponse $response): void
    {
        ob_start();
        $response->baseResponse->sendContent();
        ob_end_clean();
    }
}
