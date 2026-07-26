<?php

namespace App\Filament\Widgets;

use App\Enums\ConsultationStatus;
use App\Enums\PaymentStatus;
use App\Models\Consultation;
use App\Models\Project;
use App\Models\Revision;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OperationalStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        $projects = Project::query()->visibleTo($user);
        $stats = [
            Stat::make('Proyek aktif', (clone $projects)->whereNotIn('status', ['completed', 'cancelled'])->count())
                ->icon('heroicon-o-briefcase')
                ->color('primary'),
            Stat::make('Revisi terbuka', Revision::query()
                ->whereHas('project', fn ($query) => $query->visibleTo($user))
                ->whereNotIn('status', ['approved', 'closed'])
                ->count())
                ->icon('heroicon-o-arrow-path')
                ->color('warning'),
            Stat::make('Tenggat 7 hari', (clone $projects)
                ->whereBetween('deadline', [now(), now()->addDays(7)])
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count())
                ->icon('heroicon-o-clock')
                ->color('danger'),
        ];

        if ($user->isAdmin()) {
            $stats[] = Stat::make('Konsultasi baru', Consultation::query()->where('status', ConsultationStatus::New->value)->count())
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('info');
            $stats[] = Stat::make('Belum ditugaskan', Project::query()->whereNull('assigned_staff_id')->count())
                ->icon('heroicon-o-user-plus')
                ->color('warning');
            $stats[] = Stat::make('Pembayaran terbuka', Project::query()->where('payment_status', '!=', PaymentStatus::Paid->value)->count())
                ->icon('heroicon-o-banknotes')
                ->color('danger');
        }

        return $stats;
    }
}
