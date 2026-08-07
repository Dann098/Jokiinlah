<?php

namespace Tests\Feature\Integration;

use App\Services\WordToPdf\LibreOfficeWordToPdfConverter;
use App\Services\WordToPdf\SymfonyLibreOfficeProcessRunner;
use Tests\Support\CreatesWordDocuments;
use Tests\TestCase;

class LibreOfficeWordToPdfIntegrationTest extends TestCase
{
    use CreatesWordDocuments;

    public function test_real_libreoffice_converts_a_docx_and_cleans_temporary_files(): void
    {
        $binary = (string) config('converter.libreoffice_binary');
        if ($binary === '' || ! is_file($binary)) {
            $this->markTestSkipped('LibreOffice binary tidak tersedia atau belum dikonfigurasi.');
        }

        $converter = new LibreOfficeWordToPdfConverter(new SymfonyLibreOfficeProcessRunner);
        $result = $converter->convert($this->makeDocxUpload());

        try {
            $this->assertFileExists($result->pdfPath);
            $this->assertGreaterThan(5, filesize($result->pdfPath));
            $this->assertSame('%PDF-', file_get_contents($result->pdfPath, false, null, 0, 5));
        } finally {
            $converter->cleanup($result);
        }

        $this->assertDirectoryDoesNotExist($result->workspacePath);
    }
}
