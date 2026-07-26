<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class PrivateProjectFileStorage
{
    public function __construct(private FilenameSanitizer $filenames) {}

    /**
     * @return array{
     *     original_name: string,
     *     stored_name: string,
     *     disk: string,
     *     file_path: string,
     *     file_type: string,
     *     file_size: int,
     *     checksum: string
     * }
     */
    public function store(UploadedFile $file, string $area = 'projects'): array
    {
        $folder = (string) Str::uuid();
        $storedName = (string) Str::uuid();
        $path = Storage::disk('local')->putFileAs($area.'/'.$folder, $file, $storedName);

        if ($path === false) {
            throw new RuntimeException('Berkas tidak dapat disimpan.');
        }

        $checksum = hash_file('sha256', $file->getRealPath());

        if ($checksum === false) {
            Storage::disk('local')->delete($path);

            throw new RuntimeException('Checksum berkas tidak dapat dibuat.');
        }

        return [
            'original_name' => $this->filenames->sanitize($file->getClientOriginalName()),
            'stored_name' => $storedName,
            'disk' => 'local',
            'file_path' => $path,
            'file_type' => $file->getMimeType() ?: 'application/octet-stream',
            'file_size' => (int) $file->getSize(),
            'checksum' => $checksum,
        ];
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }
}
