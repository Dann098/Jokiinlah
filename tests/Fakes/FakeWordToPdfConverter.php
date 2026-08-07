<?php

namespace Tests\Fakes;

use App\Contracts\WordToPdfConverterInterface;
use App\Exceptions\WordToPdfConversionFailed;
use App\Exceptions\WordToPdfConversionTimedOut;
use App\Exceptions\WordToPdfConverterUnavailable;
use App\ValueObjects\WordToPdfConversionResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class FakeWordToPdfConverter implements WordToPdfConverterInterface
{
    public function __construct(private readonly string $outcome = 'success') {}

    public function convert(UploadedFile $document): WordToPdfConversionResult
    {
        match ($this->outcome) {
            'unavailable' => throw new WordToPdfConverterUnavailable,
            'timeout' => throw new WordToPdfConversionTimedOut,
            'failure' => throw new WordToPdfConversionFailed,
            default => null,
        };

        $workspace = storage_path('app/private/conversions/'.Str::uuid());
        File::ensureDirectoryExists($workspace);
        $pdfPath = $workspace.DIRECTORY_SEPARATOR.Str::uuid().'.pdf';
        file_put_contents($pdfPath, "%PDF-1.4\n% fake conversion\n%%EOF");

        return new WordToPdfConversionResult($pdfPath, $workspace);
    }

    public function cleanup(WordToPdfConversionResult $result): void
    {
        File::deleteDirectory($result->workspacePath);
    }
}
