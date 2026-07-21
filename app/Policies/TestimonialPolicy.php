<?php

namespace App\Policies;
use App\Models\Testimonial;
use App\Models\User;
class TestimonialPolicy
{
    public function viewAny(?User $user): bool { return true; }
    public function view(?User $user, Testimonial $item): bool { return true; }
    public function create(User $user): bool { return $user->isAdmin(); }
    public function update(User $user, Testimonial $item): bool { return $user->isAdmin(); }
    public function delete(User $user, Testimonial $item): bool { return $user->isAdmin(); }
}
