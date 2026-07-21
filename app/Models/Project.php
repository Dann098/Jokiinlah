<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['service_id', 'title', 'description', 'start_date', 'deadline'];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class, 'payment_status' => PaymentStatus::class, 'progress' => 'integer',
            'start_date' => 'immutable_datetime', 'deadline' => 'immutable_datetime', 'completed_at' => 'immutable_datetime',
            'payment_updated_at' => 'immutable_datetime', 'archived_at' => 'immutable_datetime', 'retention_until' => 'immutable_datetime',
        ];
    }

    public function consultation(): BelongsTo { return $this->belongsTo(Consultation::class); }
    public function customer(): BelongsTo { return $this->belongsTo(User::class, 'customer_id'); }
    public function assignedStaff(): BelongsTo { return $this->belongsTo(User::class, 'assigned_staff_id'); }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
    public function milestones(): HasMany { return $this->hasMany(ProjectMilestone::class); }
    public function files(): HasMany { return $this->hasMany(ProjectFile::class); }
    public function revisions(): HasMany { return $this->hasMany(Revision::class); }
    public function reminders(): HasMany { return $this->hasMany(Reminder::class); }
    public function appointments(): HasMany { return $this->hasMany(Appointment::class); }
    public function scopeOwnedBy(Builder $query, User $user): Builder { return $query->where('customer_id', $user->id); }
    public function scopeAssignedTo(Builder $query, User $user): Builder { return $query->where('assigned_staff_id', $user->id); }
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->when(! $user->isAdmin(), fn (Builder $query): Builder => $user->isStaff()
            ? $query->where('assigned_staff_id', $user->id)
            : $query->where('customer_id', $user->id));
    }
}
