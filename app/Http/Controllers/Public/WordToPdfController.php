<?php

namespace App\Http\Controllers\Public;

use App\Contracts\WordToPdfConverterInterface;
use App\Exceptions\WordToPdfConversionFailed;
use App\Exceptions\WordToPdfConversionTimedOut;
use App\Exceptions\WordToPdfConverterUnavailable;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConvertWordToPdfRequest;
use App\Services\FilenameSanitizer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class WordToPdfController extends Controller
{
    public function show(): View
    {
        return view('public.free-tools.word-to-pdf', [
            'maximumMegabytes' => (int) config('converter.word_to_pdf_max_mb'),
            'conversionTimeout' => (int) config('converter.word_to_pdf_timeout'),
        ]);
    }

    public function convert(
        ConvertWordToPdfRequest $request,
        WordToPdfConverterInterface $converter,
        FilenameSanitizer $filenameSanitizer,
    ): StreamedResponse|RedirectResponse {
        /** @var UploadedFile $document */
        $document = $request->file('document');
        $downloadName = $this->downloadName($document, $filenameSanitizer);

        $lock = Cache::lock(
            'word-to-pdf:conversion',
            max(10, (int) config('converter.word_to_pdf_timeout') + 10),
        );

        if (! $lock->get()) {
            return back()->withErrors([
                'document' => 'Server sedang memproses dokumen lain. Silakan coba kembali beberapa saat lagi.',
            ]);
        }

        try {
            $result = $converter->convert($document);
        } catch (WordToPdfConverterUnavailable) {
            return back()->withErrors([
                'document' => 'Layanan konversi sedang tidak tersedia. Silakan coba kembali nanti.',
            ]);
        } catch (WordToPdfConversionTimedOut|WordToPdfConversionFailed|Throwable) {
            return back()->withErrors([
                'document' => 'Dokumen belum berhasil dikonversi. Pastikan file Word tidak rusak lalu coba kembali.',
            ]);
        } finally {
            $lock->release();
        }

        $handle = is_file($result->pdfPath) ? fopen($result->pdfPath, 'rb') : false;
        if ($handle === false) {
            try {
                $converter->cleanup($result);
            } catch (Throwable) {
                Log::warning('Word to PDF workspace cleanup deferred.', [
                    'reason_code' => 'missing_output_cleanup_failed',
                ]);
            }

            return back()->withErrors([
                'document' => 'Dokumen belum berhasil dikonversi. Pastikan file Word tidak rusak lalu coba kembali.',
            ]);
        }

        return response()->streamDownload(function () use ($converter, $handle, $result): void {
            try {
                while (! feof($handle)) {
                    $chunk = fread($handle, 1024 * 64);
                    if ($chunk === false) {
                        Log::warning('Word to PDF download stopped.', ['reason_code' => 'stream_read_failed']);

                        break;
                    }

                    echo $chunk;
                    flush();
                }
            } finally {
                if (is_resource($handle)) {
                    fclose($handle);
                }

                try {
                    $converter->cleanup($result);
                } catch (Throwable) {
                    Log::warning('Word to PDF workspace cleanup deferred.', [
                        'reason_code' => 'stream_cleanup_failed',
                    ]);
                }
            }
        }, $downloadName, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function downloadName(UploadedFile $document, FilenameSanitizer $filenameSanitizer): string
    {
        $safeName = $filenameSanitizer->sanitize($document->getClientOriginalName(), 'dokumen.docx');
        $baseName = trim(pathinfo($safeName, PATHINFO_FILENAME), ' .-');
        $baseName = Str::limit($baseName === '' ? 'dokumen' : $baseName, 170, '');

        return $baseName.'.pdf';
    }
}
