<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class FaqFactory extends Factory
{
    public function definition(): array { return ['question' => fake()->sentence().'?', 'answer' => fake()->paragraph(), 'category' => 'umum', 'sort_order' => 0, 'is_active' => true]; }
}
