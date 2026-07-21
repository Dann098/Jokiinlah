<?php

namespace Database\Factories;

use App\Enums\ServiceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ServiceFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Konsultasi Penelitian', 'Analisis Data', 'Pengembangan Web', 'Aplikasi Mobile']).' '.fake()->unique()->numerify('##');
        return ['name' => $name, 'slug' => Str::slug($name), 'category' => fake()->randomElement(ServiceCategory::cases()), 'short_description' => fake()->sentence(), 'description' => fake()->paragraphs(2, true), 'features' => ['Konsultasi', 'Dokumentasi'], 'technologies' => ['Laravel'], 'icon' => 'briefcase', 'is_active' => true, 'sort_order' => 0];
    }
}
