<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ProjectStatus: string
{
    use HasOptions;

    case NewRequest = 'new_request';
    case Consultation = 'consultation';
    case WaitingData = 'waiting_data';
    case RequirementAnalysis = 'requirement_analysis';
    case InProgress = 'in_progress';
    case CustomerReview = 'customer_review';
    case Revision = 'revision';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::NewRequest => 'Permintaan Baru', self::Consultation => 'Konsultasi', self::WaitingData => 'Menunggu Data',
            self::RequirementAnalysis => 'Analisis Kebutuhan', self::InProgress => 'Sedang Dikerjakan',
            self::CustomerReview => 'Menunggu Review Pelanggan', self::Revision => 'Revisi',
            self::Completed => 'Selesai', self::Cancelled => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) { self::Completed => 'success', self::Cancelled => 'danger', self::InProgress => 'primary', default => 'warning' };
    }

    /** @return list<self> */
    public function normalTransitions(): array
    {
        return match ($this) {
            self::NewRequest => [self::Consultation],
            self::Consultation => [self::WaitingData, self::RequirementAnalysis],
            self::WaitingData => [self::RequirementAnalysis],
            self::RequirementAnalysis => [self::InProgress],
            self::InProgress => [self::CustomerReview],
            self::CustomerReview => [self::Revision, self::Completed],
            self::Revision => [self::InProgress, self::CustomerReview, self::Completed],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->normalTransitions(), true);
    }

    public function isActive(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled], true);
    }
}
