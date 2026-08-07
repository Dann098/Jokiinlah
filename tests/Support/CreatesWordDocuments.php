<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use ZipArchive;

trait CreatesWordDocuments
{
    protected function makeDocxUpload(string $name = 'laporan.docx'): UploadedFile
    {
        $path = tempnam(storage_path('framework/testing'), 'docx-');

        if ($path === false) {
            throw new RuntimeException('Tidak dapat membuat fixture DOCX.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak dapat membuka fixture DOCX.');
        }

        $zip->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML);
        $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML);
        $zip->addFromString('word/document.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:pPr><w:pStyle w:val="Title"/></w:pPr><w:r><w:t>Laporan Uji Jokiinlah</w:t></w:r></w:p>
    <w:p><w:r><w:t>Dokumen sementara untuk pengujian konversi.</w:t></w:r></w:p>
    <w:tbl><w:tr><w:tc><w:p><w:r><w:t>Kolom A</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Kolom B</w:t></w:r></w:p></w:tc></w:tr></w:tbl>
    <w:sectPr/>
  </w:body>
</w:document>
XML);
        $zip->close();

        return new UploadedFile(
            $path,
            $name,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true,
        );
    }

    protected function makeDocUpload(string $name = 'laporan.doc'): UploadedFile
    {
        return $this->makeCompoundUpload($name, ['WordDocument']);
    }

    protected function makeDisguisedOleUpload(string $name = 'workbook-palsu.doc'): UploadedFile
    {
        return $this->makeCompoundUpload($name, ['Workbook']);
    }

    protected function makeMacroDocUpload(string $name = 'macro-palsu.doc'): UploadedFile
    {
        return $this->makeCompoundUpload($name, ['WordDocument', 'VBA']);
    }

    protected function makeForgedWordCompoundUpload(string $name = 'word-palsu.doc'): UploadedFile
    {
        return $this->makeCompoundUpload($name, ['WordDocument'], false);
    }

    /** @param list<string> $streamNames */
    private function makeCompoundUpload(string $name, array $streamNames, bool $validWordFib = true): UploadedFile
    {
        $path = tempnam(storage_path('framework/testing'), 'doc-');

        if ($path === false) {
            throw new RuntimeException('Tidak dapat membuat fixture DOC.');
        }

        $header = str_repeat("\0", 512);
        $header = substr_replace($header, hex2bin('D0CF11E0A1B11AE1'), 0, 8);
        $header = substr_replace($header, hex2bin('3E000300FEFF09000600'), 24, 10);
        $header = substr_replace($header, pack('V', 1), 44, 4);
        $header = substr_replace($header, pack('V', 0), 48, 4);
        $header = substr_replace($header, pack('V', 4096), 56, 4);
        $header = substr_replace($header, pack('V', 0xFFFFFFFE), 60, 4);
        $header = substr_replace($header, pack('V', 0xFFFFFFFE), 68, 4);
        $header = substr_replace($header, str_repeat(pack('V', 0xFFFFFFFF), 109), 76, 436);
        $header = substr_replace($header, pack('V', 1), 76, 4);

        $directory = $this->compoundDirectoryEntry('Root Entry', 5);
        foreach ($streamNames as $streamName) {
            $isWordDocument = $streamName === 'WordDocument';
            $directory .= $this->compoundDirectoryEntry($streamName, 2, $isWordDocument ? 2 : 0xFFFFFFFE, $isWordDocument ? 4096 : 0);
        }
        $directory = str_pad(substr($directory, 0, 512), 512, "\0");

        $fat = pack('V', 0xFFFFFFFE).pack('V', 0xFFFFFFFD);
        for ($sector = 2; $sector <= 9; $sector++) {
            $fat .= pack('V', $sector === 9 ? 0xFFFFFFFE : $sector + 1);
        }
        $fat = str_pad($fat, 512, "\xFF");
        $wordStream = str_repeat("\0", 4096);
        if ($validWordFib) {
            $wordStream = substr_replace($wordStream, pack('v', 0xA5EC), 0, 2);
            $wordStream = substr_replace($wordStream, pack('v', 0x00D9), 2, 2);
        }
        file_put_contents($path, $header.$directory.$fat.$wordStream);

        return new UploadedFile($path, $name, 'application/msword', null, true);
    }

    private function compoundDirectoryEntry(string $name, int $type, int $startSector = 0xFFFFFFFE, int $size = 0): string
    {
        $encodedName = mb_convert_encoding($name."\0", 'UTF-16LE', 'UTF-8');
        $entry = str_pad(substr($encodedName, 0, 64), 128, "\0");
        $entry = substr_replace($entry, pack('v', min(strlen($encodedName), 64)), 64, 2);
        $entry = substr_replace($entry, chr($type), 66, 1);
        $entry = substr_replace($entry, pack('V', 0xFFFFFFFF), 68, 4);
        $entry = substr_replace($entry, pack('V', 0xFFFFFFFF), 72, 4);
        $entry = substr_replace($entry, pack('V', 0xFFFFFFFF), 76, 4);
        $entry = substr_replace($entry, pack('V', $startSector), 116, 4);
        $entry = substr_replace($entry, pack('V2', $size, 0), 120, 8);

        return $entry;
    }

    protected function makeDisguisedArchiveUpload(string $name = 'arsip-palsu.docx'): UploadedFile
    {
        return $this->makeZipUpload($name, [
            '[Content_Types].xml' => '<Types><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/></Types>',
            'xl/workbook.xml' => '<workbook/>',
        ]);
    }

    protected function makeDisguisedDocmUpload(string $name = 'macro-disamarkan.docx'): UploadedFile
    {
        return $this->makeZipUpload($name, [
            '[Content_Types].xml' => '<Types><Override PartName="/word/document.xml" ContentType="application/vnd.ms-word.document.macroEnabled.main+xml"/></Types>',
            'word/document.xml' => '<w:document/>',
            'word/vbaProject.bin' => 'macro',
        ]);
    }

    protected function makeExternalRelationshipDocxUpload(string $name = 'relasi-eksternal.docx'): UploadedFile
    {
        return $this->makeZipUpload($name, [
            '[Content_Types].xml' => '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
            '_rels/.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="https://attacker.invalid/document.xml" TargetMode="External"/></Relationships>',
            'word/document.xml' => '<not-word/>',
        ]);
    }

    /** @param array<string, string> $entries */
    private function makeZipUpload(string $name, array $entries): UploadedFile
    {
        $path = tempnam(storage_path('framework/testing'), 'zip-');
        if ($path === false) {
            throw new RuntimeException('Tidak dapat membuat fixture ZIP.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak dapat membuka fixture ZIP.');
        }

        foreach ($entries as $entry => $contents) {
            $zip->addFromString($entry, $contents);
        }
        $zip->close();

        return new UploadedFile($path, $name, 'application/zip', null, true);
    }
}
