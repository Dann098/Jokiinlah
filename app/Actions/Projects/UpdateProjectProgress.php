<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class UpdateProjectProgress
{
    public function __construct(private ActivityLogger $logger) {}

    public function execute(Project $project, int $progress, User $actor): Project
    {
        if (! $actor->isAdmin() && ! ($actor->isStaff() && $project->assigned_staff_id === $actor->id)) {
            throw new AuthorizationException('Anda tidak berwenang mengubah progress proyek ini.');
        }
        if ($progress < 0 || $progress > 100) {
            throw ValidationException::withMessages(['progress' => 'Progress harus berada pada rentang 0 sampai 100.']);
        }

        $before = $project->progress;
        $project->forceFill(['progress' => $progress])->save();
        $this->logger->log('project.progress_changed', 'Progress proyek diperbarui secara manual.', $actor, $project, ['before' => $before, 'after' => $progress]);

        return $project->refresh();
    }
}
