<?php

namespace App\Actions\Revisions;

use App\Enums\RevisionPriority;
use App\Enums\RevisionStatus;
use App\Models\Project;
use App\Models\Revision;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\PrivateProjectFileStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class CreateCustomerRevision
{
    public function __construct(
        private ActivityLogger $logger,
        private PrivateProjectFileStorage $storage,
    ) {}

    public function execute(Project $project, User $customer, array $data, ?UploadedFile $attachment = null): Revision
    {
        $file = $attachment ? $this->storage->store($attachment, 'revisions') : [];

        try {
            return DB::transaction(function () use ($project, $customer, $data, $file): Revision {
                $revision = Revision::query()->forceCreate([
                    'project_id' => $project->id,
                    'submitted_by' => $customer->id,
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'section_reference' => $data['section_reference'] ?? null,
                    'priority' => RevisionPriority::Normal,
                    'status' => RevisionStatus::Submitted,
                    'attachment_original_name' => $file['original_name'] ?? null,
                    'attachment_path' => $file['file_path'] ?? null,
                    'attachment_mime' => $file['file_type'] ?? null,
                    'attachment_size' => $file['file_size'] ?? null,
                    'attachment_checksum' => $file['checksum'] ?? null,
                    'retention_until' => now()->addDays((int) config('jokiinlah.default_retention_days')),
                ]);

                $this->logger->log(
                    'revision.submitted',
                    'Pelanggan mengirim permintaan revisi.',
                    $customer,
                    $revision,
                    ['project_id' => $project->id, 'has_attachment' => $file !== []],
                );

                return $revision;
            }, 3);
        } catch (Throwable $exception) {
            $this->storage->delete($file['file_path'] ?? null);
            throw $exception;
        }
    }
}
