<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return ['customer_id' => User::factory()->customer(), 'service_id' => Service::factory(), 'project_code' => 'PRJ-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)), 'title' => fake()->sentence(4), 'description' => fake()->paragraph(), 'status' => ProjectStatus::NewRequest, 'progress' => 0, 'start_date' => now(), 'deadline' => now()->addMonth(), 'payment_status' => PaymentStatus::Unpaid, 'retention_until' => now()->addDays(180)];
    }
}
