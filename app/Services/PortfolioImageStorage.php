<?php

namespace App\Services;

use App\Models\Portfolio;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PortfolioImageStorage
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    private const MANAGED_PATH_PATTERN = '#\Aportfolios/(?:thumbnails|gallery)/[A-Za-z0-9][A-Za-z0-9._-]*\.(?:jpe?g|png|webp)\z#Di';

    private const LEGACY_PATH_PATTERN = '#\Aimages/portfolios(?:/[A-Za-z0-9][A-Za-z0-9._-]*)*\.(?:jpe?g|png|webp)\z#Di';

    public function url(?string $path): ?string
    {
        if ($managedPath = $this->managedPath($path)) {
            return $this->publicDiskUrl($managedPath);
        }

        $legacyPath = $this->legacyPath($path);

        if ($legacyPath === null) {
            return null;
        }

        $basePath = realpath(public_path('images/portfolios'));
        $filePath = realpath(public_path($legacyPath));

        if ($basePath === false
            || $filePath === false
            || ! is_file($filePath)
            || ! in_array(mime_content_type($filePath), self::ALLOWED_MIME_TYPES, true)) {
            return null;
        }

        $basePrefix = rtrim($basePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (! str_starts_with(strtolower($filePath), strtolower($basePrefix))) {
            return null;
        }

        return asset($legacyPath);
    }

    /**
     * @return array{name: string, size: int, type: ?string, url: string}|null
     */
    public function uploadMetadata(string $path): ?array
    {
        $url = $this->url($path);

        if ($url === null) {
            return null;
        }

        $size = 0;
        $type = null;

        try {
            if ($managedPath = $this->managedPath($path)) {
                $size = Storage::disk('public')->size($managedPath);
                $type = Storage::disk('public')->mimeType($managedPath) ?: null;
            } elseif ($legacyPath = $this->legacyPath($path)) {
                $size = (int) filesize(public_path($legacyPath));
                $type = mime_content_type(public_path($legacyPath)) ?: null;
            }
        } catch (Throwable) {
            // The URL resolver already established existence. Metadata is optional.
        }

        return [
            'name' => basename($path),
            'size' => $size,
            'type' => $type,
            'url' => $url,
        ];
    }

    public function managedPath(?string $path): ?string
    {
        $path = $this->safeRelativePath($path);

        if ($path === null) {
            return null;
        }

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return preg_match(self::MANAGED_PATH_PATTERN, $path) === 1 ? $path : null;
    }

    /**
     * @param  array<int, string|null>  $paths
     */
    public function deleteUnreferenced(array $paths, int $portfolioId): void
    {
        $managedPaths = array_values(array_unique(array_filter(array_map(
            fn (?string $path): ?string => $this->managedPath($path),
            $paths,
        ))));

        foreach ($managedPaths as $managedPath) {
            if ($this->isReferencedByAnotherPortfolio($managedPath, $portfolioId)) {
                continue;
            }

            try {
                $disk = Storage::disk('public');

                if (! $disk->exists($managedPath)) {
                    continue;
                }

                if (! $disk->delete($managedPath)) {
                    $this->logCleanupWarning($portfolioId, $managedPath, 'delete_returned_false');
                }
            } catch (Throwable $exception) {
                $this->logCleanupWarning($portfolioId, $managedPath, class_basename($exception));
            }
        }
    }

    private function publicDiskUrl(string $path): ?string
    {
        try {
            $disk = Storage::disk('public');

            if (! $disk->exists($path)
                || ! in_array($disk->mimeType($path), self::ALLOWED_MIME_TYPES, true)) {
                return null;
            }

            $urlPath = parse_url($disk->url($path), PHP_URL_PATH);

            return is_string($urlPath) && $urlPath !== ''
                ? '/'.ltrim($urlPath, '/')
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function legacyPath(?string $path): ?string
    {
        $path = $this->safeRelativePath($path);

        return $path !== null && preg_match(self::LEGACY_PATH_PATTERN, $path) === 1
            ? $path
            : null;
    }

    private function safeRelativePath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $path = trim($path);

        if ($path === ''
            || str_contains($path, "\0")
            || str_contains($path, '\\')
            || str_contains($path, '..')
            || str_contains($path, '://')
            || str_starts_with($path, '/')
            || preg_match('/\A[A-Za-z]:/', $path) === 1) {
            return null;
        }

        return $path;
    }

    private function isReferencedByAnotherPortfolio(string $managedPath, int $portfolioId): bool
    {
        return Portfolio::query()
            ->whereKeyNot($portfolioId)
            ->get(['id', 'thumbnail', 'gallery'])
            ->contains(function (Portfolio $portfolio) use ($managedPath): bool {
                $paths = [
                    $portfolio->thumbnail,
                    ...($portfolio->gallery ?? []),
                ];

                return collect($paths)->contains(
                    fn (?string $path): bool => $this->managedPath($path) === $managedPath,
                );
            });
    }

    private function logCleanupWarning(int $portfolioId, string $managedPath, string $reason): void
    {
        Log::warning('Portfolio image cleanup failed.', [
            'portfolio_id' => $portfolioId,
            'path_fingerprint' => hash('sha256', $managedPath),
            'reason' => $reason,
        ]);
    }
}
