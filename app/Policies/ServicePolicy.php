<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

class ServicePolicy
{
    public function viewAny(?User $user): bool
    {
        return (bool) $user?->isAdmin();
    }

    public function view(?User $user, Service $item): bool
    {
        return (bool) $user?->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Service $item): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Service $item): bool
    {
        return $user->isAdmin();
    }
}
