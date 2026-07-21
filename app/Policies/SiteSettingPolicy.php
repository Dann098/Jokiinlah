<?php

namespace App\Policies;
use App\Models\SiteSetting;
use App\Models\User;
class SiteSettingPolicy
{
    public function viewAny(User $user): bool { return $user->isAdmin(); }
    public function view(User $user, SiteSetting $item): bool { return $user->isAdmin(); }
    public function create(User $user): bool { return $user->isAdmin(); }
    public function update(User $user, SiteSetting $item): bool { return $user->isAdmin(); }
    public function delete(User $user, SiteSetting $item): bool { return $user->isAdmin(); }
}
