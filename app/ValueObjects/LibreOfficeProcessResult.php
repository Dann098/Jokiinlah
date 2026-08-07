<?php

namespace App\ValueObjects;

final readonly class LibreOfficeProcessResult
{
    public function __construct(public int $exitCode) {}

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }
}
