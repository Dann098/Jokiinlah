<?php

namespace App\Actions\ProjectFiles;

use App\Models\ProjectFile;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PermanentlyDeleteProjectFile
{
    public function __construct(private ActivityLogger $logger) {}

    public function execute(ProjectFile $file, User $actor, string $reason): void
    {
        if (! $actor->isAdmin()) {
            throw new AuthorizationException('Hanya admin dapat menghapus berkas secara permanen.');
        }
        if (! $file->trashed()) {
            throw ValidationException::withMessages(['file' => 'Berkas harus dihapus secara lunak terlebih dahulu.']);
        }
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Alasan penghapusan permanen wajib diisi.']);
        }

        $this->logger->log('project_file.force_deleted', 'Admin menghapus berkas privat secara permanen.', $actor, $file, [
            'reason' => trim($reason), 'file_path' => $file->file_path, 'document_uuid' => $file->document_uuid, 'version' => $file->version,
        ]);
        Storage::disk($file->disk)->delete($file->file_path);
        $file->forceDelete();
    }
}
