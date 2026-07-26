<?php

namespace Tests\Feature;

use App\Actions\ProjectFiles\CreateProjectFileRecord;
use App\Actions\ProjectFiles\CreateProjectFileVersion;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use App\Services\PrivateProjectFileStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class CustomerPortalFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_list_and_download_only_owned_nested_files(): void
    {
        Storage::fake('local');
        $customer = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $project = Project::factory()->for($customer, 'customer')->create();
        $foreignProject = Project::factory()->for($other, 'customer')->create();
        $file = ProjectFile::factory()->for($project)->create(['file_path' => 'projects/owned/file']);
        $foreignFile = ProjectFile::factory()->for($foreignProject)->create(['original_name' => 'asing.pdf']);
        Storage::disk('local')->put($file->file_path, 'owned-private-content');

        $this->actingAs($customer)->get(route('customer.projects.files.index', $project))
            ->assertOk()
            ->assertSee($file->original_name)
            ->assertDontSee('asing.pdf');

        $this->actingAs($customer)
            ->get(route('customer.projects.files.download', [$project, $file]))
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->actingAs($customer)
            ->get(route('customer.projects.files.download', [$foreignProject, $foreignFile]))
            ->assertForbidden();

        $this->actingAs($customer)
            ->get('/dashboard/proyek/'.$project->id.'/file/'.$foreignFile->id.'/download')
            ->assertNotFound();
    }

    public function test_new_document_upload_is_private_uuid_named_checksummed_and_server_owned(): void
    {
        Storage::fake('local');
        $customer = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $project = Project::factory()->for($customer, 'customer')->create();
        $payload = [
            'file' => UploadedFile::fake()->create('dokumen aman.pdf', 10, 'application/pdf'),
            'category' => 'dokumen_awal',
            'description' => 'Dokumen kebutuhan pelanggan.',
            'project_id' => Project::factory()->for($other, 'customer')->create()->id,
            'uploaded_by' => $other->id,
            'version' => 999,
            'document_uuid' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'checksum' => 'forged',
            'status' => 'completed',
            'progress' => 100,
        ];

        $this->actingAs($customer)
            ->post(route('customer.projects.files.store', $project), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $file = ProjectFile::query()->sole();
        $this->assertSame($project->id, $file->project_id);
        $this->assertSame($customer->id, $file->uploaded_by);
        $this->assertSame(1, $file->version);
        $this->assertNotSame('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', $file->document_uuid);
        $this->assertSame(64, strlen((string) $file->checksum));
        $this->assertMatchesRegularExpression('#^projects/[0-9a-f-]{36}/[0-9a-f-]{36}$#', $file->file_path);
        $this->assertSame($file->stored_name, basename($file->file_path));
        Storage::disk('local')->assertExists($file->file_path);
        $this->assertDatabaseHas('activity_logs', ['action' => 'project_file.uploaded', 'user_id' => $customer->id]);
    }

    public function test_new_version_preserves_document_and_old_file_while_server_generates_version(): void
    {
        Storage::fake('local');
        $customer = User::factory()->customer()->create();
        $project = Project::factory()->for($customer, 'customer')->create();
        $old = ProjectFile::factory()->for($project)->create([
            'version' => 7,
            'file_path' => 'projects/existing/original',
        ]);
        Storage::disk('local')->put($old->file_path, 'old-content');

        $this->actingAs($customer)->post(route('customer.projects.files.versions.store', [$project, $old]), [
            'file' => UploadedFile::fake()->create('versi-delapan.pdf', 10, 'application/pdf'),
            'category' => 'revisi',
            'version' => 999,
            'document_uuid' => 'forged',
            'uploaded_by' => User::factory()->create()->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $new = ProjectFile::query()->whereKeyNot($old->id)->sole();
        $this->assertSame(8, $new->version);
        $this->assertSame($old->document_uuid, $new->document_uuid);
        $this->assertSame($customer->id, $new->uploaded_by);
        $this->assertNotSame($old->file_path, $new->file_path);
        Storage::disk('local')->assertExists($old->file_path);
        Storage::disk('local')->assertExists($new->file_path);
        $this->assertSame('old-content', Storage::disk('local')->get($old->file_path));
    }

    public function test_upload_rejects_executable_mismatch_double_extension_missing_extension_and_oversize(): void
    {
        Storage::fake('local');
        $customer = User::factory()->customer()->create();
        $project = Project::factory()->for($customer, 'customer')->create();

        $invalidFiles = [
            UploadedFile::fake()->create('payload.php', 2, 'application/x-httpd-php'),
            UploadedFile::fake()->create('dokumen.pdf', 10, 'text/plain'),
            UploadedFile::fake()->create('dokumen.php.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->create('dokumen', 10, 'application/pdf'),
            UploadedFile::fake()->create('besar.pdf', 20481, 'application/pdf'),
        ];

        foreach ($invalidFiles as $file) {
            $this->actingAs($customer)->post(route('customer.projects.files.store', $project), [
                'file' => $file,
                'category' => 'dokumen_awal',
            ])->assertSessionHasErrors('file');
        }

        $this->assertDatabaseCount('project_files', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_database_failure_cleans_stored_file_and_missing_physical_file_is_safe(): void
    {
        Storage::fake('local');
        $customer = User::factory()->customer()->create();
        $project = Project::factory()->for($customer, 'customer')->create();
        $action = $this->createMock(CreateProjectFileRecord::class);
        $action->method('execute')->willThrowException(new RuntimeException('Simulasi database gagal.'));
        $this->app->instance(CreateProjectFileRecord::class, $action);
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($customer)->post(route('customer.projects.files.store', $project), [
                'file' => UploadedFile::fake()->create('bersih.pdf', 10, 'application/pdf'),
                'category' => 'dokumen_awal',
            ]);
            $this->fail('Kegagalan database seharusnya diteruskan.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulasi database gagal.', $exception->getMessage());
        }

        $this->assertDatabaseCount('project_files', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());

        $this->withExceptionHandling();
        $missing = ProjectFile::factory()->for($project)->create(['file_path' => 'projects/missing/file']);
        $this->actingAs($customer)
            ->get(route('customer.projects.files.download', [$project, $missing]))
            ->assertNotFound();
    }

    public function test_storage_failure_does_not_create_record_and_version_sequence_remains_unique(): void
    {
        Storage::fake('local');
        $customer = User::factory()->customer()->create();
        $project = Project::factory()->for($customer, 'customer')->create();
        $storage = $this->createMock(PrivateProjectFileStorage::class);
        $storage->method('store')->willThrowException(new RuntimeException('Simulasi storage gagal.'));
        $this->app->instance(PrivateProjectFileStorage::class, $storage);
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($customer)->post(route('customer.projects.files.store', $project), [
                'file' => UploadedFile::fake()->create('gagal.pdf', 10, 'application/pdf'),
                'category' => 'dokumen_awal',
            ]);
            $this->fail('Kegagalan storage seharusnya diteruskan.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulasi storage gagal.', $exception->getMessage());
        }

        $this->assertDatabaseCount('project_files', 0);

        $current = ProjectFile::factory()->for($project)->create(['version' => 1]);
        $metadata = [
            'category' => 'revisi',
            'original_name' => 'version.pdf',
            'file_path' => 'projects/test/version',
            'file_type' => 'application/pdf',
            'file_size' => 100,
            'checksum' => hash('sha256', 'version'),
        ];
        app(CreateProjectFileVersion::class)->execute($current, $customer, $metadata);
        app(CreateProjectFileVersion::class)->execute($current, $customer, $metadata);

        $this->assertSame(
            [1, 2, 3],
            ProjectFile::query()->where('document_uuid', $current->document_uuid)->orderBy('version')->pluck('version')->all(),
        );
        $this->assertDatabaseCount('project_files', 3);
    }

    public function test_soft_deleted_file_and_customer_delete_routes_are_unavailable(): void
    {
        $customer = User::factory()->customer()->create();
        $project = Project::factory()->for($customer, 'customer')->create();
        $file = ProjectFile::factory()->for($project)->create();
        $file->delete();

        $this->actingAs($customer)
            ->get('/dashboard/proyek/'.$project->id.'/file/'.$file->id.'/download')
            ->assertNotFound();

        $this->assertFalse($customer->can('delete', $file));
        $this->assertFalse(collect(Route::getRoutes()->getRoutes())->contains(
            fn ($route): bool => in_array('DELETE', $route->methods(), true)
                && str_contains($route->uri(), '/file'),
        ));
    }
}
