<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Consultation;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_only_sees_owned_projects(): void
    {
        $owner = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $owned = Project::factory()->for($owner, 'customer')->create();
        $foreign = Project::factory()->for($other, 'customer')->create();

        $this->assertTrue($owner->can('view', $owned));
        $this->assertFalse($owner->can('view', $foreign));
    }

    public function test_staff_only_sees_assigned_project_and_admin_sees_both(): void
    {
        $staff = User::factory()->staff()->create();
        $admin = User::factory()->admin()->create();
        $assigned = Project::factory()->create(['assigned_staff_id' => $staff->id]);
        $unassigned = Project::factory()->create();

        $this->assertTrue($staff->can('view', $assigned));
        $this->assertFalse($staff->can('view', $unassigned));
        $this->assertTrue($admin->can('view', $assigned));
        $this->assertTrue($admin->can('view', $unassigned));
    }

    public function test_consultation_is_admin_only_and_activity_log_is_immutable(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $consultation = Consultation::factory()->create();
        $log = ActivityLog::query()->forceCreate(['action' => 'test', 'description' => 'Audit']);

        $this->assertTrue($admin->can('view', $consultation));
        $this->assertFalse($staff->can('view', $consultation));
        $this->assertTrue($admin->can('view', $log));
        $this->assertFalse($admin->can('delete', $log));
    }

    public function test_customer_cannot_delete_processed_file_but_can_upload_a_version(): void
    {
        $customer = User::factory()->customer()->create();
        $project = Project::factory()->for($customer, 'customer')->create();
        $file = ProjectFile::factory()->for($project)->create();

        $this->assertTrue($customer->can('view', $file));
        $this->assertTrue($customer->can('uploadVersion', $file));
        $this->assertFalse($customer->can('delete', $file));
    }

    public function test_private_file_download_requires_project_access_and_is_audited(): void
    {
        Storage::fake('local');
        $owner = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $project = Project::factory()->for($owner, 'customer')->create();
        $file = ProjectFile::factory()->for($project)->create(['file_path' => 'projects/test/file.pdf']);
        Storage::disk('local')->put($file->file_path, 'private-content');

        $this->actingAs($other)->get(route('project-files.download', $file))->assertForbidden();
        $this->actingAs($owner)->get(route('project-files.download', $file))->assertOk();
        $this->assertDatabaseHas('activity_logs', ['action' => 'project_file.downloaded', 'model_id' => $file->id]);
    }
}
