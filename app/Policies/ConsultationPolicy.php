<?php

namespace App\Policies;

use App\Models\Consultation;
use App\Models\User;

class ConsultationPolicy
{
    public function viewAny(User $user): bool { return $user->isAdmin(); }
    public function view(User $user, Consultation $consultation): bool { return $user->isAdmin(); }
    public function create(?User $user): bool { return true; }
    public function update(User $user, Consultation $consultation): bool { return $user->isAdmin(); }
    public function delete(User $user, Consultation $consultation): bool { return $user->isAdmin(); }
    public function restore(User $user, Consultation $consultation): bool { return $user->isAdmin(); }
    public function forceDelete(User $user, Consultation $consultation): bool { return $user->isAdmin(); }
}
