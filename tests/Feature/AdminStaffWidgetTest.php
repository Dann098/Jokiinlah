<?php

namespace Tests\Feature;

use App\Filament\Widgets\OperationalStats;
use App\Filament\Widgets\UpcomingOperations;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Project;
use App\Models\User;
use App\Notifications\NewConsultationNotification;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminStaffWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        Livewire::flushState();
        Filament::setCurrentPanel(null);

        parent::tearDown();
    }

    public function test_admin_stats_include_global_operations_but_staff_stats_do_not(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        Project::factory()->create(['assigned_staff_id' => $staff->id]);
        Project::factory()->create(['assigned_staff_id' => null]);
        Consultation::factory()->create(['status' => 'new']);

        $this->actingAs($admin);
        Livewire::test(OperationalStats::class)
            ->assertSee('Konsultasi baru')
            ->assertSee('Belum ditugaskan')
            ->assertSee('Pembayaran terbuka');

        $this->actingAs($staff);
        Livewire::test(OperationalStats::class)
            ->assertSee('Proyek aktif')
            ->assertDontSee('Konsultasi baru')
            ->assertDontSee('Belum ditugaskan')
            ->assertDontSee('Pembayaran terbuka');
    }

    public function test_upcoming_appointments_table_is_scoped_to_assigned_projects(): void
    {
        $staff = User::factory()->staff()->create();
        $other = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create();
        $assignedProject = Project::factory()->create(['customer_id' => $customer->id, 'assigned_staff_id' => $staff->id]);
        $otherProject = Project::factory()->create(['customer_id' => $customer->id, 'assigned_staff_id' => $other->id]);
        $assigned = Appointment::query()->forceCreate([
            'project_id' => $assignedProject->id, 'customer_id' => $customer->id, 'staff_id' => $staff->id,
            'title' => 'Agenda Assigned', 'appointment_date' => now()->addDay(), 'status' => 'scheduled',
        ]);
        $foreign = Appointment::query()->forceCreate([
            'project_id' => $otherProject->id, 'customer_id' => $customer->id, 'staff_id' => $other->id,
            'title' => 'Agenda Foreign', 'appointment_date' => now()->addDay(), 'status' => 'scheduled',
        ]);

        $this->actingAs($staff);
        Livewire::test(UpcomingOperations::class)
            ->assertCanSeeTableRecords([$assigned])
            ->assertCanNotSeeTableRecords([$foreign]);
    }

    public function test_database_notifications_are_owned_by_the_recipient(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $consultation = Consultation::factory()->create();

        $admin->notify(new NewConsultationNotification($consultation, ['database']));

        $this->assertCount(1, $admin->notifications);
        $this->assertCount(0, $otherAdmin->notifications);
        $this->assertCount(0, $staff->notifications);
        $this->assertSame($consultation->id, $admin->notifications->first()->data['consultation_id']);
    }
}
