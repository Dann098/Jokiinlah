<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class AssignProjectStaff
{
    public function __construct(private ActivityLogger $logger) {}

    public function execute(Project $project, ?User $staff, User $actor): Project
    {
        if (! $actor->isAdmin()) {
            throw new AuthorizationException('Hanya admin dapat menugaskan staff.');
        }
        if ($staff && (! $staff->isStaff() || ! $staff->is_active)) {
            throw ValidationException::withMessages(['staff' => 'Staff harus aktif dan memiliki role staff.']);
        }

        $before = $project->assigned_staff_id;
        $project->forceFill(['assigned_staff_id' => $staff?->id])->save();
        $this->logger->log('project.staff_assigned', 'Penugasan staff proyek diperbarui.', $actor, $project, ['before' => $before, 'after' => $staff?->id]);

        return $project->refresh();
    }
}
