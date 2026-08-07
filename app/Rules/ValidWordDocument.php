<?php

namespace App\Rules;

use Closure;
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

        if ($path === false || $fileSize < ($sectorSize * 2) || $fatSectorCount < 1 || $fatSectorCount > 109 || $difatSectorCount !== 0) {
            return null;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        try {
            $fat = [];
            for ($index = 0; $index < $fatSectorCount; $index++) {
                $fatSector = $this->littleEndianUnsigned(substr($header, 76 + ($index * 4), 4));
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

            return $this->compoundEntryNames($directory);
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

    /** @return list<string> */
    private function compoundEntryNames(string $directory): array
    {
        $names = [];

        foreach (str_split($directory, 128) as $entry) {
            if (strlen($entry) !== 128 || ! in_array(ord($entry[66]), [1, 2, 5], true)) {
                continue;
            }

            $nameLength = unpack('vvalue', substr($entry, 64, 2))['value'] ?? 0;
            if ($nameLength < 2 || $nameLength > 64 || ($nameLength % 2) !== 0) {
                continue;
            }

            $name = mb_convert_encoding(substr($entry, 0, $nameLength - 2), 'UTF-8', 'UTF-16LE');
            $names[] = mb_strtolower($name);
        }

        return array_values(array_unique($names));
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

            $contentTypes = $zip->getFromName('[Content_Types].xml', 65_536);

            return is_string($contentTypes)
                && ! str_contains(strtolower($contentTypes), 'macroenabled')
                && str_contains(
                    $contentTypes,
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
                );
        } finally {
            $zip->close();
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
