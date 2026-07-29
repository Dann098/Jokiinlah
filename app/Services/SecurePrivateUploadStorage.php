<?php

namespace App\Services;

use App\Contracts\MalwareScannerInterface;
use App\Enums\MalwareScanStatus;
use App\Exceptions\UnsafeUploadException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class SecurePrivateUploadStorage
{
    public function __construct(
        private FilenameSanitizer $filenames,
        private MalwareScannerInterface $scanner,
        private ActivityLogger $logger,
    ) {}

    /**
     * @return array{
     *     original_name: string,
     *     stored_name: string,
     *     disk: string,
     *     file_path: string,
     *     file_type: string,
     *     file_size: int,
     *     checksum: string,
     *     scan_status: string,
     *     scanned_at: Carbon
     * }
     */
    public function store(UploadedFile $file, string $area): array
    {
        $diskName = (string) config('jokiinlah.private_disk', 'local');
        $disk = Storage::disk($diskName);
        $token = (string) Str::uuid();
        $pendingPath = 'quarantine/pending/'.$token;
        $storedName = (string) Str::uuid();

        if ($disk->putFileAs('quarantine/pending', $file, $token) === false) {
            throw new RuntimeException('Berkas tidak dapat disimpan untuk pemeriksaan keamanan.');
        }

        $result = $this->scanner->scan($diskName, $pendingPath);

        if ($result->status !== MalwareScanStatus::Clean) {
            $quarantineArea = $result->status === MalwareScanStatus::Infected ? 'infected' : 'failed';
            $quarantinePath = 'quarantine/'.$quarantineArea.'/'.$token;
            $disk->move($pendingPath, $quarantinePath);

            $this->logger->log(
                $result->status === MalwareScanStatus::Infected ? 'security.file_infected' : 'security.file_scan_failed',
                $result->status === MalwareScanStatus::Infected
                    ? 'Upload berbahaya dipindahkan ke karantina.'
                    : 'Upload gagal diverifikasi dan dipindahkan ke karantina.',
                request()?->user(),
                metadata: [
                    'reason_code' => $result->reasonCode,
                    'file_fingerprint' => substr(hash('sha256', $token), 0, 24),
                ],
            );

            throw new UnsafeUploadException($result->status, $result->reasonCode);
        }

        $finalPath = trim($area, '/').'/'.Str::uuid().'/'.$storedName;

        if (! $disk->move($pendingPath, $finalPath)) {
            $disk->delete($pendingPath);
            throw new RuntimeException('Berkas aman tidak dapat dipindahkan ke penyimpanan final.');
        }

        $checksum = $this->checksum($diskName, $finalPath);

        if ($checksum === null) {
            $disk->delete($finalPath);
            throw new RuntimeException('Checksum berkas tidak dapat dibuat.');
        }

        return [
            'original_name' => $this->filenames->sanitize($file->getClientOriginalName()),
            'stored_name' => $storedName,
            'disk' => $diskName,
            'file_path' => $finalPath,
            'file_type' => $file->getMimeType() ?: 'application/octet-stream',
            'file_size' => (int) $file->getSize(),
            'checksum' => $checksum,
            'scan_status' => MalwareScanStatus::Clean->value,
            'scanned_at' => now(),
        ];
    }

    private function checksum(string $disk, string $path): ?string
    {
        $stream = Storage::disk($disk)->readStream($path);

        if (! is_resource($stream)) {
            return null;
        }

        try {
            $context = hash_init('sha256');
            hash_update_stream($context, $stream);

            return hash_final($context);
        } finally {
            fclose($stream);
        }
    }
}
