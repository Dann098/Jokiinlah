<?php

namespace App\Contracts;

use App\ValueObjects\WordToPdfConversionResult;
use Illuminate\Http\UploadedFile;

interface WordToPdfConverterInterface
{
    public function convert(UploadedFile $document): WordToPdfConversionResult;

    public function cleanup(WordToPdfConversionResult $result): void;
}
