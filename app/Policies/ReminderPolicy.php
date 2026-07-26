<?php

namespace App\Policies;

use App\Models\Reminder;
use App\Models\User;

class ReminderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Reminder $item): bool
    {
        return $user->isAdmin()
            || ($user->isCustomer() && $item->user_id === $user->id && $item->is_customer_visible)
            || ($user->isStaff() && $item->project?->assigned_staff_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isStaff();
    }

    public function update(User $user, Reminder $item): bool
    {
        return $user->isAdmin()
            || ($user->isStaff() && $item->project?->assigned_staff_id === $user->id);
    }

    public function delete(User $user, Reminder $item): bool
    {
        return $user->isAdmin()
            || ($user->isStaff() && $item->project?->assigned_staff_id === $user->id);
    }
}
