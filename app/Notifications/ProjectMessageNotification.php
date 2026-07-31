<?php

namespace App\Notifications;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\ProjectMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ProjectMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        private ProjectMessage $message,
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
        $project = $this->message->project;

        return (new MailMessage)
            ->subject('Pesan baru pada proyek '.$project->project_code)
            ->line('Ada pesan baru pada proyek '.$project->title.'.')
            ->line($this->snippet())
            ->action('Buka percakapan', $this->urlFor($notifiable));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message_id' => $this->message->id,
            'project_id' => $this->message->project_id,
            'project_code' => $this->message->project->project_code,
            'sender_id' => $this->message->sender_id,
            'snippet' => $this->snippet(),
            'url' => $this->urlFor($notifiable),
        ];
    }

    private function snippet(): string
    {
        return Str::limit($this->message->message, 100, '');
    }

    private function urlFor(object $notifiable): string
    {
        return $notifiable->isCustomer()
            ? route('customer.projects.show', $this->message->project).'#project-chat'
            : ProjectResource::getUrl('view', ['record' => $this->message->project_id]).'#project-chat';
    }
}
