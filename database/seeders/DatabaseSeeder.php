<?php

namespace Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Demo seeder dilarang berjalan pada environment production.');
        }

        Model::unguarded(fn () => $this->call([
            UserSeeder::class,
            ServiceSeeder::class,
            PortfolioSeeder::class,
            ArticleSeeder::class,
            TestimonialSeeder::class,
            FaqSeeder::class,
            ProjectSeeder::class,
            SiteSettingSeeder::class,
            ActivityLogSeeder::class,
        ]));
    }
}
