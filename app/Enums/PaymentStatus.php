<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PaymentStatus: string
{
    use HasOptions;

    case Unpaid = 'belum_dibayar';
    case DownPayment = 'dp';
    case Paid = 'lunas';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Belum Dibayar', self::DownPayment => 'DP', self::Paid => 'Lunas'
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unpaid => 'danger', self::DownPayment => 'warning', self::Paid => 'success'
        };
    }
}
