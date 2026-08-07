<?php

namespace App\Rules;

use Closure;
use DOMDocument;
use DOMXPath;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use ZipArchive;

final class ValidWordDocument implements ValidationRule
{
    private const DOC_MIME_TYPES = [
        'application/msword',
        'application/vnd.ms-word',
        'application/x-ole-storage',
        'application/cdfv2',
        'application/octet-stream',
    ];

    private const DOCX_MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip',
        'application/x-zip',
        'application/x-zip-compressed',
        'application/octet-stream',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid() || $value->getSize() < 1) {
            $fail('Dokumen Word tidak valid atau kosong.');

            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());
        $mimeType = strtolower((string) $value->getMimeType());
        $header = $this->header($value, 8);

        $valid = match ($extension) {
            'doc' => in_array($mimeType, self::DOC_MIME_TYPES, true)
                && $this->isValidCompoundDocument($value),
            'docx' => in_array($mimeType, self::DOCX_MIME_TYPES, true)
                && str_starts_with($header, "PK\x03\x04")
                && $this->isValidDocxPackage($value),
            default => false,
        };

        if (! $valid) {
            $fail('Hanya dokumen DOC atau DOCX yang valid yang dapat dikonversi.');
        }
    }

    private function isValidCompoundDocument(UploadedFile $file): bool
    {
        $header = $this->header($file, 512);

        if (strlen($header) !== 512 || substr($header, 0, 8) !== hex2bin('D0CF11E0A1B11AE1')) {
            return false;
        }

        $majorVersion = unpack('v', substr($header, 26, 2))[1] ?? 0;
        $sectorShift = unpack('v', substr($header, 30, 2))[1] ?? 0;
        $miniSectorShift = unpack('v', substr($header, 32, 2))[1] ?? 0;

        $validHeader = substr($header, 28, 2) === "\xFE\xFF"
            && in_array($majorVersion, [3, 4], true)
            && in_array($sectorShift, [9, 12], true)
            && $miniSectorShift === 6
            && substr($header, 34, 6) === str_repeat("\0", 6);

        if (! $validHeader) {
            return false;
        }

        $directoryNames = $this->compoundDirectoryNames($file, $header, 1 << $sectorShift);
        if ($directoryNames === null || ! in_array('worddocument', $directoryNames, true)) {
            return false;
        }

        return array_intersect(
            ['vba', 'macros', '_vba_project', '_vba_project_cur', 'encryptedpackage'],
            $directoryNames,
        ) === [];
    }

    /** @return list<string>|null */
    private function compoundDirectoryNames(UploadedFile $file, string $header, int $sectorSize): ?array
    {
        $path = $file->getRealPath();
        $fileSize = $file->getSize();
        $fatSectorCount = $this->littleEndianUnsigned(substr($header, 44, 4));
        $directorySector = $this->littleEndianUnsigned(substr($header, 48, 4));
        $difatSectorCount = $this->littleEndianUnsigned(substr($header, 72, 4));

        $maximumSectors = intdiv(max(0, $fileSize - $sectorSize), $sectorSize);
        if ($path === false || $fileSize < ($sectorSize * 2) || $fatSectorCount < 1 || $fatSectorCount > $maximumSectors || $difatSectorCount > 32) {
            return null;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        try {
            $fatSectors = [];
            for ($index = 0; $index < 109 && count($fatSectors) < $fatSectorCount; $index++) {
                $sector = $this->littleEndianUnsigned(substr($header, 76 + ($index * 4), 4));
                if ($sector < 0xFFFFFFF0) {
                    $fatSectors[] = $sector;
                }
            }

            $difatSector = $this->littleEndianUnsigned(substr($header, 68, 4));
            $seenDifat = [];
            for ($chain = 0; count($fatSectors) < $fatSectorCount && $chain < $difatSectorCount; $chain++) {
                if ($difatSector >= 0xFFFFFFF0 || isset($seenDifat[$difatSector])) {
                    return null;
                }

                $seenDifat[$difatSector] = true;
                $contents = $this->compoundSector($handle, $difatSector, $sectorSize, $fileSize);
                if ($contents === null) {
                    return null;
                }

                $entriesPerSector = intdiv($sectorSize, 4) - 1;
                for ($index = 0; $index < $entriesPerSector && count($fatSectors) < $fatSectorCount; $index++) {
                    $sector = $this->littleEndianUnsigned(substr($contents, $index * 4, 4));
                    if ($sector < 0xFFFFFFF0) {
                        $fatSectors[] = $sector;
                    }
                }
                $difatSector = $this->littleEndianUnsigned(substr($contents, -4));
            }

            if (count($fatSectors) !== $fatSectorCount) {
                return null;
            }

            $fat = [];
            foreach ($fatSectors as $fatSector) {
                $contents = $this->compoundSector($handle, $fatSector, $sectorSize, $fileSize);
                if ($contents === null) {
                    return null;
                }

                foreach (str_split($contents, 4) as $entry) {
                    $fat[] = $this->littleEndianUnsigned($entry);
                }
            }

            $directory = '';
            $seen = [];
            while ($directorySector !== 0xFFFFFFFE) {
                if (isset($seen[$directorySector]) || count($seen) >= 2048) {
                    return null;
                }

                $seen[$directorySector] = true;
                $sector = $this->compoundSector($handle, $directorySector, $sectorSize, $fileSize);
                if ($sector === null || ! isset($fat[$directorySector])) {
                    return null;
                }

                $directory .= $sector;
                $directorySector = $fat[$directorySector];
            }

            $entries = $this->compoundEntries($directory);
            $wordDocument = collect($entries)->firstWhere('name', 'worddocument');
            if (! is_array($wordDocument)) {
                return null;
            }

            $fib = $this->compoundStreamPrefix($handle, $wordDocument, $fat, $sectorSize, $fileSize, 32);
            if ($fib === null || ! $this->isValidWordFib($fib)) {
                return null;
            }

            return array_values(array_unique(array_column($entries, 'name')));
        } finally {
            fclose($handle);
        }
    }

    /** @param resource $handle */
    private function compoundSector($handle, int $sector, int $sectorSize, int $fileSize): ?string
    {
        if ($sector >= 0xFFFFFFF0) {
            return null;
        }

        $offset = $sectorSize + ($sector * $sectorSize);
        if ($offset < $sectorSize || ($offset + $sectorSize) > $fileSize || fseek($handle, $offset) !== 0) {
            return null;
        }

        $contents = fread($handle, $sectorSize);

        return is_string($contents) && strlen($contents) === $sectorSize ? $contents : null;
    }

    /** @return list<array{name: string, start: int, size: int}> */
    private function compoundEntries(string $directory): array
    {
        $entries = [];

        foreach (str_split($directory, 128) as $entry) {
            if (strlen($entry) !== 128 || ! in_array(ord($entry[66]), [1, 2, 5], true)) {
                continue;
            }

            $nameLength = unpack('vvalue', substr($entry, 64, 2))['value'] ?? 0;
            if ($nameLength < 2 || $nameLength > 64 || ($nameLength % 2) !== 0) {
                continue;
            }

            $name = mb_convert_encoding(substr($entry, 0, $nameLength - 2), 'UTF-8', 'UTF-16LE');
            $sizeHigh = $this->littleEndianUnsigned(substr($entry, 124, 4));
            if ($sizeHigh !== 0) {
                continue;
            }

            $entries[] = [
                'name' => mb_strtolower($name),
                'start' => $this->littleEndianUnsigned(substr($entry, 116, 4)),
                'size' => $this->littleEndianUnsigned(substr($entry, 120, 4)),
            ];
        }

        return $entries;
    }

    /**
     * @param  resource  $handle
     * @param  array{name: string, start: int, size: int}  $entry
     * @param  list<int>  $fat
     */
    private function compoundStreamPrefix($handle, array $entry, array $fat, int $sectorSize, int $fileSize, int $length): ?string
    {
        if ($entry['size'] < $length || $entry['start'] >= 0xFFFFFFF0) {
            return null;
        }

        $contents = '';
        $sector = $entry['start'];
        $seen = [];
        while (strlen($contents) < $length) {
            if (isset($seen[$sector]) || ! isset($fat[$sector])) {
                return null;
            }

            $seen[$sector] = true;
            $chunk = $this->compoundSector($handle, $sector, $sectorSize, $fileSize);
            if ($chunk === null) {
                return null;
            }

            $contents .= $chunk;
            $sector = $fat[$sector];
        }

        return substr($contents, 0, $length);
    }

    private function isValidWordFib(string $fib): bool
    {
        $identifier = unpack('vvalue', substr($fib, 0, 2))['value'] ?? 0;
        $version = unpack('vvalue', substr($fib, 2, 2))['value'] ?? 0;
        $flags = unpack('vvalue', substr($fib, 10, 2))['value'] ?? 0;

        return $identifier === 0xA5EC
            && $version >= 0x0065
            && $version <= 0x0112
            && ($flags & 0x8100) === 0;
    }

    private function littleEndianUnsigned(string $bytes): int
    {
        return strlen($bytes) === 4 ? (int) (unpack('Vvalue', $bytes)['value'] ?? 0xFFFFFFFF) : 0xFFFFFFFF;
    }

    private function isValidDocxPackage(UploadedFile $file): bool
    {
        $path = $file->getRealPath();
        if ($path === false || ! class_exists(ZipArchive::class)) {
            return false;
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CHECKCONS) !== true) {
            return false;
        }

        try {
            if ($zip->numFiles < 3 || $zip->numFiles > (int) config('converter.word_to_pdf_archive_max_entries')) {
                return false;
            }

            $expandedBytes = 0;
            $expandedLimit = (int) config('converter.word_to_pdf_expanded_max_mb') * 1024 * 1024;

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                if (! is_array($entry) || ! $this->isSafeArchiveEntry($entry, $expandedLimit)) {
                    return false;
                }

                if (method_exists($zip, 'getEncryptionName') && $zip->getEncryptionName($index) !== null) {
                    return false;
                }

                $expandedBytes += (int) ($entry['size'] ?? 0);
                if ($expandedBytes > $expandedLimit) {
                    return false;
                }
            }

            if ($zip->locateName('word/vbaProject.bin', ZipArchive::FL_NOCASE) !== false) {
                return false;
            }

            foreach (['[Content_Types].xml', '_rels/.rels', 'word/document.xml'] as $requiredEntry) {
                if ($zip->locateName($requiredEntry) === false) {
                    return false;
                }
            }

            return $this->hasValidContentTypes($zip)
                && $this->hasValidOfficeDocumentRelationship($zip)
                && $this->hasValidWordDocumentRoot($zip);
        } finally {
            $zip->close();
        }
    }

    private function hasValidContentTypes(ZipArchive $zip): bool
    {
        $document = $this->archiveXml($zip, '[Content_Types].xml', 65_536);
        if ($document === null) {
            return false;
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('ct', 'http://schemas.openxmlformats.org/package/2006/content-types');
        $nodes = $xpath->query(
            '/ct:Types/ct:Override[@PartName="/word/document.xml" and @ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"]',
        );

        return $nodes !== false && $nodes->length === 1;
    }

    private function hasValidOfficeDocumentRelationship(ZipArchive $zip): bool
    {
        $document = $this->archiveXml($zip, '_rels/.rels', 65_536);
        if ($document === null) {
            return false;
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');
        $nodes = $xpath->query(
            '/r:Relationships/r:Relationship[@Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" and @Target="word/document.xml" and not(@TargetMode)]',
        );

        return $nodes !== false && $nodes->length === 1;
    }

    private function hasValidWordDocumentRoot(ZipArchive $zip): bool
    {
        $document = $this->archiveXml($zip, 'word/document.xml', 4 * 1024 * 1024);
        $root = $document?->documentElement;

        return $root !== null
            && $root->localName === 'document'
            && $root->namespaceURI === 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    }

    private function archiveXml(ZipArchive $zip, string $name, int $maximumBytes): ?DOMDocument
    {
        $contents = $zip->getFromName($name, $maximumBytes);
        if (! is_string($contents) || $contents === '' || strlen($contents) >= $maximumBytes) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument;
            $loaded = $document->loadXML($contents, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);

            return $loaded && $document->doctype === null ? $document : null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** @param array<string, int|string> $entry */
    private function isSafeArchiveEntry(array $entry, int $expandedLimit): bool
    {
        $name = (string) ($entry['name'] ?? '');
        $size = (int) ($entry['size'] ?? 0);
        $compressedSize = (int) ($entry['comp_size'] ?? 0);

        if ($name === '' || str_contains($name, "\0") || str_contains($name, '\\') || str_starts_with($name, '/')) {
            return false;
        }

        if (preg_match('~(^|/)\.\.?(/|$)~', $name) === 1 || $size < 0 || $size > $expandedLimit) {
            return false;
        }

        return $size === 0 || ($compressedSize > 0 && ($size / $compressedSize) <= 100);
    }

    private function header(UploadedFile $file, int $length): string
    {
        $path = $file->getRealPath();
        if ($path === false) {
            return '';
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }

        try {
            return (string) fread($handle, $length);
        } finally {
            fclose($handle);
        }
    }
}
