<?php

namespace App\Actions\ProjectFiles;

use App\Models\ProjectFile;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

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

        try {
            if (! Storage::disk($file->disk)->exists($file->file_path)) {
                throw new RuntimeException('Berkas fisik tidak ditemukan; record audit dipertahankan.');
            }

            $deleted = Storage::disk($file->disk)->delete($file->file_path);
        } catch (Throwable $exception) {
            throw new RuntimeException('Penyimpanan gagal menghapus berkas; record database tidak dihapus.', previous: $exception);
        }

        if (! $deleted) {
            throw new RuntimeException('Penyimpanan menolak penghapusan; record database tidak dihapus.');
        }

        $this->logger->log('project_file.force_deleted', 'Admin menghapus berkas privat secara permanen.', $actor, $file, [
            'reason' => trim($reason), 'file_path' => $file->file_path, 'document_uuid' => $file->document_uuid, 'version' => $file->version,
        ]);
        $file->forceDelete();
    }
}
