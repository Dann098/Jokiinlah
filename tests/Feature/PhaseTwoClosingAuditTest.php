<?php

namespace Tests\Feature;

use App\Actions\ProjectFiles\PermanentlyDeleteProjectFile;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use App\Services\FilenameSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class PhaseTwoClosingAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_a_cannot_view_or_download_customer_b_file(): void
    {
        Storage::fake('local');
        $customerA = User::factory()->customer()->create();
        $customerB = User::factory()->customer()->create();
        $projectB = Project::factory()->for($customerB, 'customer')->create();
        $fileB = ProjectFile::factory()->for($projectB)->create(['file_path' => 'projects/b/private.pdf']);
        Storage::disk('local')->put($fileB->file_path, 'customer-b-private-content');

        $this->assertFalse($customerA->can('view', $fileB));
        $this->actingAs($customerA)->get(route('project-files.download', $fileB))->assertForbidden();
    }

    public function test_customer_a_cannot_upload_new_version_for_customer_b_file(): void
    {
        Storage::fake('local');
        $customerA = User::factory()->customer()->create();
        $customerB = User::factory()->customer()->create();
        $fileB = ProjectFile::factory()->for(Project::factory()->for($customerB, 'customer'))->create();

        $this->actingAs($customerA)->post(route('project-files.versions.store', $fileB), [
            'file' => UploadedFile::fake()->create('versi-baru.pdf', 10, 'application/pdf'),
            'category' => 'dokumen_awal',
        ])->assertForbidden();

        $this->assertDatabaseCount('project_files', 1);
    }

    public function test_unassigned_staff_cannot_access_project_or_file(): void
    {
        Storage::fake('local');
        $assignedStaff = User::factory()->staff()->create();
        $otherStaff = User::factory()->staff()->create();
        $project = Project::factory()->create(['assigned_staff_id' => $assignedStaff->id]);
        $file = ProjectFile::factory()->for($project)->create(['file_path' => 'projects/staff/private.pdf']);
        Storage::disk('local')->put($file->file_path, 'assigned-staff-only');

        $this->assertFalse($otherStaff->can('view', $project));
        $this->assertFalse($otherStaff->can('view', $file));
        $this->actingAs($otherStaff)->get(route('project-files.download', $file))->assertForbidden();
        $this->actingAs($otherStaff)->post(route('project-files.versions.store', $file), [
            'file' => UploadedFile::fake()->create('unauthorized.pdf', 10, 'application/pdf'),
            'category' => 'hasil',
        ])->assertForbidden();
    }

    public function test_soft_deleted_file_cannot_be_downloaded(): void
    {
        Storage::fake('local');
        $customer = User::factory()->customer()->create();
        $project = Project::factory()->for($customer, 'customer')->create();
        $file = ProjectFile::factory()->for($project)->create(['file_path' => 'projects/deleted/file.pdf']);
        Storage::disk('local')->put($file->file_path, 'soft-deleted-content');
        $file->delete();

        $this->actingAs($customer)->get('/project-files/'.$file->id.'/download')->assertNotFound();
    }

    public function test_customer_cannot_manipulate_protected_identity_fields_during_version_upload(): void
    {
        Storage::fake('local');
        $customer = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $project = Project::factory()->for($customer, 'customer')->create();
        $current = ProjectFile::factory()->for($project)->create(['uploaded_by' => $other->id]);

        $this->actingAs($customer)->from('/dashboard')->post(route('project-files.versions.store', $current), [
            'file' => UploadedFile::fake()->create('safe-version.pdf', 10, 'application/pdf'),
            'category' => 'hasil',
            'uploaded_by' => $other->id,
            'version' => 999,
            'document_uuid' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'customer_id' => $other->id,
            'role' => UserRole::Admin->value,
            'is_active' => false,
        ])->assertRedirect('/dashboard');

        $newVersion = ProjectFile::query()->whereKeyNot($current->id)->firstOrFail();
        $this->assertSame($customer->id, $newVersion->uploaded_by);
        $this->assertSame(2, $newVersion->version);
        $this->assertSame($current->document_uuid, $newVersion->document_uuid);
        $this->assertSame($customer->id, $project->refresh()->customer_id);
        $this->assertSame(UserRole::Customer, $customer->refresh()->role);
        $this->assertTrue($customer->is_active);
    }

    public function test_uploading_new_version_does_not_overwrite_old_file(): void
    {
        Storage::fake('local');
        $customer = User::factory()->customer()->create();
        $project = Project::factory()->for($customer, 'customer')->create();
        $current = ProjectFile::factory()->for($project)->create(['file_path' => 'projects/versioning/original.pdf']);
        Storage::disk('local')->put($current->file_path, 'original-file-content');

        $this->actingAs($customer)->post(route('project-files.versions.store', $current), [
            'file' => UploadedFile::fake()->create('revision.pdf', 10, 'application/pdf'),
            'category' => 'hasil',
        ])->assertSessionHasNoErrors();

        $newVersion = ProjectFile::query()->whereKeyNot($current->id)->firstOrFail();
        $this->assertNotSame($current->file_path, $newVersion->file_path);
        Storage::disk('local')->assertExists($current->file_path);
        Storage::disk('local')->assertExists($newVersion->file_path);
        $this->assertSame('original-file-content', Storage::disk('local')->get($current->file_path));
        $this->assertDatabaseCount('project_files', 2);
    }

    public function test_filename_is_sanitized_and_content_disposition_has_no_header_injection(): void
    {
        Storage::fake('local');
        $sanitizer = app(FilenameSanitizer::class);
        $this->assertSame('dokumen', $sanitizer->sanitize('..'.chr(13).chr(10)));
        $this->assertLessThanOrEqual(180, mb_strlen($sanitizer->sanitize(str_repeat('a', 250).'.PDF')));

        $customer = User::factory()->customer()->create();
        $project = Project::factory()->for($customer, 'customer')->create();
        $file = ProjectFile::factory()->for($project)->create([
            'original_name' => '../laporan'.chr(13).chr(10).'X-Evil: injected.pdf',
            'file_path' => 'projects/headers/file.pdf',
        ]);
        Storage::disk('local')->put($file->file_path, 'safe-download');

        $response = $this->actingAs($customer)->get(route('project-files.download', $file))->assertOk();
        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertStringNotContainsString(chr(13), $disposition);
        $this->assertStringNotContainsString(chr(10), $disposition);
        $this->assertStringNotContainsString('X-Evil:', $disposition);
    }

    public function test_permanent_delete_has_no_route_and_checks_retention(): void
    {
        $hasPurgeRoute = collect(Route::getRoutes()->getRoutes())->contains(
            fn ($route): bool => str_contains($route->getActionName(), PermanentlyDeleteProjectFile::class),
        );
        $this->assertFalse($hasPurgeRoute);

        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $file = ProjectFile::factory()->create([
            'file_path' => 'projects/retention/future.pdf',
            'retention_until' => now()->addDay(),
        ]);
        Storage::disk('local')->put($file->file_path, 'retained-content');
        $file->delete();

        try {
            app(PermanentlyDeleteProjectFile::class)->execute($file, $admin, 'Permintaan purge audit.');
            $this->fail('Purge sebelum retensi berakhir seharusnya ditolak.');
        } catch (ValidationException) {
            $this->assertNotNull(ProjectFile::withTrashed()->find($file->id));
            Storage::disk('local')->assertExists($file->file_path);
        }
    }

    public function test_storage_failure_keeps_soft_deleted_file_record(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $file = ProjectFile::factory()->create([
            'file_path' => 'projects/retention/missing.pdf',
            'retention_until' => now()->subDay(),
        ]);
        $file->delete();

        try {
            app(PermanentlyDeleteProjectFile::class)->execute($file, $admin, 'Retensi telah berakhir.');
            $this->fail('Storage yang gagal seharusnya mempertahankan record.');
        } catch (RuntimeException) {
            $this->assertNotNull(ProjectFile::withTrashed()->find($file->id));
            $this->assertDatabaseCount('activity_logs', 0);
        }
    }
}
