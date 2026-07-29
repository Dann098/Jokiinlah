<?php

namespace App\Actions\ProjectFiles;

use App\Enums\PurgeStatus;
use App\Models\ProjectFile;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
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
        if ($file->retention_until === null || $file->retention_until->isFuture()) {
            throw ValidationException::withMessages(['retention_until' => 'Masa retensi berkas belum berakhir.']);
        }

        $file->forceFill([
            'purge_status' => PurgeStatus::Pending,
            'purge_pending_at' => now(),
            'purge_failure_code' => null,
        ])->saveQuietly();

        $this->logger->log('project_file.purge_requested', 'Admin memasukkan berkas ke proses two-phase purge.', $actor, $file, [
            'reason' => trim($reason),
            'version' => $file->version,
        ]);
    }
}
