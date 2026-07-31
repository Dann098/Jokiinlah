<?php

namespace App\Models;

use App\Services\PublicImageStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Portfolio extends Model
{
    use HasFactory;

    private const GITHUB_REPOSITORY_URL_PATTERN = '#\Ahttps://github\.com/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+(?:\.git)?/?\z#i';

    private const RESERVED_GITHUB_OWNERS = [
        'about',
        'account',
        'apps',
        'business',
        'codespaces',
        'collections',
        'contact',
        'customer-stories',
        'dashboard',
        'edu',
        'enterprise',
        'events',
        'explore',
        'features',
        'gist',
        'git',
        'github',
        'home',
        'issues',
        'join',
        'login',
        'logout',
        'marketplace',
        'new',
        'notifications',
        'organizations',
        'orgs',
        'pricing',
        'pulls',
        'readme',
        'repositories',
        'search',
        'security',
        'settings',
        'site',
        'sponsors',
        'stars',
        'team',
        'topics',
        'trending',
        'users',
        'watching',
    ];

    protected $fillable = ['title', 'slug', 'category', 'description', 'problem', 'solution', 'result', 'technologies', 'thumbnail', 'gallery', 'repository_url', 'is_published', 'is_demo'];

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
        return app(PublicImageStorage::class)->url($this->thumbnail);
    }

    public function repositoryUrl(): ?string
    {
        if (! is_string($this->repository_url)) {
            return null;
        }

        $url = trim($this->repository_url);
        $parts = parse_url($url);

        $pathParts = explode('/', trim((string) ($parts['path'] ?? ''), '/'));

        if (filter_var($url, FILTER_VALIDATE_URL) === false
            || ! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || strtolower((string) ($parts['host'] ?? '')) !== 'github.com'
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])
            || preg_match(self::GITHUB_REPOSITORY_URL_PATTERN, $url) !== 1
            || in_array(strtolower($pathParts[0] ?? ''), self::RESERVED_GITHUB_OWNERS, true)) {
            return null;
        }

        return $url;
    }

    /**
     * @return array<int, string>
     */
    public function galleryUrls(): array
    {
        return array_values(array_filter(array_map(
            fn (?string $path): ?string => app(PublicImageStorage::class)->url($path),
            $this->gallery ?? [],
        )));
    }
}
