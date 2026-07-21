<?php

namespace App\Models;

use App\Enums\MilestoneStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMilestone extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'title', 'description', 'due_date', 'status', 'completed_at', 'sort_order'];

    protected function casts(): array
    {
        return ['status' => MilestoneStatus::class, 'due_date' => 'immutable_datetime', 'completed_at' => 'immutable_datetime', 'sort_order' => 'integer'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
