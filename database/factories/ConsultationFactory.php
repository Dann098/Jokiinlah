<?php

namespace Database\Factories;

use App\Enums\ConsultationStatus;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ConsultationFactory extends Factory
{
    public function definition(): array
    {
        return ['service_id' => Service::factory(), 'request_code' => 'CNS-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)), 'name' => fake()->name(), 'email' => fake()->safeEmail(), 'phone' => '+628'.fake()->numerify('##########'), 'project_title' => fake()->sentence(4), 'description' => fake()->paragraph(), 'deadline' => now()->addWeeks(4), 'technology' => 'Laravel', 'budget' => fake()->numberBetween(2000000, 15000000), 'status' => ConsultationStatus::New, 'privacy_accepted_at' => now(), 'privacy_policy_version' => '1.0', 'terms_version' => '1.0', 'source' => 'website', 'retention_until' => now()->addDays(180)];
    }
}
