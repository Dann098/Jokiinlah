<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConversionCleanupCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = storage_path('framework/testing/conversion-cleanup-'.bin2hex(random_bytes(6)));
        config([
            'converter.temporary_directory' => $this->root,
            'converter.cleanup_after_minutes' => 120,
        ]);
        File::ensureDirectoryExists($this->root);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        File::deleteDirectory($this->root.'-outside');
        parent::tearDown();
    }

    public function test_cleanup_deletes_only_stale_uuid_workspaces_inside_the_configured_root(): void
    {
        $old = $this->workspace(now()->subHours(3)->timestamp);
        $recent = $this->workspace(now()->subMinutes(30)->timestamp);
        $invalid = $this->root.DIRECTORY_SEPARATOR.'not-a-uuid';
        $outside = $this->root.'-outside';
        File::ensureDirectoryExists($invalid);
        File::ensureDirectoryExists($outside);

        $this->artisan('jokiinlah:conversion-cleanup')->assertSuccessful();

        $this->assertDirectoryDoesNotExist($old);
        $this->assertDirectoryExists($recent);
        $this->assertDirectoryExists($invalid);
        $this->assertDirectoryExists($outside);
    }

    public function test_dry_run_keeps_stale_workspaces_and_command_is_scheduled_hourly(): void
    {
        $old = $this->workspace(now()->subHours(3)->timestamp);

        $this->artisan('jokiinlah:conversion-cleanup --dry-run')
            ->expectsOutputToContain('dry-run')
            ->assertSuccessful();

        $this->assertDirectoryExists($old);
        $event = collect(Schedule::events())
            ->first(fn ($event): bool => str_contains($event->command ?? '', 'jokiinlah:conversion-cleanup'));
        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);
    }

    private function workspace(int $timestamp): string
    {
        $path = $this->root.DIRECTORY_SEPARATOR.Str::uuid();
        File::ensureDirectoryExists($path);
        file_put_contents($path.DIRECTORY_SEPARATOR.'output.pdf', '%PDF-');
        touch($path, $timestamp);

        return $path;
    }
}
