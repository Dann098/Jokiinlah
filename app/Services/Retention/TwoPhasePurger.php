<?php

namespace App\Services\Retention;

use App\Enums\PurgeStatus;
use App\Models\Consultation;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Revision;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TwoPhasePurger
{
    private const MODELS = [
        ProjectFile::class,
        Revision::class,
        Consultation::class,
        Project::class,
    ];

    public function __construct(private ActivityLogger $logger) {}

    /**
     * @return array{found: int, physical_deleted: int, purged: int, failed: int}
     */
    public function purge(int $limit, bool $dryRun): array
    {
        $result = ['found' => 0, 'physical_deleted' => 0, 'purged' => 0, 'failed' => 0];
        $remaining = max(1, $limit);

        foreach (self::MODELS as $modelClass) {
            if ($remaining <= 0) {
                break;
            }

            $records = $modelClass::onlyTrashed()
                ->whereIn('purge_status', [
                    PurgeStatus::Pending->value,
                    PurgeStatus::PhysicalDeleted->value,
                ])
                ->orderBy('id')
                ->limit($remaining)
                ->get();

            foreach ($records as $record) {
                $result['found']++;
                $remaining--;

                if ($dryRun) {
                    continue;
                }

                try {
                    if ($record->purge_status !== PurgeStatus::PhysicalDeleted) {
                        $this->deletePhysicalData($record);
                        $record->forceFill([
                            'purge_status' => PurgeStatus::PhysicalDeleted,
                            'physical_deleted_at' => now(),
                            'purge_failure_code' => null,
                        ])->saveQuietly();
                        $result['physical_deleted']++;

                        $this->logger->log(
                            'purge.physical_deleted',
                            'Data fisik record telah dihapus atau sudah tidak tersedia.',
                            subject: $record,
                        );
                    }

                    $record->forceFill([
                        'purge_status' => PurgeStatus::Purged,
                        'purged_at' => now(),
                    ])->saveQuietly();

                    $this->logger->log(
                        'purge.record_purged',
                        'Record database dipurge setelah data fisik konsisten.',
                        subject: $record,
                    );
                    $record->forceDelete();
                    $result['purged']++;
                } catch (Throwable $exception) {
                    $failureCode = $this->failureCode($exception);

                    try {
                        $record->forceFill(['purge_failure_code' => $failureCode])->saveQuietly();
                        $this->logger->log(
                            'purge.failed',
                            'Purge record gagal dan dapat dicoba ulang.',
                            subject: $record,
                            metadata: ['failure_code' => $failureCode],
                        );
                    } catch (Throwable) {
                        // The batch still continues; the original state remains retryable.
                    }

                    $result['failed']++;
                }
            }
        }

        return $result;
    }

    private function deletePhysicalData(Model $record): void
    {
        if ($record instanceof Project) {
            $hasChildren = ProjectFile::withTrashed()->where('project_id', $record->getKey())->exists()
                || Revision::withTrashed()->where('project_id', $record->getKey())->exists();

            if ($hasChildren) {
                throw new \RuntimeException('child_records_pending');
            }

            return;
        }

        [$disk, $path] = match (true) {
            $record instanceof ProjectFile => [$record->disk, $record->file_path],
            $record instanceof Consultation => [config('jokiinlah.private_disk', 'local'), $record->attachment_path],
            $record instanceof Revision => [config('jokiinlah.private_disk', 'local'), $record->attachment_path],
            default => [null, null],
        };

        if (! $disk || ! $path) {
            return;
        }

        $storage = Storage::disk($disk);

        if ($storage->exists($path) && ! $storage->delete($path)) {
            throw new \RuntimeException('storage_delete_failed');
        }
    }

    private function failureCode(Throwable $exception): string
    {
        return match ($exception->getMessage()) {
            'child_records_pending' => 'child_records_pending',
            'storage_delete_failed' => 'storage_delete_failed',
            default => 'unexpected_failure',
        };
    }
}
