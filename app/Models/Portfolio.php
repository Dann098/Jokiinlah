<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Portfolio extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'slug', 'category', 'description', 'problem', 'solution', 'result', 'technologies', 'thumbnail', 'gallery', 'is_published'];
    protected function casts(): array { return ['technologies' => 'array', 'gallery' => 'array', 'is_published' => 'boolean']; }
    public function scopePublished(Builder $query): Builder { return $query->where('is_published', true); }
}
