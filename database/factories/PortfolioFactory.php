<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PortfolioFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return ['title' => $title, 'slug' => Str::slug($title).'-'.Str::lower(Str::random(4)), 'category' => 'web', 'description' => fake()->paragraph(), 'problem' => fake()->paragraph(), 'solution' => fake()->paragraph(), 'result' => fake()->paragraph(), 'technologies' => ['Laravel', 'Tailwind CSS'], 'gallery' => [], 'is_published' => true];
    }
}
