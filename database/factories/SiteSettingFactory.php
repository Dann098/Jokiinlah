<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SiteSettingFactory extends Factory
{
    public function definition(): array
    {
        return ['key' => 'setting_'.Str::lower(Str::random(10)), 'value' => fake()->sentence(), 'type' => 'string', 'group' => 'general', 'is_public' => false];
    }
}
