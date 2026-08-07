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
        $workingDirectory = $environment['HOME'] ?? null;
        $process = new Process(
            $command,
            is_string($workingDirectory) ? $workingDirectory : null,
            $this->isolatedEnvironment($environment),
            null,
            $timeout,
        );
        $process->setIdleTimeout($timeout);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            throw new WordToPdfConversionTimedOut;
        }

        return new LibreOfficeProcessResult($process->getExitCode() ?? 1);
    }

    /**
     * Symfony merges the supplied environment with the parent process. Explicitly
     * setting inherited keys to false prevents application secrets from crossing
     * the process boundary while retaining the OS values LibreOffice needs.
     *
     * @param  array<string, string>  $environment
     * @return array<string, string|false>
     */
    private function isolatedEnvironment(array $environment): array
    {
        $parent = getenv();
        $parentKeys = is_array($parent) ? array_keys($parent) : [];
        $serverKeys = array_keys(array_filter(
            $_SERVER,
            fn (mixed $value): bool => is_scalar($value) || $value === null,
        ));
        $isolated = array_fill_keys(array_unique([...$parentKeys, ...array_keys($_ENV), ...$serverKeys]), false);

        foreach (['PATH', 'SystemRoot', 'WINDIR', 'COMSPEC', 'PATHEXT', 'LANG', 'LC_ALL'] as $key) {
            $value = getenv($key);
            if (is_string($value) && $value !== '') {
                $isolated[$key] = $value;
            }
        }

        return [...$isolated, ...$environment];
    }
}
