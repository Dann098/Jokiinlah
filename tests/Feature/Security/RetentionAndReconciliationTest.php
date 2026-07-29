<?php

namespace Tests\Feature\Security;

use App\Enums\PurgeStatus;
use App\Models\ProjectFile;
use App\Services\Reconciliation\FileReconciler;
use App\Services\Retention\RetentionEvaluator;
use App\Services\Retention\TwoPhasePurger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RetentionAndReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_retention_evaluation_is_dry_run_safe_and_only_marks_expired_deleted_records(): void
    {
        $expired = $this->trashedFile(now()->subDay());
        $future = $this->trashedFile(now()->addDay());
        $active = ProjectFile::factory()->create(['retention_until' => now()->subDay()]);

        $dryRun = app(RetentionEvaluator::class)->evaluate(100, true);
        $this->assertSame(1, $dryRun['found']);
        $this->assertSame(PurgeStatus::Eligible, $expired->fresh()->purge_status);

        $applied = app(RetentionEvaluator::class)->evaluate(100, false);
        $this->assertSame(1, $applied['updated']);
        $this->assertSame(PurgeStatus::Pending, $expired->fresh()->purge_status);
        $this->assertSame(PurgeStatus::Eligible, $future->fresh()->purge_status);
        $this->assertSame(PurgeStatus::Eligible, $active->fresh()->purge_status);
    }

    public function test_two_phase_purge_removes_private_file_then_record_and_is_idempotent(): void
    {
        Storage::fake('local');
        $file = $this->trashedFile(now()->subDay(), [
            'file_path' => 'projects/test/document',
            'purge_status' => PurgeStatus::Pending,
            'purge_pending_at' => now(),
        ]);
        Storage::disk('local')->put($file->file_path, 'private document');

        $first = app(TwoPhasePurger::class)->purge(50, false);
        $this->assertSame(1, $first['physical_deleted']);
        $this->assertSame(1, $first['purged']);
        Storage::disk('local')->assertMissing($file->file_path);
        $this->assertNull(ProjectFile::withTrashed()->find($file->id));

        $second = app(TwoPhasePurger::class)->purge(50, false);
        $this->assertSame(0, $second['found']);
    }

    public function test_reconciliation_reports_missing_checksum_and_orphans_without_auto_delete(): void
    {
        Storage::fake('local');
        $file = ProjectFile::factory()->create([
            'file_path' => 'projects/test/recorded',
            'file_size' => 5,
            'checksum' => hash('sha256', 'wrong'),
        ]);
        Storage::disk('local')->put($file->file_path, 'valid');
        Storage::disk('local')->put('projects/test/orphan', 'orphan');

        $result = app(FileReconciler::class)->reconcile(100, true, false, false);

        $this->assertGreaterThanOrEqual(2, $result['mismatches']);
        $this->assertTrue(collect($result['issues'])->contains(
            fn (string $issue): bool => str_starts_with($issue, 'checksum_mismatch:'),
        ));
        $this->assertTrue(collect($result['issues'])->contains(
            fn (string $issue): bool => str_starts_with($issue, 'orphan_file:'),
        ));
        Storage::disk('local')->assertExists('projects/test/orphan');
    }

    public function test_reconciliation_can_only_repair_provable_missing_physical_state(): void
    {
        Storage::fake('local');
        $file = $this->trashedFile(now()->subDay(), [
            'file_path' => 'projects/test/missing',
            'purge_status' => PurgeStatus::Pending,
            'purge_pending_at' => now(),
        ]);

        $result = app(FileReconciler::class)->reconcile(10, false, true, false);

        $this->assertSame(1, $result['repaired']);
        $this->assertSame(PurgeStatus::PhysicalDeleted, $file->fresh()->purge_status);
        $this->assertNotNull($file->fresh()->physical_deleted_at);
    }

    public function test_reconciliation_isolates_unavailable_storage_per_record(): void
    {
        ProjectFile::factory()->create([
            'disk' => 'disk-that-is-not-configured',
            'file_path' => 'projects/test/unavailable',
        ]);
        $valid = ProjectFile::factory()->create([
            'file_path' => 'projects/test/available',
            'file_size' => 5,
        ]);
        Storage::fake('local');
        Storage::disk('local')->put($valid->file_path, 'valid');

        $result = app(FileReconciler::class)->reconcile(10, false, false, false);

        $this->assertGreaterThanOrEqual(2, $result['checked']);
        $this->assertTrue(collect($result['issues'])->contains(
            fn (string $issue): bool => str_starts_with($issue, 'storage_or_metadata_unavailable:'),
        ));
    }

    private function trashedFile(mixed $retentionUntil, array $attributes = []): ProjectFile
    {
        $file = ProjectFile::factory()->create(array_merge([
            'retention_until' => $retentionUntil,
        ], $attributes));
        $file->delete();

        return $file;
    }
}
