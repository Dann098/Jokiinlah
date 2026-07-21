<?php

namespace App\Services;

use Illuminate\Support\Str;

class FilenameSanitizer
{
    private const MAX_LENGTH = 180;

    public function sanitize(?string $originalName, string $fallback = 'dokumen'): string
    {
        $name = basename(str_replace('\\', '/', (string) $originalName));
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name) ?? '';
        $name = preg_replace('/[<>:\/\\|?*]+/u', '-', $name) ?? '';
        $name = preg_replace('/\s+/u', ' ', $name) ?? '';
        $name = trim($name, ' .-');

        if ($name === '' || $name === '.' || $name === '..') {
            $name = $fallback;
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $suffix = $extension === '' ? '' : '.'.Str::lower($extension);
        $base = $suffix === '' ? $name : Str::beforeLast($name, '.');
        $maximumBaseLength = max(1, self::MAX_LENGTH - Str::length($suffix));
        $base = trim(Str::substr($base, 0, $maximumBaseLength), ' .-');

        return ($base === '' ? $fallback : $base).$suffix;
    }
}
