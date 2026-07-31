<?php

namespace App\Policies;

use App\Enums\ConsultationStatus;
use App\Models\Consultation;
use App\Models\User;

class ConsultationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Consultation $consultation): bool
    {
        return $user->isAdmin();
    }

    public function create(?User $user): bool
    {
        return true;
    }

    public function viewRequest(User $user, Consultation $consultation): bool
    {
        return $user->is_active
            && $user->isCustomer()
            && $consultation->user_id === $user->id;
    }

    public function updateRequest(User $user, Consultation $consultation): bool
    {
        return $this->viewRequest($user, $consultation)
            && $consultation->status === ConsultationStatus::Contacted
            && ! $consultation->project()->exists();
    }

    public function update(User $user, Consultation $consultation): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Consultation $consultation): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Consultation $consultation): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Consultation $consultation): bool
    {
        return false;
    }
}
