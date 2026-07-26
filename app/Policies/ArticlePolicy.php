<?php

namespace App\Policies;

use App\Models\Article;
use App\Models\User;

class ArticlePolicy
{
    public function viewAny(?User $user): bool
    {
        return (bool) $user?->isAdmin();
    }

    public function view(?User $user, Article $item): bool
    {
        return (bool) $user?->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Article $item): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Article $item): bool
    {
        return $user->isAdmin();
    }
}
