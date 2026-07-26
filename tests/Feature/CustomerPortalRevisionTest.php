<?php

namespace Tests\Feature;

use App\Enums\RevisionPriority;
use App\Enums\RevisionStatus;
use App\Models\Project;
use App\Models\Revision;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class CustomerPortalRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_revision_with_private_attachment_but_not_internal_fields(): void
    {
        Storage::fake('local');
        $customer = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $project = Project::factory()->for($customer, 'customer')->create();

        $response = $this->actingAs($customer)->post(route('customer.projects.revisions.store', $project), [
            'title' => 'Perbaiki alur review',
            'description' => 'Mohon tambahkan konfirmasi sebelum proses review diselesaikan.',
            'section_reference' => 'Halaman review',
            'attachment' => UploadedFile::fake()->create('contoh.pdf', 10, 'application/pdf'),
            'project_id' => Project::factory()->for($other, 'customer')->create()->id,
            'submitted_by' => $other->id,
            'status' => RevisionStatus::Closed->value,
            'priority' => RevisionPriority::Urgent->value,
            'admin_response' => 'forged',
            'internal_notes' => 'forged',
        ]);

        $revision = Revision::query()->sole();
        $response->assertRedirect(route('customer.projects.revisions.show', [$project, $revision]));
        $this->assertSame($project->id, $revision->project_id);
        $this->assertSame($customer->id, $revision->submitted_by);
        $this->assertSame(RevisionStatus::Submitted, $revision->status);
        $this->assertSame(RevisionPriority::Normal, $revision->priority);
        $this->assertNull($revision->admin_response);
        $this->assertSame(64, strlen((string) $revision->attachment_checksum));
        $this->assertMatchesRegularExpression('#^revisions/[0-9a-f-]{36}/[0-9a-f-]{36}$#', $revision->attachment_path);
        Storage::disk('local')->assertExists($revision->attachment_path);
        $this->assertDatabaseHas('activity_logs', ['action' => 'revision.submitted', 'user_id' => $customer->id]);

        $this->actingAs($customer)
            ->get(route('customer.projects.revisions.attachment', [$project, $revision]))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_revision_list_detail_and_attachment_are_owner_scoped(): void
    {
        Storage::fake('local');
        $customer = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $project = Project::factory()->for($customer, 'customer')->create();
        $foreignProject = Project::factory()->for($other, 'customer')->create();
        $revision = Revision::factory()->for($project)->for($customer, 'submitter')->create(['title' => 'Revisi sendiri']);
        $foreign = Revision::factory()->for($foreignProject)->for($other, 'submitter')->create(['title' => 'REVISI ASING']);

        $this->actingAs($customer)->get(route('customer.projects.revisions.index', $project))
            ->assertOk()
            ->assertSee('Revisi sendiri')
            ->assertDontSee('REVISI ASING');
        $this->actingAs($customer)->get(route('customer.projects.revisions.show', [$project, $revision]))->assertOk();
        $this->actingAs($customer)->get(route('customer.projects.revisions.show', [$foreignProject, $foreign]))->assertForbidden();
        $this->actingAs($customer)
            ->get('/dashboard/proyek/'.$project->id.'/revisi/'.$foreign->id)
            ->assertNotFound();
    }

    public function test_revision_validation_rejects_unsafe_attachment_and_short_description(): void
    {
        Storage::fake('local');
        $customer = User::factory()->customer()->create();
        $project = Project::factory()->for($customer, 'customer')->create();

        $this->actingAs($customer)->post(route('customer.projects.revisions.store', $project), [
            'title' => 'x',
            'description' => 'pendek',
            'attachment' => UploadedFile::fake()->create('payload.php.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors(['title', 'description', 'attachment']);

        $this->assertDatabaseCount('revisions', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_revision_database_failure_cleans_private_attachment(): void
    {
        Storage::fake('local');
        $customer = User::factory()->customer()->create();
        $project = Project::factory()->for($customer, 'customer')->create();
        $logger = $this->createMock(ActivityLogger::class);
        $logger->method('log')->willThrowException(new RuntimeException('Simulasi audit gagal.'));
        $this->app->instance(ActivityLogger::class, $logger);
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($customer)->post(route('customer.projects.revisions.store', $project), [
                'title' => 'Revisi dengan rollback',
                'description' => 'Permintaan ini harus dibatalkan jika audit gagal.',
                'attachment' => UploadedFile::fake()->create('rollback.pdf', 10, 'application/pdf'),
            ]);
            $this->fail('Kegagalan audit seharusnya membatalkan transaksi.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulasi audit gagal.', $exception->getMessage());
        }

        $this->assertDatabaseCount('revisions', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
