<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class UpdateProjectDetails
{
    public function __construct(private ActivityLogger $logger) {}

    public function execute(Project $project, array $data, User $actor): Project
    {
        if (! $actor->isAdmin()) {
            throw new AuthorizationException('Hanya admin dapat memperbarui detail inti proyek.');
        }

        return DB::transaction(function () use ($project, $data, $actor): Project {
            $project->forceFill([
                'service_id' => $data['service_id'],
                'title' => trim($data['title']),
                'description' => trim($data['description']),
                'start_date' => $data['start_date'] ?? null,
                'deadline' => $data['deadline'] ?? null,
                'admin_note' => filled($data['admin_note'] ?? null) ? trim($data['admin_note']) : null,
            ])->save();

            $this->logger->log('project.details_updated', 'Detail inti proyek diperbarui.', $actor, $project, [
                'changed_fields' => array_values(array_diff(array_keys($project->getChanges()), ['updated_at'])),
            ]);

            return $project->refresh();
        });
    }
}
