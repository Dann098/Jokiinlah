<?php

namespace App\Models;

use App\Enums\ArticleCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Article extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'slug', 'excerpt', 'content', 'category', 'thumbnail', 'is_published', 'published_at'];

    protected function casts(): array
    {
        return ['category' => ArticleCategory::class, 'is_published' => 'boolean', 'published_at' => 'immutable_datetime'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->whereNotNull('published_at')->where('published_at', '<=', now());
    }
}
