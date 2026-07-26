<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'appointment_date', 'meeting_link', 'notes', 'status'];

    protected function casts(): array
    {
        return ['appointment_date' => 'immutable_datetime', 'status' => AppointmentStatus::class];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function safeMeetingUrl(): ?string
    {
        if (! $this->meeting_link || filter_var($this->meeting_link, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return mb_strtolower((string) parse_url($this->meeting_link, PHP_URL_SCHEME)) === 'https'
            ? $this->meeting_link
            : null;
    }
}
