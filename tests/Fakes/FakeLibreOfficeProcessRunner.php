<?php

namespace Tests\Fakes;

use App\Contracts\LibreOfficeProcessRunnerInterface;
use App\ValueObjects\LibreOfficeProcessResult;
use Closure;

final class FakeLibreOfficeProcessRunner implements LibreOfficeProcessRunnerInterface
{
    public function __construct(private readonly Closure $handler) {}

    public function run(array $command, array $environment, int $timeout): LibreOfficeProcessResult
    {
        return ($this->handler)($command, $environment, $timeout);
    }
}
