<?php

namespace Tests\Feature;

use App\Actions\Consultations\ConvertConsultationToProject;
use App\Actions\Consultations\LinkConsultationToCustomer;
use App\Actions\ProjectFiles\CreateProjectFileVersion;
use App\Actions\Projects\UpdateProjectProgress;
use App\Actions\Projects\UpdateProjectStatus;
use App\Enums\ConsultationStatus;
use App\Enums\ProjectStatus;
use App\Models\ActivityLog;
use App\Models\Consultation;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class DomainActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_consultation_must_link_to_verified_matching_customer_before_conversion(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create(['email' => 'guest@example.com']);
        $consultation = Consultation::factory()->create(['email' => 'guest@example.com', 'status' => ConsultationStatus::Reviewed]);

        app(LinkConsultationToCustomer::class)->execute($consultation, $customer, $admin);
        $project = app(ConvertConsultationToProject::class)->execute($consultation->refresh(), $admin);

        $this->assertSame($customer->id, $project->customer_id);
        $this->assertSame(ProjectStatus::NewRequest, $project->status);
        $this->assertSame(ConsultationStatus::Converted, $consultation->refresh()->status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'consultation.converted']);
    }

    public function test_staff_follows_normal_transition_and_admin_override_requires_reason(): void
    {
        $staff = User::factory()->staff()->create();
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['assigned_staff_id' => $staff->id]);
        $action = app(UpdateProjectStatus::class);

        $action->execute($project, ProjectStatus::Consultation, $staff);
        $this->expectException(ValidationException::class);
        $action->execute($project->refresh(), ProjectStatus::Completed, $admin);
    }

    public function test_admin_override_and_manual_progress_are_audited(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        app(UpdateProjectStatus::class)->execute($project, ProjectStatus::Completed, $admin, 'Proyek lama telah diverifikasi selesai.');
        app(UpdateProjectProgress::class)->execute($project->refresh(), 100, $admin);

        $this->assertSame(100, $project->refresh()->progress);
        $this->assertSame(2, ActivityLog::query()->where('model_id', $project->id)->count());
    }

    public function test_file_version_uses_same_document_uuid_and_increments_version(): void
    {
        $user = User::factory()->create();
        $current = ProjectFile::factory()->create(['uploaded_by' => $user->id]);
        $metadata = ['category' => 'hasil', 'original_name' => 'hasil-baru.pdf', 'file_path' => 'private/test.pdf', 'file_type' => 'application/pdf', 'file_size' => 1234];
        $next = app(CreateProjectFileVersion::class)->execute($current, $user, $metadata);

        $this->assertSame($current->document_uuid, $next->document_uuid);
        $this->assertSame(2, $next->version);
        $this->assertNotSame($current->stored_name, $next->stored_name);
    }

    public function test_double_consultation_conversion_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        $consultation = Consultation::factory()->create([
            'user_id' => $customer->id,
            'email' => $customer->email,
            'status' => ConsultationStatus::Reviewed,
        ]);
        $action = app(ConvertConsultationToProject::class);
        $action->execute($consultation, $admin);

        try {
            $action->execute($consultation->refresh(), $admin);
            $this->fail('Konversi ganda seharusnya ditolak.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('projects', 1);
            $this->assertSame(ConsultationStatus::Converted, $consultation->refresh()->status);
        }
    }

    public function test_consultation_conversion_rolls_back_when_audit_logging_fails(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        $consultation = Consultation::factory()->create([
            'user_id' => $customer->id,
            'email' => $customer->email,
            'status' => ConsultationStatus::Reviewed,
        ]);
        $logger = $this->createMock(ActivityLogger::class);
        $logger->method('log')->willThrowException(new RuntimeException('Simulasi kegagalan audit.'));
        $this->app->instance(ActivityLogger::class, $logger);

        try {
            app(ConvertConsultationToProject::class)->execute($consultation, $admin);
            $this->fail('Kegagalan audit seharusnya membatalkan transaksi.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('projects', 0);
            $this->assertDatabaseCount('code_sequences', 0);
            $this->assertSame(ConsultationStatus::Reviewed, $consultation->refresh()->status);
        }
    }

    public function test_admin_override_without_reason_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['status' => ProjectStatus::NewRequest]);

        try {
            app(UpdateProjectStatus::class)->execute($project, ProjectStatus::Completed, $admin);
            $this->fail('Override tanpa alasan seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('override_reason', $exception->errors());
            $this->assertSame(ProjectStatus::NewRequest, $project->refresh()->status);
            $this->assertDatabaseCount('activity_logs', 0);
        }
    }
}
