<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use LogicException;

class ProjectMessage extends Model
{
    protected $fillable = ['message'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Pesan proyek tidak dapat diubah.'));
        static::deleting(fn () => throw new LogicException('Pesan proyek tidak dapat dihapus.'));
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'model');
    }
}
