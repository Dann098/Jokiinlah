<?php

namespace App\Services\WordToPdf;

use App\Contracts\LibreOfficeProcessRunnerInterface;
use App\Contracts\WordToPdfConverterInterface;
use App\Exceptions\WordToPdfConversionFailed;
use App\Exceptions\WordToPdfConversionTimedOut;
use App\Exceptions\WordToPdfConverterUnavailable;
use App\ValueObjects\WordToPdfConversionResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class LibreOfficeWordToPdfConverter implements WordToPdfConverterInterface
{
    public function __construct(
        private readonly LibreOfficeProcessRunnerInterface $runner,
        private readonly ConversionWorkspaceCleaner $cleaner = new ConversionWorkspaceCleaner,
    ) {}

    public function convert(UploadedFile $document): WordToPdfConversionResult
    {
        $binary = $this->availableBinary();
        $workspace = $this->createWorkspace();

        try {
            $extension = strtolower($document->getClientOriginalExtension());
            if (! in_array($extension, ['doc', 'docx'], true)) {
                throw new WordToPdfConversionFailed;
            }

            $physicalId = Str::uuid()->toString();
            $sourcePath = $workspace.DIRECTORY_SEPARATOR.$physicalId.'.'.$extension;
            $profilePath = $workspace.DIRECTORY_SEPARATOR.'profile';
            File::ensureDirectoryExists($profilePath, 0750, true);
            $document->move($workspace, basename($sourcePath));

            $result = $this->runner->run(
                $this->command($binary, $profilePath, $workspace, $sourcePath),
                $this->environment($workspace),
                max(1, (int) config('converter.word_to_pdf_timeout')),
            );

            $pdfPath = $workspace.DIRECTORY_SEPARATOR.$physicalId.'.pdf';
            if (! $result->successful() || ! $this->isValidPdf($pdfPath)) {
                throw new WordToPdfConversionFailed;
            }

            File::delete($sourcePath);
            File::deleteDirectory($profilePath);

            return new WordToPdfConversionResult($pdfPath, $workspace);
        } catch (WordToPdfConversionTimedOut $exception) {
            $this->cleaner->delete($workspace);
            Log::warning('Word to PDF conversion stopped.', ['reason_code' => 'timeout']);

            throw $exception;
        } catch (Throwable $exception) {
            $this->cleaner->delete($workspace);
            Log::warning('Word to PDF conversion stopped.', [
                'reason_code' => 'conversion_failed',
                'failure_type' => $exception::class,
            ]);

            throw new WordToPdfConversionFailed;
        }
    }

    public function cleanup(WordToPdfConversionResult $result): void
    {
        $this->cleaner->delete($result->workspacePath);
    }

    private function availableBinary(): string
    {
        $binary = trim((string) config('converter.libreoffice_binary'));
        $available = $binary !== '' && is_file($binary);

        if (app()->environment('production') && config('converter.sandbox_verified') !== true) {
            $available = false;
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            $available = $available && is_executable($binary);
        }

        if (! $available) {
            throw new WordToPdfConverterUnavailable;
        }

        return $binary;
    }

    private function createWorkspace(): string
    {
        $root = $this->cleaner->root();
        File::ensureDirectoryExists($root, 0750, true);
        $workspace = $root.DIRECTORY_SEPARATOR.Str::uuid();
        File::ensureDirectoryExists($workspace, 0750, true);

        if (! is_dir($workspace) || ! is_writable($workspace)) {
            throw new WordToPdfConversionFailed;
        }

        return $workspace;
    }

    /** @return list<string> */
    private function command(string $binary, string $profilePath, string $workspace, string $sourcePath): array
    {
        return [
            $binary,
            '-env:UserInstallation='.$this->fileUri($profilePath),
            '--headless',
            '--nologo',
            '--nodefault',
            '--nofirststartwizard',
            '--norestore',
            '--convert-to',
            'pdf',
            '--outdir',
            $workspace,
            $sourcePath,
        ];
    }

    /** @return array<string, string> */
    private function environment(string $workspace): array
    {
        return [
            'HOME' => $workspace,
            'TMP' => $workspace,
            'TEMP' => $workspace,
            'USERPROFILE' => $workspace,
        ];
    }

    private function fileUri(string $path): string
    {
        return 'file:///'.ltrim(str_replace('\\', '/', $path), '/');
    }

    private function isValidPdf(string $path): bool
    {
        $maximumBytes = (int) config('converter.word_to_pdf_output_max_mb') * 1024 * 1024;
        $size = is_file($path) ? filesize($path) : false;

        if ($size === false || $size < 6 || $size > $maximumBytes) {
            return false;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        try {
            return fread($handle, 5) === '%PDF-';
        } finally {
            fclose($handle);
        }
    }
}
