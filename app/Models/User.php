<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'password'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function consultations(): HasMany { return $this->hasMany(Consultation::class); }
    public function ownedProjects(): HasMany { return $this->hasMany(Project::class, 'customer_id'); }
    public function assignedProjects(): HasMany { return $this->hasMany(Project::class, 'assigned_staff_id'); }
    public function projectFiles(): HasMany { return $this->hasMany(ProjectFile::class, 'uploaded_by'); }
    public function revisions(): HasMany { return $this->hasMany(Revision::class, 'submitted_by'); }
    public function reminders(): HasMany { return $this->hasMany(Reminder::class); }
    public function customerAppointments(): HasMany { return $this->hasMany(Appointment::class, 'customer_id'); }
    public function staffAppointments(): HasMany { return $this->hasMany(Appointment::class, 'staff_id'); }
    public function articles(): HasMany { return $this->hasMany(Article::class, 'author_id'); }
    public function activityLogs(): HasMany { return $this->hasMany(ActivityLog::class); }
    public function scopeActive(Builder $query): Builder { return $query->where('is_active', true); }
    public function scopeWithRole(Builder $query, UserRole $role): Builder { return $query->where('role', $role->value); }
    public function isAdmin(): bool { return $this->role === UserRole::Admin; }
    public function isStaff(): bool { return $this->role === UserRole::Staff; }
    public function isCustomer(): bool { return $this->role === UserRole::Customer; }
}
