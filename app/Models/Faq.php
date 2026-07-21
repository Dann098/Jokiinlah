<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Faq extends Model
{
    use HasFactory;

    protected $fillable = ['service_id', 'question', 'answer', 'category', 'sort_order', 'is_active'];
    protected function casts(): array { return ['sort_order' => 'integer', 'is_active' => 'boolean']; }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
    public function scopeActive(Builder $query): Builder { return $query->where('is_active', true); }
}
