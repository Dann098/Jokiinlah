<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $subject): bool
    {
        return $user->isAdmin() || $user->is($subject);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $subject): bool
    {
        return $user->isAdmin() || $user->is($subject);
    }

    public function delete(User $user, User $subject): bool
    {
        return $user->isAdmin() && ! $user->is($subject);
    }

    public function restore(User $user, User $subject): bool
    {
        return false;
    }

    public function forceDelete(User $user, User $subject): bool
    {
        return false;
    }
}
