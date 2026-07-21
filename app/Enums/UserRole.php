<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum UserRole: string
{
    use HasOptions;

    case Admin = 'admin';
    case Staff = 'staff';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin', self::Staff => 'Staff', self::Customer => 'Pelanggan'
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Admin => 'danger', self::Staff => 'warning', self::Customer => 'primary'
        };
    }
}
