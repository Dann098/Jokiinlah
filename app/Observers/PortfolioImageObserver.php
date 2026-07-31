<?php

namespace App\Observers;

use App\Models\Portfolio;
use App\Services\PortfolioImageStorage;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class PortfolioImageObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly PortfolioImageStorage $images) {}

    public function updated(Portfolio $portfolio): void
    {
        $oldPaths = [];

        if ($portfolio->wasChanged('thumbnail')) {
            $oldPaths[] = $portfolio->getRawOriginal('thumbnail');
        }

        if ($portfolio->wasChanged('gallery')) {
            $oldPaths = [...$oldPaths, ...$this->decodeGallery($portfolio->getRawOriginal('gallery'))];
        }

        $newPaths = [$portfolio->thumbnail, ...($portfolio->gallery ?? [])];
        $newManagedPaths = array_values(array_filter(array_map(
            fn (?string $path): ?string => $this->images->managedPath($path),
            $newPaths,
        )));

        $removedPaths = array_filter(
            $oldPaths,
            fn (?string $path): bool => ($managedPath = $this->images->managedPath($path)) !== null
                && ! in_array($managedPath, $newManagedPaths, true),
        );

        $this->images->deleteUnreferenced(array_values($removedPaths), (int) $portfolio->getKey());
    }

    public function deleted(Portfolio $portfolio): void
    {
        $this->images->deleteUnreferenced([
            $portfolio->thumbnail,
            ...($portfolio->gallery ?? []),
        ], (int) $portfolio->getKey());
    }

    /**
     * @return array<int, string>
     */
    private function decodeGallery(mixed $gallery): array
    {
        if (is_array($gallery)) {
            return array_values(array_filter($gallery, 'is_string'));
        }

        if (! is_string($gallery) || $gallery === '') {
            return [];
        }

        $decoded = json_decode($gallery, true);

        return is_array($decoded)
            ? array_values(array_filter($decoded, 'is_string'))
            : [];
    }
}
