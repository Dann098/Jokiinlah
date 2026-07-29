<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PrivateProjectFileStorage
{
    public function __construct(private SecurePrivateUploadStorage $storage) {}

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
        return $this->storage->store($file, $area);
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk((string) config('jokiinlah.private_disk', 'local'))->delete($path);
        }
    }
}
