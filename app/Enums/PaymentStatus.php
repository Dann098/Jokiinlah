<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PaymentStatus: string
{
    use HasOptions;

    case Unpaid = 'unpaid';
    case DownPayment = 'down_payment';
    case Paid = 'paid';

    public function label(): string { return match ($this) { self::Unpaid => 'Belum Dibayar', self::DownPayment => 'DP', self::Paid => 'Lunas' }; }
    public function color(): string { return match ($this) { self::Unpaid => 'danger', self::DownPayment => 'warning', self::Paid => 'success' }; }
}
