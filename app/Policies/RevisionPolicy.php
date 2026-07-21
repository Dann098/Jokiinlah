<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Revision;
use App\Models\User;
use App\Policies\Concerns\AuthorizesProjectAccess;

class RevisionPolicy
{
    use AuthorizesProjectAccess;
    public function viewAny(User $user): bool { return $user->is_active; }
    public function view(User $user, Revision $item): bool { return $this->canViewProject($user, $item->project); }
    public function create(User $user, Project $project): bool { return $this->canViewProject($user, $project); }
    public function update(User $user, Revision $item): bool { return $this->canWorkOnProject($user, $item->project); }
    public function delete(User $user, Revision $item): bool { return $user->isAdmin(); }
    public function restore(User $user, Revision $item): bool { return $user->isAdmin(); }
    public function forceDelete(User $user, Revision $item): bool { return $user->isAdmin(); }
}
