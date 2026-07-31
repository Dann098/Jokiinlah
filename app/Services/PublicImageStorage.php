<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Portfolio;
use App\Models\Service;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PublicImageStorage
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    private const MANAGED_PATH_PATTERN = '#\A(?:articles/thumbnails|portfolios/(?:thumbnails|gallery)|services/images|testimonials/photos)/[A-Za-z0-9][A-Za-z0-9._-]*\.(?:jpe?g|png|webp)\z#Di';

    private const LEGACY_PATH_PATTERN = '#\Aimages/(?:[A-Za-z0-9][A-Za-z0-9._-]*/)*[A-Za-z0-9][A-Za-z0-9._-]*\.(?:jpe?g|png|webp)\z#Di';

    /**
     * @var array<class-string<Model>, array<int, string>>
     */
    private const MODEL_IMAGE_ATTRIBUTES = [
        Article::class => ['thumbnail'],
        Portfolio::class => ['thumbnail', 'gallery'],
        Service::class => ['image'],
        Testimonial::class => ['photo'],
    ];

    public function url(?string $path): ?string
    {
        if ($managedPath = $this->managedPath($path)) {
            return $this->publicDiskUrl($managedPath);
        }

        $legacyPath = $this->legacyPath($path);

        if ($legacyPath === null) {
            return null;
        }

        $basePath = realpath(public_path('images'));
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
     * @return array<int, string>
     */
    public function imageAttributes(Model $model): array
    {
        return self::MODEL_IMAGE_ATTRIBUTES[$model::class] ?? [];
    }

    /**
     * @return array<int, string|null>
     */
    public function pathsFor(Model $model): array
    {
        $paths = [];

        foreach ($this->imageAttributes($model) as $attribute) {
            $value = $model->getAttribute($attribute);

            if (is_array($value)) {
                $paths = [...$paths, ...array_values(array_filter($value, 'is_string'))];
            } elseif (is_string($value)) {
                $paths[] = $value;
            }
        }

        return $paths;
    }

    /**
     * @param  array<int, string|null>  $paths
     */
    public function deleteUnreferenced(array $paths, Model $owner): void
    {
        $managedPaths = array_values(array_unique(array_filter(array_map(
            fn (?string $path): ?string => $this->managedPath($path),
            $paths,
        ))));

        foreach ($managedPaths as $managedPath) {
            if ($this->isReferencedByAnotherRecord($managedPath, $owner)) {
                continue;
            }

            try {
                $disk = Storage::disk('public');

                if (! $disk->exists($managedPath)) {
                    continue;
                }

                if (! $disk->delete($managedPath)) {
                    $this->logCleanupWarning($owner, $managedPath, 'delete_returned_false');
                }
            } catch (Throwable $exception) {
                $this->logCleanupWarning($owner, $managedPath, class_basename($exception));
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

            $url = $disk->url($path);
            $urlPath = parse_url($url, PHP_URL_PATH);

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

    private function isReferencedByAnotherRecord(string $managedPath, Model $owner): bool
    {
        foreach (self::MODEL_IMAGE_ATTRIBUTES as $modelClass => $attributes) {
            $query = $modelClass::query();

            if ($owner instanceof $modelClass) {
                $query->whereKeyNot($owner->getKey());
            }

            if ($query->get(['id', ...$attributes])->contains(
                fn (Model $record): bool => collect($this->pathsFor($record))->contains(
                    fn (?string $path): bool => $this->managedPath($path) === $managedPath,
                ),
            )) {
                return true;
            }
        }

        return false;
    }

    private function logCleanupWarning(Model $owner, string $managedPath, string $reason): void
    {
        Log::warning('Public image cleanup failed.', [
            'owner_type' => class_basename($owner),
            'owner_id' => $owner->getKey(),
            'path_fingerprint' => hash('sha256', $managedPath),
            'reason' => $reason,
        ]);
    }
}
