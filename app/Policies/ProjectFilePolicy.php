<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use App\Policies\Concerns\AuthorizesProjectAccess;

class ProjectFilePolicy
{
    use AuthorizesProjectAccess;

    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, ProjectFile $file): bool
    {
        return $this->canViewProject($user, $file->project);
    }

    public function create(User $user, Project $project): bool
    {
        return $this->canViewProject($user, $project);
    }

    public function uploadVersion(User $user, ProjectFile $file): bool
    {
        return $this->canViewProject($user, $file->project);
    }

    public function update(User $user, ProjectFile $file): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ProjectFile $file): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, ProjectFile $file): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, ProjectFile $file): bool
    {
        return $user->isAdmin();
    }
}
