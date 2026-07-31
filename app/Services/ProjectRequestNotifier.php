<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Consultation;
use App\Models\User;
use App\Notifications\NewConsultationNotification;
use App\Notifications\ProjectRequestStatusNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProjectRequestNotifier
{
    public function notifyAdmins(Consultation $consultation): void
    {
        User::query()
            ->active()
            ->where('role', UserRole::Admin->value)
            ->each(fn (User $admin) => $this->send(
                $admin,
                fn (array $channels) => new NewConsultationNotification($consultation, $channels),
                $consultation,
            ));
    }

    public function notifyCustomer(Consultation $consultation): void
    {
        if ($consultation->user?->is_active) {
            $this->send(
                $consultation->user,
                fn (array $channels) => new ProjectRequestStatusNotification($consultation, $channels),
                $consultation,
            );
        }
    }

    private function send(User $recipient, callable $notification, Consultation $consultation): void
    {
        foreach (['database', 'mail'] as $channel) {
            try {
                $recipient->notify($notification([$channel]));
            } catch (Throwable $exception) {
                Log::warning('Notifikasi permintaan proyek gagal.', [
                    'consultation_id' => $consultation->id,
                    'channel' => $channel,
                    'exception' => $exception::class,
                ]);
            }
        }
    }
}
