<?php

namespace Tests\Feature;

use App\Actions\Projects\UpdatePaymentStatus;
use App\Actions\Projects\UpdateProjectProgress;
use App\Actions\Projects\UpdateProjectStatus;
use App\Actions\Revisions\UpdateRevision;
use App\Actions\Users\CreateManagedUser;
use App\Actions\Users\UpdateManagedUser;
use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Enums\RevisionPriority;
use App\Enums\RevisionStatus;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectMilestone;
use App\Models\Reminder;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminStaffDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_managed_accounts_have_server_generated_roles_and_no_logged_credentials(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        $staff = app(CreateManagedUser::class)->execute([
            'name' => 'Staff Baru',
            'email' => 'staff.baru@example.com',
            'phone' => '+628123456789',
            'role' => UserRole::Admin->value,
            'password' => 'InjectedPassword!',
            'is_active' => true,
        ], UserRole::Staff, $admin);

        $this->assertSame(UserRole::Staff, $staff->role);
        $this->assertNull($staff->email_verified_at);
        $this->assertNotSame('InjectedPassword!', $staff->password);
        Notification::assertSentTo($staff, ResetPassword::class);
        Notification::assertSentTo($staff, VerifyEmail::class);

        $serializedLogs = ActivityLog::query()->pluck('metadata')->map(fn ($value) => json_encode($value))->implode(' ');
        $this->assertStringNotContainsString('InjectedPassword', $serializedLogs);
        $this->assertStringNotContainsString('password', mb_strtolower($serializedLogs));
    }

    public function test_account_updates_ignore_role_and_password_and_protect_self_deactivation(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $password = $staff->password;

        $updated = app(UpdateManagedUser::class)->execute($staff, [
            'name' => 'Nama Staff',
            'email' => $staff->email,
            'phone' => null,
            'institution' => null,
            'study_program' => null,
            'is_active' => true,
            'role' => UserRole::Admin->value,
            'password' => 'InjectedPassword!',
        ], UserRole::Staff, $admin);

        $this->assertSame(UserRole::Staff, $updated->role);
        $this->assertSame($password, $updated->password);
        $this->assertFalse($admin->can('delete', $admin));

        $this->expectException(ValidationException::class);
        app(UpdateManagedUser::class)->execute($admin, [
            'name' => $admin->name,
            'email' => $admin->email,
            'is_active' => false,
        ], UserRole::Admin, $admin);
    }

    public function test_project_actions_enforce_scope_transitions_progress_and_manual_payment(): void
    {
        $admin = User::factory()->admin()->create();
        $assigned = User::factory()->staff()->create();
        $other = User::factory()->staff()->create();
        $project = Project::factory()->create([
            'assigned_staff_id' => $assigned->id,
            'status' => ProjectStatus::NewRequest,
            'progress' => 0,
            'payment_status' => PaymentStatus::Unpaid,
        ]);

        app(UpdateProjectStatus::class)->execute($project, ProjectStatus::Consultation, $assigned);
        app(UpdateProjectProgress::class)->execute($project, 42, $assigned);
        $this->assertSame(42, $project->refresh()->progress);

        try {
            app(UpdateProjectStatus::class)->execute($project, ProjectStatus::Completed, $assigned);
            $this->fail('Transisi status staff yang tidak valid seharusnya ditolak.');
        } catch (ValidationException) {
            $this->assertSame(ProjectStatus::Consultation, $project->refresh()->status);
        }

        try {
            app(UpdateProjectProgress::class)->execute($project, 101, $assigned);
            $this->fail('Progress di atas 100 seharusnya ditolak.');
        } catch (ValidationException) {
            $this->assertSame(42, $project->refresh()->progress);
        }

        try {
            app(UpdatePaymentStatus::class)->execute($project, PaymentStatus::Paid, null, $other);
            $this->fail('Staff seharusnya tidak dapat mengubah pembayaran.');
        } catch (AuthorizationException) {
            $this->assertSame(PaymentStatus::Unpaid, $project->refresh()->payment_status);
        }

        app(UpdatePaymentStatus::class)->execute($project, PaymentStatus::DownPayment, 'DP diterima manual', $admin);
        $this->assertSame(PaymentStatus::DownPayment, $project->refresh()->payment_status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'project.payment_status_changed', 'model_id' => $project->id]);
        $this->assertSame(['belum_dibayar', 'dp', 'lunas'], array_column(PaymentStatus::cases(), 'value'));
        $this->assertFalse(collect(app('router')->getRoutes())->contains(fn ($route) => str_contains($route->uri(), 'invoice') || str_contains($route->uri(), 'payment-gateway')));
    }

    public function test_child_policies_and_internal_fields_are_scoped_to_the_parent_project(): void
    {
        $customer = User::factory()->customer()->create();
        $assigned = User::factory()->staff()->create();
        $other = User::factory()->staff()->create();
        $project = Project::factory()->create(['customer_id' => $customer->id, 'assigned_staff_id' => $assigned->id]);

        $milestone = ProjectMilestone::query()->forceCreate([
            'project_id' => $project->id, 'title' => 'Milestone aman', 'internal_note' => 'RAHASIA-MILESTONE',
        ]);
        $reminder = Reminder::query()->forceCreate([
            'project_id' => $project->id, 'user_id' => $customer->id, 'created_by' => $assigned->id,
            'title' => 'Internal', 'reminder_date' => now(), 'is_customer_visible' => false,
        ]);
        $appointment = Appointment::query()->forceCreate([
            'project_id' => $project->id, 'customer_id' => $customer->id, 'staff_id' => $assigned->id,
            'title' => 'Jadwal', 'appointment_date' => now()->addDay(), 'meeting_link' => 'javascript:alert(1)',
            'internal_note' => 'RAHASIA-JADWAL', 'status' => 'scheduled',
        ]);
        $revision = Revision::query()->forceCreate([
            'project_id' => $project->id, 'submitted_by' => $customer->id, 'title' => 'Revisi',
            'description' => 'Mohon revisi', 'priority' => RevisionPriority::Normal,
            'status' => RevisionStatus::Submitted, 'internal_note' => 'RAHASIA-REVISI',
        ]);

        foreach ([$milestone, $reminder, $appointment, $revision] as $child) {
            $this->assertTrue($assigned->can('view', $child));
            $this->assertFalse($other->can('view', $child));
        }
        $this->assertFalse($customer->can('view', $reminder));
        $this->assertNull($appointment->safeMeetingUrl());

        app(UpdateRevision::class)->execute($revision, [
            'status' => RevisionStatus::UnderReview->value,
            'priority' => RevisionPriority::High->value,
            'admin_response' => 'Sedang kami tinjau.',
            'internal_note' => 'RAHASIA-REVISI-BARU',
        ], $assigned);
        $this->assertDatabaseHas('activity_logs', ['action' => 'revision.updated', 'model_id' => $revision->id]);

        $response = $this->actingAs($customer)->get(route('customer.projects.show', $project));
        $response->assertOk()
            ->assertDontSee('RAHASIA-MILESTONE')
            ->assertDontSee('RAHASIA-JADWAL')
            ->assertDontSee('RAHASIA-REVISI')
            ->assertDontSee('javascript:alert');
        $this->actingAs($customer)->get(route('customer.reminders.index'))->assertDontSee('Internal');
    }

    public function test_private_file_access_is_available_only_to_admin_and_assigned_project_users(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $assigned = User::factory()->staff()->create();
        $other = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create();
        $project = Project::factory()->create(['customer_id' => $customer->id, 'assigned_staff_id' => $assigned->id]);
        $file = ProjectFile::factory()->for($project)->create(['file_path' => 'projects/private/uuid-file']);
        Storage::disk('local')->put($file->file_path, 'private');

        foreach ([$admin, $assigned, $customer] as $user) {
            $this->assertTrue($user->can('download', $file));
            $this->actingAs($user)->get(route('project-files.download', $file))->assertOk();
        }

        $this->assertFalse($other->can('download', $file));
        $this->actingAs($other)->get(route('project-files.download', $file))->assertForbidden();
        $this->assertFalse($assigned->can('delete', $file));
        $this->assertFalse($admin->can('forceDelete', $file));
    }
}
