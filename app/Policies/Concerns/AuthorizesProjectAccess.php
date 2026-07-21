<?php

namespace App\Policies\Concerns;

use App\Models\Project;
use App\Models\User;

trait AuthorizesProjectAccess
{
    protected function canViewProject(User $user, Project $project): bool
    {
        return $user->isAdmin()
            || ($user->isStaff() && $project->assigned_staff_id === $user->id)
            || ($user->isCustomer() && $project->customer_id === $user->id);
    }

    protected function canWorkOnProject(User $user, Project $project): bool
    {
        return $user->isAdmin() || ($user->isStaff() && $project->assigned_staff_id === $user->id);
    }
}
