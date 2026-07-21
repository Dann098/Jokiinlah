<?php

namespace App\Policies;
use App\Models\Portfolio;
use App\Models\User;
class PortfolioPolicy
{
    public function viewAny(?User $user): bool { return true; }
    public function view(?User $user, Portfolio $item): bool { return true; }
    public function create(User $user): bool { return $user->isAdmin(); }
    public function update(User $user, Portfolio $item): bool { return $user->isAdmin(); }
    public function delete(User $user, Portfolio $item): bool { return $user->isAdmin(); }
}
