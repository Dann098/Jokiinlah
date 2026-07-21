<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ConsultationStatus: string
{
    use HasOptions;

    case New = 'new';
    case Contacted = 'contacted';
    case Reviewed = 'reviewed';
    case Converted = 'converted';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Baru', self::Contacted => 'Sudah Dihubungi', self::Reviewed => 'Sudah Ditinjau',
            self::Converted => 'Dikonversi', self::Closed => 'Ditutup', self::Cancelled => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'info', self::Converted => 'success', self::Cancelled => 'danger', default => 'warning'
        };
    }
}
