<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReminderFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'title' => fake()->sentence(4), 'description' => fake()->sentence(), 'reminder_date' => now()->addDays(3), 'is_completed' => false];
    }
}
