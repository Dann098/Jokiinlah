<?php

namespace App\Http\Controllers;

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
        Project $project,
        Revision $revision,
        ActivityLogger $logger,
        FilenameSanitizer $filenames,
    ): StreamedResponse {
        $this->authorize('view', $project);
        $this->authorize('view', $revision);
        abort_unless($revision->attachment_path, 404);
        abort_unless(Storage::disk('local')->exists($revision->attachment_path), 404, 'Lampiran tidak ditemukan.');

        $logger->log(
            'revision.attachment_downloaded',
            'Lampiran revisi diunduh melalui pemeriksaan authorization.',
            request()->user(),
            $revision,
        );

        return Storage::disk('local')->download(
            $revision->attachment_path,
            $filenames->sanitize($revision->attachment_original_name, 'lampiran-revisi'),
        );
    }
}
