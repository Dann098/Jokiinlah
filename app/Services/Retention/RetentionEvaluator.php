<?php

namespace App\Services\Retention;

use App\Enums\PurgeStatus;
use App\Models\Consultation;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Revision;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Log;
use Throwable;

class RetentionEvaluator
{
    private const MODELS = [
        ProjectFile::class,
        Revision::class,
        Consultation::class,
        Project::class,
    ];

    public function __construct(private ActivityLogger $logger) {}

    /**
     * @return array{found: int, updated: int, failed: int}
     */
    public function evaluate(int $limit, bool $dryRun): array
    {
        $result = ['found' => 0, 'updated' => 0, 'failed' => 0];
        $remaining = max(1, $limit);

        foreach (self::MODELS as $modelClass) {
            if ($remaining <= 0) {
                break;
            }

            $records = $modelClass::onlyTrashed()
                ->whereNotNull('retention_until')
                ->where('retention_until', '<=', now())
                ->where('purge_status', PurgeStatus::Eligible->value)
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
                    $record->forceFill([
                        'purge_status' => PurgeStatus::Pending,
                        'purge_pending_at' => now(),
                        'purge_failure_code' => null,
                    ])->saveQuietly();

                    $this->logger->log(
                        'retention.purge_pending',
                        'Record melewati masa retensi dan masuk antrean purge.',
                        subject: $record,
                        metadata: ['entity' => $record->getMorphClass()],
                    );
                    $result['updated']++;
                } catch (Throwable $exception) {
                    Log::warning('Evaluasi retention record gagal dan dapat dicoba ulang.', [
                        'entity' => $record->getMorphClass(),
                        'record_id' => $record->getKey(),
                        'exception' => $exception::class,
                    ]);
                    $result['failed']++;
                }
            }
        }

        return $result;
    }
}
