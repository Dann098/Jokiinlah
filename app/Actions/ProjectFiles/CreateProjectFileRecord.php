<?php

namespace App\Actions\ProjectFiles;

use App\Enums\MalwareScanStatus;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateProjectFileRecord
{
    public function __construct(private ActivityLogger $logger) {}

    public function execute(Project $project, User $uploader, array $metadata): ProjectFile
    {
        return DB::transaction(function () use ($project, $uploader, $metadata): ProjectFile {
            $storedName = $metadata['stored_name'] ?? (string) Str::uuid();
            $file = ProjectFile::query()->forceCreate(array_merge($metadata, [
                'project_id' => $project->id,
                'uploaded_by' => $uploader->id,
                'document_uuid' => (string) Str::uuid(),
                'version' => 1,
                'stored_name' => $storedName,
                'disk' => $metadata['disk'] ?? config('jokiinlah.private_disk', 'local'),
                'scan_status' => $metadata['scan_status'] ?? MalwareScanStatus::Pending->value,
                'scanned_at' => $metadata['scanned_at'] ?? null,
                'retention_until' => now()->addDays((int) config('jokiinlah.default_retention_days')),
            ]));
            $this->logger->log('project_file.uploaded', 'Metadata berkas privat dicatat.', $uploader, $file, ['version' => 1]);

            return $file;
        });
    }
}
