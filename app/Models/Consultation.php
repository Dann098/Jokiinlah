<?php

namespace App\Models;

use App\Enums\ConsultationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Consultation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['service_id', 'name', 'email', 'phone', 'project_title', 'description', 'deadline', 'technology', 'budget', 'privacy_accepted_at', 'privacy_policy_version', 'terms_version', 'source'];

    protected function casts(): array
    {
        return [
            'status' => ConsultationStatus::class, 'deadline' => 'immutable_datetime', 'privacy_accepted_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime', 'retention_until' => 'immutable_datetime', 'budget' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
    public function project(): HasOne { return $this->hasOne(Project::class); }
}
