<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\ProjectMessage;
use App\Models\User;
use App\Notifications\ProjectMessageNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProjectMessageNotifier
{
    public function notifyParticipants(ProjectMessage $message): void
    {
        $message->loadMissing('project');
        $project = $message->project;

        User::query()
            ->active()
            ->whereKeyNot($message->sender_id)
            ->where(function (Builder $query) use ($project): void {
                $query->where('role', UserRole::Admin->value)
                    ->orWhere('id', $project->customer_id);

                if ($project->assigned_staff_id) {
                    $query->orWhere('id', $project->assigned_staff_id);
                }
            })
            ->each(function (User $recipient) use ($message): void {
                foreach (['database', 'mail'] as $channel) {
                    try {
                        $recipient->notify(new ProjectMessageNotification($message, [$channel]));
                    } catch (Throwable $exception) {
                        Log::warning('Notifikasi pesan proyek gagal.', [
                            'message_id' => $message->id,
                            'channel' => $channel,
                            'exception' => $exception::class,
                        ]);
                    }
                }
            });
    }
}
