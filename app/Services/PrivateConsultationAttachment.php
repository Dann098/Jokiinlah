<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PrivateConsultationAttachment
{
    public function __construct(private FilenameSanitizer $filenames) {}

    public function store(UploadedFile $file): array
    {
        $folder = (string) Str::uuid();
        $storedName = (string) Str::uuid();
        $path = Storage::disk('local')->putFileAs('consultations/'.$folder, $file, $storedName);

        if ($path === false) {
            throw new \RuntimeException('Lampiran tidak dapat disimpan.');
        }

        $checksum = hash_file('sha256', $file->getRealPath());

        if ($checksum === false) {
            Storage::disk('local')->delete($path);

            throw new \RuntimeException('Checksum lampiran tidak dapat dibuat.');
        }

        return [
            'attachment_original_name' => $this->filenames->sanitize($file->getClientOriginalName()),
            'attachment_path' => $path,
            'attachment_mime' => $file->getMimeType() ?: 'application/octet-stream',
            'attachment_size' => $file->getSize(),
            'attachment_checksum' => $checksum,
        ];
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }
}
