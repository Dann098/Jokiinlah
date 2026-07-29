<?php

namespace App\Http\Controllers;

use App\Enums\MalwareScanStatus;
use App\Models\ProjectFile;
use App\Services\ActivityLogger;
use App\Services\FilenameSanitizer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectFileDownloadController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(ProjectFile $projectFile, ActivityLogger $logger, FilenameSanitizer $filenames): StreamedResponse
    {
        $this->authorize('download', $projectFile);
        abort_unless($projectFile->scan_status === MalwareScanStatus::Clean, 423, 'Berkas belum tersedia untuk diunduh.');
        abort_unless(Storage::disk($projectFile->disk)->exists($projectFile->file_path), 404, 'Berkas tidak ditemukan.');

        $logger->log('project_file.downloaded', 'Berkas privat diunduh melalui pemeriksaan authorization.', request()->user(), $projectFile, ['version' => $projectFile->version]);

        return Storage::disk($projectFile->disk)->download(
            $projectFile->file_path,
            $filenames->sanitize($projectFile->original_name),
            [
                'Content-Type' => $projectFile->file_type ?: 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "sandbox; default-src 'none'",
            ],
        );
    }
}
