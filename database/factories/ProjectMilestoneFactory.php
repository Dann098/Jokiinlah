<?php

namespace Database\Factories;

use App\Enums\MilestoneStatus;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectMilestoneFactory extends Factory
{
    public function definition(): array
    {
        return ['project_id' => Project::factory(), 'title' => fake()->sentence(3), 'description' => fake()->sentence(), 'due_date' => now()->addWeek(), 'status' => MilestoneStatus::Pending, 'sort_order' => 1];
    }
}
