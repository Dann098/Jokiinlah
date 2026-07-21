<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum AppointmentStatus: string
{
    use HasOptions;

    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Terjadwal', self::Confirmed => 'Dikonfirmasi', self::Completed => 'Selesai', self::Cancelled => 'Dibatalkan'
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Completed => 'success', self::Cancelled => 'danger', self::Confirmed => 'primary', default => 'warning'
        };
    }
}
