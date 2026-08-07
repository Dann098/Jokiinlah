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
        $path = tempnam(storage_path('framework/testing'), 'doc-');

        if ($path === false) {
            throw new RuntimeException('Tidak dapat membuat fixture DOC.');
        }

        file_put_contents($path, hex2bin('D0CF11E0A1B11AE1').str_repeat("\0", 2048));

        return new UploadedFile($path, $name, 'application/msword', null, true);
    }
}
