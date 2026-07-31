<?php

namespace App\Models;

use App\Services\PortfolioImageStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Portfolio extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'slug', 'category', 'description', 'problem', 'solution', 'result', 'technologies', 'thumbnail', 'gallery', 'is_published', 'is_demo'];

    protected function casts(): array
    {
        return ['technologies' => 'array', 'gallery' => 'array', 'is_published' => 'boolean', 'is_demo' => 'boolean'];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function thumbnailUrl(): string
    {
        return $this->resolvedThumbnailUrl() ?? asset('images/logo.webp');
    }

    public function resolvedThumbnailUrl(): ?string
    {
        return app(PortfolioImageStorage::class)->url($this->thumbnail);
    }

    /**
     * @return array<int, string>
     */
    public function galleryUrls(): array
    {
        return array_values(array_filter(array_map(
            fn (?string $path): ?string => app(PortfolioImageStorage::class)->url($path),
            $this->gallery ?? [],
        )));
    }
}
