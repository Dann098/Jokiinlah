<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class Testimonial extends Model
{
    use HasFactory;

    protected $fillable = ['customer_name', 'customer_role', 'content', 'rating', 'photo', 'is_published', 'is_demo'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'is_published' => 'boolean', 'is_demo' => 'boolean'];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function setRatingAttribute(int $value): void
    {
        if ($value < 1 || $value > 5) {
            throw new InvalidArgumentException('Rating harus bernilai 1 sampai 5.');
        }
        $this->attributes['rating'] = $value;
    }
}
