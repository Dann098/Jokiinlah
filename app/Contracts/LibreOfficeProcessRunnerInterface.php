<?php

namespace App\Contracts;

use App\ValueObjects\LibreOfficeProcessResult;

interface LibreOfficeProcessRunnerInterface
{
    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $environment
     */
    public function run(array $command, array $environment, int $timeout): LibreOfficeProcessResult;
}
