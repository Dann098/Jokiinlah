<?php

namespace App\Actions\Projects;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateProjectStatus
{
    public function __construct(private ActivityLogger $logger) {}

    public function execute(Project $project, ProjectStatus $target, User $actor, ?string $overrideReason = null): Project
    {
        $isAssignedStaff = $actor->isStaff() && $project->assigned_staff_id === $actor->id;
        if (! $actor->isAdmin() && ! $isAssignedStaff) {
            throw new AuthorizationException('Anda tidak berwenang mengubah status proyek ini.');
        }

        $from = $project->status;
        if ($from === $target) {
            return $project;
        }

        $normal = $from->canTransitionTo($target);
        if (! $normal && ! $actor->isAdmin()) {
            throw ValidationException::withMessages(['status' => 'Staff hanya dapat mengikuti transisi status normal.']);
        }
        if (! $normal && blank($overrideReason)) {
            throw ValidationException::withMessages(['override_reason' => 'Alasan override wajib diisi.']);
        }

        return DB::transaction(function () use ($project, $target, $actor, $from, $normal, $overrideReason): Project {
            $project->forceFill([
                'status' => $target,
                'completed_at' => $target === ProjectStatus::Completed ? now() : null,
            ])->save();

            $this->logger->log('project.status_changed', 'Status proyek diperbarui.', $actor, $project, [
                'before' => $from->value,
                'after' => $target->value,
                'transition_type' => $normal ? 'normal' : 'admin_override',
                'reason' => $normal ? null : trim((string) $overrideReason),
            ]);

            return $project->refresh();
        });
    }
}
