<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectChatParticipant extends Model
{
    protected $fillable = ['last_read_message_id', 'last_read_at'];

    protected function casts(): array
    {
        return ['last_read_at' => 'immutable_datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
