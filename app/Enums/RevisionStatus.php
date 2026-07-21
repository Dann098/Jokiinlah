<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum RevisionStatus: string
{
    use HasOptions;

    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case InProgress = 'in_progress';
    case CustomerConfirmation = 'customer_confirmation';
    case Approved = 'approved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Diajukan', self::UnderReview => 'Sedang Ditinjau', self::InProgress => 'Sedang Dikerjakan',
            self::CustomerConfirmation => 'Menunggu Konfirmasi Pelanggan', self::Approved => 'Disetujui', self::Closed => 'Ditutup',
        };
    }
    public function color(): string { return match ($this) { self::Approved, self::Closed => 'success', self::InProgress => 'primary', default => 'warning' }; }
}
