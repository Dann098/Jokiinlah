<?php

namespace App\Services\WordToPdf;

use App\Contracts\LibreOfficeProcessRunnerInterface;
use App\Exceptions\WordToPdfConversionTimedOut;
use App\ValueObjects\LibreOfficeProcessResult;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

final class SymfonyLibreOfficeProcessRunner implements LibreOfficeProcessRunnerInterface
{
    public function run(array $command, array $environment, int $timeout): LibreOfficeProcessResult
    {
        $process = new Process($command, null, $environment, null, $timeout);
        $process->setIdleTimeout($timeout);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            throw new WordToPdfConversionTimedOut;
        }

        return new LibreOfficeProcessResult($process->getExitCode() ?? 1);
    }
}
