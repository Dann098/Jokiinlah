<?php

namespace App\Services\Reconciliation;

use App\Enums\MalwareScanStatus;
use App\Enums\PurgeStatus;
use App\Models\Consultation;
use App\Models\ProjectFile;
use App\Models\Revision;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\StorageAttributes;
use Throwable;

class FileReconciler
{
    public function __construct(private ActivityLogger $logger) {}

    /**
     * @return array{checked: int, mismatches: int, repaired: int, quarantined: int, issues: array<int, string>}
     */
    public function reconcile(int $limit, bool $checksum, bool $repair, bool $quarantineOrphans): array
    {
        $result = ['checked' => 0, 'mismatches' => 0, 'repaired' => 0, 'quarantined' => 0, 'issues' => []];
        $remaining = max(1, $limit);

        foreach ($this->recordSources() as [$modelClass, $pathColumn, $diskColumn, $sizeColumn, $checksumColumn, $scanColumn, $expectedPrefix]) {
            if ($remaining <= 0) {
                break;
            }

            $records = $modelClass::withTrashed()
                ->whereNotNull($pathColumn)
                ->orderBy('id')
                ->limit($remaining)
                ->get();

            foreach ($records as $record) {
                $remaining--;
                $result['checked']++;

                try {
                    $diskName = $diskColumn ? $record->{$diskColumn} : config('jokiinlah.private_disk', 'local');
                    $path = (string) $record->{$pathColumn};
                    $disk = Storage::disk($diskName);

                    if (! str_starts_with($path, $expectedPrefix.'/')) {
                        $this->issue($result, $record, 'path_convention_mismatch');
                    }

                    if (! $disk->exists($path)) {
                        $this->issue($result, $record, 'physical_file_missing');

                        if ($repair && $record->purge_status === PurgeStatus::Pending) {
                            $record->forceFill([
                                'purge_status' => PurgeStatus::PhysicalDeleted,
                                'physical_deleted_at' => now(),
                                'purge_failure_code' => null,
                            ])->saveQuietly();
                            $result['repaired']++;
                        }

                        continue;
                    }

                    if ($sizeColumn && (int) $record->{$sizeColumn} !== (int) $disk->size($path)) {
                        $this->issue($result, $record, 'size_mismatch');
                    }

                    if ($scanColumn && $record->{$scanColumn} !== MalwareScanStatus::Clean) {
                        $this->issue($result, $record, 'scan_status_not_clean');
                    }

                    if ($record->purge_status === PurgeStatus::Purged) {
                        $this->issue($result, $record, 'purged_record_has_physical_file');
                    }

                    if ($checksum && $checksumColumn && $record->{$checksumColumn}) {
                        $actualChecksum = $this->checksum($diskName, $path);

                        if (! hash_equals((string) $record->{$checksumColumn}, (string) $actualChecksum)) {
                            $this->issue($result, $record, 'checksum_mismatch');
                        }
                    }
                } catch (Throwable) {
                    $this->issue($result, $record, 'storage_or_metadata_unavailable');
                }
            }
        }

        if ($remaining > 0) {
            try {
                $this->findOrphans($result, $remaining, $quarantineOrphans);
            } catch (Throwable) {
                $result['mismatches']++;
                $result['issues'][] = 'orphan_scan_unavailable';
            }
        }

        if ($result['mismatches'] > 0) {
            $this->logger->log(
                'reconciliation.mismatch',
                'Reconciliation menemukan inkonsistensi file private.',
                metadata: [
                    'mismatches' => $result['mismatches'],
                    'repaired' => $result['repaired'],
                    'quarantined' => $result['quarantined'],
                ],
            );
        }

        return $result;
    }

    private function recordSources(): array
    {
        return [
            [ProjectFile::class, 'file_path', 'disk', 'file_size', 'checksum', 'scan_status', 'projects'],
            [Revision::class, 'attachment_path', null, 'attachment_size', 'attachment_checksum', 'attachment_scan_status', 'revisions'],
            [Consultation::class, 'attachment_path', null, 'attachment_size', 'attachment_checksum', 'attachment_scan_status', 'consultations'],
        ];
    }

    private function findOrphans(array &$result, int $limit, bool $quarantine): void
    {
        $diskName = (string) config('jokiinlah.private_disk', 'local');
        $disk = Storage::disk($diskName);
        $listing = $disk->getDriver()->listContents('', true);
        $checked = 0;

        /** @var StorageAttributes $attributes */
        foreach ($listing as $attributes) {
            if ($checked >= $limit) {
                break;
            }

            if (! $attributes->isFile()) {
                continue;
            }

            $path = $attributes->path();

            if (str_starts_with($path, 'quarantine/') || $path === '.gitignore') {
                continue;
            }

            $checked++;
            $result['checked']++;

            if ($this->recordExistsForPath($path)) {
                continue;
            }

            $result['mismatches']++;
            $result['issues'][] = 'orphan_file:'.substr(hash('sha256', $path), 0, 16);

            if ($quarantine) {
                $target = 'quarantine/orphans/'.Str::uuid();

                if ($disk->move($path, $target)) {
                    $result['quarantined']++;
                }
            }
        }
    }

    private function recordExistsForPath(string $path): bool
    {
        return ProjectFile::withTrashed()->where('file_path', $path)->exists()
            || Revision::withTrashed()->where('attachment_path', $path)->exists()
            || Consultation::withTrashed()->where('attachment_path', $path)->exists();
    }

    private function issue(array &$result, Model $record, string $code): void
    {
        $result['mismatches']++;
        $result['issues'][] = $code.':'.$record->getMorphClass().':'.$record->getKey();
    }

    private function checksum(string $diskName, string $path): ?string
    {
        $stream = Storage::disk($diskName)->readStream($path);

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
