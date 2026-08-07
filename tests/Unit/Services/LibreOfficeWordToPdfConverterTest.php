<?php

namespace Tests\Unit\Services;

use App\Exceptions\WordToPdfConversionFailed;
use App\Exceptions\WordToPdfConversionTimedOut;
use App\Exceptions\WordToPdfConverterUnavailable;
use App\Services\WordToPdf\LibreOfficeWordToPdfConverter;
use App\ValueObjects\LibreOfficeProcessResult;
use Illuminate\Support\Facades\File;
use Tests\Fakes\FakeLibreOfficeProcessRunner;
use Tests\Support\CreatesWordDocuments;
use Tests\TestCase;

class LibreOfficeWordToPdfConverterTest extends TestCase
{
    use CreatesWordDocuments;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = storage_path('framework/testing/word-to-pdf-'.bin2hex(random_bytes(6)));
        config([
            'converter.libreoffice_binary' => PHP_BINARY,
            'converter.word_to_pdf_timeout' => 17,
            'converter.temporary_directory' => $this->root,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_it_uses_array_arguments_private_uuid_paths_timeout_and_pdf_signature(): void
    {
        $captured = null;
        $runner = new FakeLibreOfficeProcessRunner(function (array $command, array $environment, int $timeout) use (&$captured): LibreOfficeProcessResult {
            $captured = compact('command', 'environment', 'timeout');
            $inputPath = $command[array_key_last($command)];
            $outDirectory = $command[array_search('--outdir', $command, true) + 1];
            $outputPath = $outDirectory.DIRECTORY_SEPARATOR.pathinfo($inputPath, PATHINFO_FILENAME).'.pdf';
            file_put_contents($outputPath, "%PDF-1.7\nvalid\n%%EOF");

            return new LibreOfficeProcessResult(0);
        });

        $converter = new LibreOfficeWordToPdfConverter($runner);
        $result = $converter->convert($this->makeDocxUpload('nama pengguna.docx'));

        $this->assertIsArray($captured['command']);
        $this->assertSame(PHP_BINARY, $captured['command'][0]);
        $this->assertContains('--headless', $captured['command']);
        $this->assertContains('--convert-to', $captured['command']);
        $this->assertSame(17, $captured['timeout']);
        $this->assertStringStartsWith($this->root.DIRECTORY_SEPARATOR, $result->workspacePath);
        $this->assertStringStartsWith($result->workspacePath.DIRECTORY_SEPARATOR, $result->pdfPath);
        $this->assertSame('%PDF-', file_get_contents($result->pdfPath, false, null, 0, 5));

        $inputPath = $captured['command'][array_key_last($captured['command'])];
        $this->assertStringNotContainsString('nama pengguna', $inputPath);
        $this->assertMatchesRegularExpression('/[0-9a-f-]{36}\.docx$/i', $inputPath);
        $this->assertFileDoesNotExist($inputPath);
        $this->assertStringContainsString('-env:UserInstallation=file:///', implode(' ', $captured['command']));
        $this->assertArrayHasKey('HOME', $captured['environment']);
        $this->assertArrayHasKey('TMP', $captured['environment']);
        $this->assertArrayHasKey('TEMP', $captured['environment']);

        $converter->cleanup($result);
        $this->assertDirectoryDoesNotExist($result->workspacePath);
    }

    public function test_it_cleans_every_workspace_when_process_or_output_fails(): void
    {
        $behaviors = [
            fn (): LibreOfficeProcessResult => new LibreOfficeProcessResult(1),
            fn (): LibreOfficeProcessResult => new LibreOfficeProcessResult(0),
            function (array $command): LibreOfficeProcessResult {
                $input = $command[array_key_last($command)];
                $out = $command[array_search('--outdir', $command, true) + 1];
                file_put_contents($out.DIRECTORY_SEPARATOR.pathinfo($input, PATHINFO_FILENAME).'.pdf', '');

                return new LibreOfficeProcessResult(0);
            },
            function (array $command): LibreOfficeProcessResult {
                $input = $command[array_key_last($command)];
                $out = $command[array_search('--outdir', $command, true) + 1];
                file_put_contents($out.DIRECTORY_SEPARATOR.pathinfo($input, PATHINFO_FILENAME).'.pdf', 'not a pdf');

                return new LibreOfficeProcessResult(0);
            },
        ];

        foreach ($behaviors as $behavior) {
            File::deleteDirectory($this->root);
            $runner = new FakeLibreOfficeProcessRunner(fn (array $command, array $environment, int $timeout): LibreOfficeProcessResult => $behavior($command, $environment, $timeout));
            $converter = new LibreOfficeWordToPdfConverter($runner);

            try {
                $converter->convert($this->makeDocxUpload());
                $this->fail('Konversi tidak valid seharusnya gagal.');
            } catch (WordToPdfConversionFailed) {
                $this->assertSame([], File::directories($this->root));
            }
        }
    }

    public function test_it_maps_timeout_and_unavailable_binary_without_leaking_details(): void
    {
        $runner = new FakeLibreOfficeProcessRunner(fn (): never => throw new WordToPdfConversionTimedOut);
        $converter = new LibreOfficeWordToPdfConverter($runner);

        try {
            $converter->convert($this->makeDocxUpload());
            $this->fail('Timeout seharusnya dilempar.');
        } catch (WordToPdfConversionTimedOut $exception) {
            $this->assertSame('Konversi melewati batas waktu.', $exception->getMessage());
            $this->assertSame([], File::directories($this->root));
        }

        config(['converter.libreoffice_binary' => '']);
        $this->expectException(WordToPdfConverterUnavailable::class);
        $converter->convert($this->makeDocxUpload());
    }
}
