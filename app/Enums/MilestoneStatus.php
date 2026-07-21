<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum MilestoneStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu', self::InProgress => 'Sedang Dikerjakan', self::Completed => 'Selesai', self::Cancelled => 'Dibatalkan'
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Completed => 'success', self::Cancelled => 'danger', self::InProgress => 'primary', default => 'warning'
        };
    }
}
