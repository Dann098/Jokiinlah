<?php

namespace App\Services\WordToPdf;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class ConversionWorkspaceCleaner
{
    public function root(): string
    {
        return rtrim((string) config('converter.temporary_directory'), '\\/');
    }

    /** @return list<string> */
    public function staleWorkspaces(int $olderThanTimestamp): array
    {
        $root = $this->root();
        File::ensureDirectoryExists($root, 0750, true);

        return array_values(array_filter(
            File::directories($root),
            fn (string $path): bool => $this->isSafeWorkspace($path)
                && ((int) filemtime($path)) <= $olderThanTimestamp,
        ));
    }

    public function delete(string $workspace): bool
    {
        if (! is_dir($workspace)) {
            return true;
        }

        return $this->isSafeWorkspace($workspace) && File::deleteDirectory($workspace);
    }

    public function isSafeWorkspace(string $workspace): bool
    {
        if (is_link($workspace) || ! Str::isUuid(basename($workspace))) {
            return false;
        }

        $rootPath = realpath($this->root());
        $workspacePath = realpath($workspace);

        if ($rootPath === false || $workspacePath === false) {
            return false;
        }

        return strcasecmp(dirname($workspacePath), $rootPath) === 0;
    }
}
