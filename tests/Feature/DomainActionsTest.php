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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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
}
