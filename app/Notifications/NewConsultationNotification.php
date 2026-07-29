<?php

namespace App\Notifications;

use App\Models\Consultation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewConsultationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(private Consultation $consultation, private array $channels = ['database', 'mail'])
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Permintaan konsultasi baru')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Permintaan konsultasi baru telah diterima.')
            ->line('Nomor konsultasi: '.$this->consultation->request_code)
            ->line('Tinjau permintaan melalui area admin.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'consultation_id' => $this->consultation->id,
            'request_code' => $this->consultation->request_code,
            'project_title' => $this->consultation->project_title,
        ];
    }
}
