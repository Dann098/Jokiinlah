<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function viewAny(User $user): bool { return $user->is_active; }
    public function view(User $user, Appointment $item): bool { return $user->isAdmin() || $item->customer_id === $user->id || $item->staff_id === $user->id; }
    public function create(User $user): bool { return $user->isAdmin() || $user->isStaff(); }
    public function update(User $user, Appointment $item): bool { return $user->isAdmin() || $item->staff_id === $user->id; }
    public function delete(User $user, Appointment $item): bool { return $user->isAdmin(); }
}
