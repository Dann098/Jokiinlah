<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'value', 'type', 'group', 'is_public'];

    protected function casts(): array
    {
        return ['is_public' => 'boolean'];
    }
}
