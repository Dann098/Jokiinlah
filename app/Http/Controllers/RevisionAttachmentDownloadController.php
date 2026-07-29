<?php

namespace App\Http\Controllers;

use App\Enums\MalwareScanStatus;
use App\Models\Project;
use App\Models\Revision;
use App\Services\ActivityLogger;
use App\Services\FilenameSanitizer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RevisionAttachmentDownloadController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(
        Revision $revision,
        ActivityLogger $logger,
        FilenameSanitizer $filenames,
    ): StreamedResponse {
        return $this->download($revision, $logger, $filenames);
    }

    public function customer(
        Project $project,
        Revision $revision,
        ActivityLogger $logger,
        FilenameSanitizer $filenames,
    ): StreamedResponse {
        abort_unless($project->is($revision->project), 404);
        $this->authorize('view', $project);

        return $this->download($revision, $logger, $filenames);
    }

    private function download(
        Revision $revision,
        ActivityLogger $logger,
        FilenameSanitizer $filenames,
    ): StreamedResponse {
        $this->authorize('view', $revision);
        abort_unless($revision->attachment_path, 404);
        abort_unless($revision->attachment_scan_status === MalwareScanStatus::Clean, 423, 'Lampiran belum tersedia untuk diunduh.');
        $disk = Storage::disk((string) config('jokiinlah.private_disk', 'local'));
        abort_unless($disk->exists($revision->attachment_path), 404, 'Lampiran tidak ditemukan.');

        $logger->log(
            'revision.attachment_downloaded',
            'Lampiran revisi diunduh melalui pemeriksaan authorization.',
            request()->user(),
            $revision,
        );

        return $disk->download(
            $revision->attachment_path,
            $filenames->sanitize($revision->attachment_original_name, 'lampiran-revisi'),
            [
                'Content-Type' => $revision->attachment_mime ?: 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "sandbox; default-src 'none'",
            ],
        );
    }
}
