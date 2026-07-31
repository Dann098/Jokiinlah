<?php

namespace App\Policies;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use App\Policies\Concerns\AuthorizesProjectAccess;

class ProjectPolicy
{
    use AuthorizesProjectAccess;

    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Project $project): bool
    {
        return $this->canViewProject($user, $project);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function viewChat(User $user, Project $project): bool
    {
        return $user->is_active
            && $project->deleted_at === null
            && $this->canViewProject($user, $project);
    }

    public function sendMessage(User $user, Project $project): bool
    {
        return $this->viewChat($user, $project)
            && $project->status !== ProjectStatus::Cancelled;
    }

    public function update(User $user, Project $project): bool
    {
        return $this->canWorkOnProject($user, $project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Project $project): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Project $project): bool
    {
        return false;
    }
}
