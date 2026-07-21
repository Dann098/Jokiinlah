<?php

namespace App\Policies;

use App\Models\ProjectMilestone;
use App\Models\User;
use App\Policies\Concerns\AuthorizesProjectAccess;

class ProjectMilestonePolicy
{
    use AuthorizesProjectAccess;
    public function viewAny(User $user): bool { return $user->is_active; }
    public function view(User $user, ProjectMilestone $item): bool { return $this->canViewProject($user, $item->project); }
    public function create(User $user): bool { return $user->isAdmin() || $user->isStaff(); }
    public function update(User $user, ProjectMilestone $item): bool { return $this->canWorkOnProject($user, $item->project); }
    public function delete(User $user, ProjectMilestone $item): bool { return $this->canWorkOnProject($user, $item->project); }
}
