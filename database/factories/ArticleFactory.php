<?php

namespace Database\Factories;

use App\Enums\ArticleCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ArticleFactory extends Factory
{
    public function definition(): array { $title = fake()->unique()->sentence(6); return ['author_id' => User::factory()->admin(), 'title' => $title, 'slug' => Str::slug($title).'-'.Str::lower(Str::random(4)), 'excerpt' => fake()->sentence(), 'content' => fake()->paragraphs(5, true), 'category' => fake()->randomElement(ArticleCategory::cases()), 'is_published' => true, 'published_at' => now()]; }
}
