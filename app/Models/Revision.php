<?php

namespace App\Models;

use App\Enums\RevisionPriority;
use App\Enums\RevisionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Revision extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['title', 'description', 'section_reference', 'priority'];

    protected function casts(): array
    {
        return [
            'priority' => RevisionPriority::class, 'status' => RevisionStatus::class, 'completed_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime', 'retention_until' => 'immutable_datetime',
        ];
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function submitter(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by'); }
}
