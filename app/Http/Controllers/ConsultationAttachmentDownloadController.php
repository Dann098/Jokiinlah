<?php

namespace App\Http\Controllers;

use App\Models\Consultation;
use App\Services\ActivityLogger;
use App\Services\FilenameSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConsultationAttachmentDownloadController extends Controller
{
    public function __invoke(
        Request $request,
        Consultation $consultation,
        FilenameSanitizer $filenames,
        ActivityLogger $logger,
    ): StreamedResponse {
        $this->authorize('view', $consultation);
        abort_if(blank($consultation->attachment_path), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($consultation->attachment_path), 404);

        $logger->log(
            'consultation.attachment_downloaded',
            'Admin mengunduh lampiran konsultasi privat.',
            $request->user(),
            $consultation,
        );

        return $disk->download(
            $consultation->attachment_path,
            $filenames->sanitize($consultation->attachment_original_name, 'lampiran-konsultasi'),
            ['Content-Type' => $consultation->attachment_mime ?: 'application/octet-stream'],
        );
    }
}
