<?php

namespace App\ValueObjects;

final readonly class WordToPdfConversionResult
{
    public function __construct(
        public string $pdfPath,
        public string $workspacePath,
    ) {}
}
