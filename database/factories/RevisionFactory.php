<?php

namespace Database\Factories;

use App\Enums\RevisionPriority;
use App\Enums\RevisionStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RevisionFactory extends Factory
{
    public function definition(): array
    {
        return ['project_id' => Project::factory(), 'submitted_by' => User::factory()->customer(), 'title' => fake()->sentence(4), 'description' => fake()->paragraph(), 'section_reference' => 'Bab 3', 'priority' => RevisionPriority::Normal, 'status' => RevisionStatus::Submitted, 'retention_until' => now()->addDays(180)];
    }
}
