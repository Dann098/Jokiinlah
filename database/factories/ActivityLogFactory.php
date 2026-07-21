<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActivityLogFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'action' => 'factory.event', 'description' => 'Catatan audit yang dibuat factory untuk kebutuhan pengujian.', 'metadata' => ['source' => 'factory'], 'ip_address' => '127.0.0.1', 'user_agent' => 'PHPUnit'];
    }
}
