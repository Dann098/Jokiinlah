<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

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
                && $header === hex2bin('D0CF11E0A1B11AE1'),
            'docx' => in_array($mimeType, self::DOCX_MIME_TYPES, true)
                && str_starts_with($header, "PK\x03\x04"),
            default => false,
        };

        if (! $valid) {
            $fail('Hanya dokumen DOC atau DOCX yang valid yang dapat dikonversi.');
        }
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
