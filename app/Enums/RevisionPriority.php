<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum RevisionPriority: string
{
    use HasOptions;

    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string { return match ($this) { self::Low => 'Rendah', self::Normal => 'Normal', self::High => 'Tinggi', self::Urgent => 'Mendesak' }; }
    public function color(): string { return match ($this) { self::Urgent => 'danger', self::High => 'warning', self::Normal => 'primary', self::Low => 'gray' }; }
}
