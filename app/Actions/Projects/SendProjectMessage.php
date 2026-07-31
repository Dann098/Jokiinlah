<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\ProjectChatParticipant;
use App\Models\ProjectMessage;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ProjectMessageNotifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SendProjectMessage
{
    public function __construct(
        private ActivityLogger $logger,
        private ProjectMessageNotifier $notifier,
    ) {}

    public function execute(Project $project, User $sender, string $body): ProjectMessage
    {
        if (! $sender->can('sendMessage', $project)) {
            throw new AuthorizationException('Anda tidak dapat mengirim pesan pada proyek ini.');
        }

        $body = trim($body);
        Validator::make(['message' => $body], [
            'message' => ['required', 'string', 'max:2000'],
        ])->validate();

        $rateKey = "project-chat:{$sender->id}:{$project->id}";
        if (RateLimiter::tooManyAttempts($rateKey, 20)) {
            throw ValidationException::withMessages([
                'message' => 'Terlalu banyak pesan. Silakan tunggu satu menit.',
            ]);
        }
        RateLimiter::hit($rateKey, 60);

        $message = DB::transaction(function () use ($project, $sender, $body): ProjectMessage {
            $message = ProjectMessage::query()->forceCreate([
                'project_id' => $project->id,
                'sender_id' => $sender->id,
                'message' => $body,
            ]);

            ProjectChatParticipant::query()->upsert(
                [[
                    'project_id' => $project->id,
                    'user_id' => $sender->id,
                    'last_read_message_id' => $message->id,
                    'last_read_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]],
                ['project_id', 'user_id'],
                ['last_read_message_id', 'last_read_at', 'updated_at'],
            );

            $this->logger->log(
                'project.message_sent',
                'Pesan proyek dikirim.',
                $sender,
                $message,
                [
                    'message_id' => $message->id,
                    'project_id' => $project->id,
                    'sender_id' => $sender->id,
                    'sent_at' => $message->created_at?->toIso8601String(),
                ],
            );

            return $message;
        });

        $this->notifier->notifyParticipants($message);

        return $message;
    }
}
