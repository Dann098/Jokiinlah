<?php

namespace App\Notifications;

use App\Models\Consultation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProjectRequestStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        private Consultation $consultation,
        private array $channels = ['database', 'mail'],
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pembaruan permintaan proyek')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Status permintaan '.$this->consultation->request_code.' diperbarui menjadi '.$this->consultation->customerStatusLabel().'.')
            ->action('Lihat permintaan', route('customer.project-requests.show', $this->consultation));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'consultation_id' => $this->consultation->id,
            'request_code' => $this->consultation->request_code,
            'status' => $this->consultation->status->value,
            'status_label' => $this->consultation->customerStatusLabel(),
            'url' => route('customer.project-requests.show', $this->consultation),
        ];
    }
}
