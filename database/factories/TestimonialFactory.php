<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TestimonialFactory extends Factory
{
    public function definition(): array { return ['customer_name' => fake()->name(), 'customer_role' => 'Mahasiswa', 'content' => fake()->paragraph(), 'rating' => fake()->numberBetween(4, 5), 'is_published' => true, 'is_demo' => true]; }
}
