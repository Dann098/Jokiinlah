<?php

namespace App\Observers;

use App\Services\PublicImageStorage;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;

class PublicImageObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly PublicImageStorage $images) {}

    public function updated(Model $model): void
    {
        $oldPaths = [];

        foreach ($this->images->imageAttributes($model) as $attribute) {
            if ($model->wasChanged($attribute)) {
                $oldPaths = [
                    ...$oldPaths,
                    ...$this->decodePaths($model->getRawOriginal($attribute)),
                ];
            }
        }

        $currentManagedPaths = array_values(array_filter(array_map(
            fn (?string $path): ?string => $this->images->managedPath($path),
            $this->images->pathsFor($model),
        )));

        $removedPaths = array_filter(
            $oldPaths,
            fn (?string $path): bool => ($managedPath = $this->images->managedPath($path)) !== null
                && ! in_array($managedPath, $currentManagedPaths, true),
        );

        $this->images->deleteUnreferenced(array_values($removedPaths), $model);
    }

    public function deleted(Model $model): void
    {
        $this->images->deleteUnreferenced($this->images->pathsFor($model), $model);
    }

    /**
     * @return array<int, string>
     */
    private function decodePaths(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, 'is_string'));
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded)
            ? array_values(array_filter($decoded, 'is_string'))
            : [$value];
    }
}
