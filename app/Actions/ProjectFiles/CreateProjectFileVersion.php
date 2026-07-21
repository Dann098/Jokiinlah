<?php

namespace App\Actions\ProjectFiles;

use App\Models\ProjectFile;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateProjectFileVersion
{
    public function __construct(private ActivityLogger $logger) {}

    public function execute(ProjectFile $current, User $uploader, array $metadata): ProjectFile
    {
        return DB::transaction(function () use ($current, $uploader, $metadata): ProjectFile {
            $latest = ProjectFile::withTrashed()
                ->where('project_id', $current->project_id)
                ->where('document_uuid', $current->document_uuid)
                ->lockForUpdate()
                ->orderByDesc('version')
                ->firstOrFail();

            $storedName = $metadata['stored_name'] ?? (string) Str::uuid();
            $file = ProjectFile::query()->forceCreate(array_merge($metadata, [
                'project_id' => $current->project_id,
                'uploaded_by' => $uploader->id,
                'document_uuid' => $current->document_uuid,
                'version' => $latest->version + 1,
                'stored_name' => $storedName,
                'disk' => 'local',
                'retention_until' => now()->addDays((int) config('jokiinlah.default_retention_days')),
            ]));
            $this->logger->log('project_file.version_uploaded', 'Versi baru berkas privat dicatat.', $uploader, $file, ['previous_file_id' => $latest->id, 'version' => $file->version]);

            return $file;
        }, 3);
    }
}
