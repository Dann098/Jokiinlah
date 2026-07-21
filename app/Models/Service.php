<?php

namespace App\Models;

use App\Enums\ServiceCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'category', 'short_description', 'description', 'features', 'technologies', 'icon', 'image', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['category' => ServiceCategory::class, 'features' => 'array', 'technologies' => 'array', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(Faq::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
