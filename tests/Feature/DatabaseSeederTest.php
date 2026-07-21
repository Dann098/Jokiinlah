<?php

namespace Tests\Feature;

use App\Models\ProjectFile;
use App\Models\Service;
use App\Models\Testimonial;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_is_complete_and_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(8, Service::query()->count());
        $this->assertSame(8, User::query()->count());
        $this->assertSame(5, Testimonial::query()->where('is_demo', true)->count());
        $this->assertSame(2, ProjectFile::query()->where('document_uuid', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa')->count());
    }

    public function test_demo_seeder_is_rejected_in_production(): void
    {
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn (): string => 'production');

        try {
            (new DatabaseSeeder)->run();
            $this->fail('Demo seeder seharusnya ditolak pada production.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('production', $exception->getMessage());
            $this->assertDatabaseCount('users', 0);
        } finally {
            app()->detectEnvironment(fn (): string => $originalEnvironment);
        }
    }
}
