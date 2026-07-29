<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PrivateConsultationAttachment
{
    public function __construct(private SecurePrivateUploadStorage $storage) {}

    public function store(UploadedFile $file): array
    {
        $metadata = $this->storage->store($file, 'consultations');

        return [
            'attachment_original_name' => $metadata['original_name'],
            'attachment_path' => $metadata['file_path'],
            'attachment_mime' => $metadata['file_type'],
            'attachment_size' => $metadata['file_size'],
            'attachment_checksum' => $metadata['checksum'],
            'attachment_scan_status' => $metadata['scan_status'],
            'attachment_scanned_at' => $metadata['scanned_at'],
        ];
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk((string) config('jokiinlah.private_disk', 'local'))->delete($path);
        }
    }
}
