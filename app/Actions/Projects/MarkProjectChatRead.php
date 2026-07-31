<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\ProjectChatParticipant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class MarkProjectChatRead
{
    public function execute(Project $project, User $user): ProjectChatParticipant
    {
        if (! $user->can('viewChat', $project)) {
            throw new AuthorizationException('Anda tidak dapat membuka percakapan proyek ini.');
        }

        $values = [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'last_read_message_id' => $project->messages()->max('id'),
            'last_read_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        ProjectChatParticipant::query()->upsert(
            [$values],
            ['project_id', 'user_id'],
            ['last_read_message_id', 'last_read_at', 'updated_at'],
        );

        return ProjectChatParticipant::query()
            ->where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->sole();
    }
}
