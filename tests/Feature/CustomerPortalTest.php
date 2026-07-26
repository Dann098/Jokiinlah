<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectMilestone;
use App\Models\Reminder;
use App\Models\Revision;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_requires_verified_active_customer(): void
    {
        $this->get(route('customer.dashboard'))->assertRedirect(route('login'));

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)->get(route('customer.dashboard'))->assertRedirect(route('verification.notice'));

        $inactive = User::factory()->customer()->inactive()->create();
        $this->actingAs($inactive)->get(route('customer.dashboard'))->assertForbidden();

        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)->get(route('customer.dashboard'))->assertForbidden();

        $customer = User::factory()->customer()->create();
        $this->actingAs($customer)->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('Customer Portal')
            ->assertSee('Lewati ke konten utama');
    }

    public function test_dashboard_counts_and_lists_only_owned_projects(): void
    {
        $customer = User::factory()->customer()->create(['name' => 'Nadia Customer']);
        $other = User::factory()->customer()->create();

        $active = Project::factory()->for($customer, 'customer')->create([
            'title' => 'Portal Milik Nadia',
            'status' => ProjectStatus::WaitingData,
        ]);
        Project::factory()->for($customer, 'customer')->create([
            'title' => 'Proyek Selesai Nadia',
            'status' => ProjectStatus::Completed,
        ]);
        Project::factory()->for($other, 'customer')->create([
            'title' => 'RAHASIA PROYEK CUSTOMER LAIN',
            'status' => ProjectStatus::CustomerReview,
        ]);

        Reminder::factory()->for($customer)->create(['project_id' => $active->id, 'title' => 'Unggah data Nadia']);
        Reminder::factory()->for($other)->create(['title' => 'RAHASIA REMINDER LAIN']);
        Appointment::factory()->for($active)->create([
            'customer_id' => $customer->id,
            'appointment_date' => now()->addDay(),
            'title' => 'Jadwal Nadia',
        ]);
        ProjectFile::factory()->for($active)->create(['original_name' => 'file-nadia.pdf']);

        $response = $this->actingAs($customer)->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('Nadia Customer')
            ->assertSee('Portal Milik Nadia')
            ->assertSee('Proyek Selesai Nadia')
            ->assertSee('Unggah data Nadia')
            ->assertSee('Jadwal Nadia')
            ->assertSee('file-nadia.pdf')
            ->assertDontSee('RAHASIA PROYEK CUSTOMER LAIN')
            ->assertDontSee('RAHASIA REMINDER LAIN');

        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
        $response->assertSee("aria-valuemin='0'", false)
            ->assertSee("aria-valuemax='100'", false)
            ->assertSee("aria-current='page'", false);
    }

    public function test_customer_without_projects_sees_useful_empty_state(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('Belum ada proyek')
            ->assertSee('Lihat layanan')
            ->assertSee('Ajukan konsultasi');
    }

    public function test_project_index_search_filter_pagination_and_xss_are_safe(): void
    {
        $customer = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();

        Project::factory()->for($customer, 'customer')->create([
            'project_code' => 'PRJ-CARI-001',
            'title' => 'Dashboard Riset Aman',
            'status' => ProjectStatus::InProgress,
        ]);
        Project::factory()->for($customer, 'customer')->create([
            'project_code' => 'PRJ-SELESAI-001',
            'title' => 'Proyek Selesai',
            'status' => ProjectStatus::Completed,
        ]);
        Project::factory()->for($other, 'customer')->create(['title' => 'PROYEK ASING']);

        foreach (range(1, 10) as $index) {
            Project::factory()->for($customer, 'customer')->create([
                'project_code' => 'PRJ-PAGE-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'title' => 'Proyek Pagination '.$index,
            ]);
        }

        $this->actingAs($customer)
            ->get(route('customer.projects.index', ['q' => 'Dashboard', 'status' => ProjectStatus::InProgress->value]))
            ->assertOk()
            ->assertSee('Dashboard Riset Aman')
            ->assertDontSee('Proyek Selesai')
            ->assertDontSee('PROYEK ASING');

        $this->actingAs($customer)
            ->get(route('customer.projects.index', ['q' => 'Pagination']))
            ->assertOk()
            ->assertSee('page=2', false)
            ->assertSee('q=Pagination', false);

        $payload = '<script>alert(1)</script>';
        $this->actingAs($customer)
            ->get(route('customer.projects.index', ['q' => $payload]))
            ->assertOk()
            ->assertDontSee($payload, false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_project_detail_is_owner_only_and_hides_internal_data(): void
    {
        $customer = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $project = Project::factory()->for($customer, 'customer')->create([
            'title' => '<script>Detail Aman</script>',
            'description' => '<img src=x onerror=alert(1)> Deskripsi aman',
            'admin_note' => 'CATATAN INTERNAL SANGAT RAHASIA',
            'payment_note' => 'CATATAN PEMBAYARAN INTERNAL',
        ]);
        $foreign = Project::factory()->for($other, 'customer')->create();

        ProjectMilestone::factory()->for($project)->create(['title' => '<b>Milestone aman</b>']);
        ProjectFile::factory()->for($project)->create(['original_name' => '<svg onload=alert(1)>.pdf']);
        Revision::factory()->for($project)->for($customer, 'submitter')->create(['title' => '<i>Revisi aman</i>']);
        Reminder::factory()->for($customer)->for($project)->create(['title' => '<u>Reminder aman</u>']);
        Appointment::factory()->for($project)->create(['customer_id' => $customer->id, 'title' => '<em>Jadwal aman</em>']);
        ActivityLog::query()->forceCreate([
            'user_id' => $customer->id,
            'action' => 'private.audit',
            'description' => 'ACTIVITY LOG INTERNAL',
            'model_type' => $project->getMorphClass(),
            'model_id' => $project->id,
        ]);

        $this->actingAs($customer)->get(route('customer.projects.show', $project))
            ->assertOk()
            ->assertSee('&lt;script&gt;Detail Aman&lt;/script&gt;', false)
            ->assertDontSee('<script>Detail Aman</script>', false)
            ->assertSee('&lt;b&gt;Milestone aman&lt;/b&gt;', false)
            ->assertSee('&lt;svg onload=alert(1)&gt;.pdf', false)
            ->assertDontSee('CATATAN INTERNAL SANGAT RAHASIA')
            ->assertDontSee('CATATAN PEMBAYARAN INTERNAL')
            ->assertDontSee('ACTIVITY LOG INTERNAL');

        $this->actingAs($customer)->get(route('customer.projects.show', $foreign))->assertForbidden();
    }

    public function test_reminders_and_appointments_are_scoped_and_unsafe_url_is_not_linked(): void
    {
        $customer = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $project = Project::factory()->for($customer, 'customer')->create();
        $foreignProject = Project::factory()->for($other, 'customer')->create();

        Reminder::factory()->for($customer)->for($project)->create(['title' => 'Pengingat milik sendiri']);
        Reminder::factory()->for($other)->for($foreignProject)->create(['title' => 'PENGINGAT ASING']);

        Appointment::factory()->for($project)->create([
            'customer_id' => $customer->id,
            'title' => 'Jadwal aman',
            'meeting_link' => 'https://meet.example.test/room',
            'appointment_date' => '2026-07-27 03:00:00',
            'status' => AppointmentStatus::Confirmed,
        ]);
        Appointment::factory()->for($project)->create([
            'customer_id' => $customer->id,
            'title' => 'Jadwal URL tidak aman',
            'meeting_link' => 'javascript:alert(1)',
        ]);
        Appointment::factory()->for($foreignProject)->create([
            'customer_id' => $other->id,
            'title' => 'JADWAL ASING',
        ]);

        $this->actingAs($customer)->get(route('customer.reminders.index'))
            ->assertOk()
            ->assertSee('Pengingat milik sendiri')
            ->assertDontSee('PENGINGAT ASING');

        $this->actingAs($customer)->get(route('customer.appointments.index'))
            ->assertOk()
            ->assertSee('Jadwal aman')
            ->assertSee('27 Jul 2026, 10:00')
            ->assertSee('https://meet.example.test/room', false)
            ->assertDontSee('javascript:alert(1)', false)
            ->assertDontSee('JADWAL ASING');
    }

    public function test_profile_update_whitelists_fields_and_password_requires_current_password(): void
    {
        $customer = User::factory()->customer()->create([
            'name' => 'Nama Lama',
            'email' => 'lama@example.test',
            'password' => Hash::make('Password123!'),
        ]);

        $this->actingAs($customer)->patch(route('customer.profile.update'), [
            'name' => 'Nama Baru',
            'phone' => '0812-3456-7890',
            'email' => 'penyerang@example.test',
            'role' => UserRole::Admin->value,
            'is_active' => false,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertSame('Nama Baru', $customer->name);
        $this->assertSame('6281234567890', $customer->phone);
        $this->assertSame('lama@example.test', $customer->email);
        $this->assertSame(UserRole::Customer, $customer->role);
        $this->assertTrue($customer->is_active);
        $this->assertDatabaseHas('activity_logs', ['action' => 'customer.profile_updated', 'user_id' => $customer->id]);

        $this->actingAs($customer)->put(route('customer.password.update'), [
            'current_password' => 'salah',
            'password' => 'PasswordBaru123!',
            'password_confirmation' => 'PasswordBaru123!',
        ])->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('Password123!', $customer->refresh()->password));

        $this->actingAs($customer)->put(route('customer.password.update'), [
            'current_password' => 'Password123!',
            'password' => 'PasswordBaru123!',
            'password_confirmation' => 'PasswordBaru123!',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('PasswordBaru123!', $customer->refresh()->password));

        $passwordLog = ActivityLog::query()->where('action', 'customer.password_updated')->sole();
        $this->assertStringNotContainsString('PasswordBaru123!', json_encode($passwordLog->toArray(), JSON_THROW_ON_ERROR));
    }

    public function test_project_whatsapp_message_uses_configured_number_and_encoded_server_data(): void
    {
        SiteSetting::query()->create([
            'key' => 'whatsapp_number',
            'value' => '0812-3456-7890',
            'type' => 'string',
            'group' => 'contact',
            'is_public' => true,
        ]);
        $customer = User::factory()->customer()->create();
        $project = Project::factory()->for($customer, 'customer')->create([
            'project_code' => 'PRJ-WA-001',
            'title' => 'Data & Portal?',
        ]);

        $this->actingAs($customer)->get(route('customer.projects.show', $project))
            ->assertOk()
            ->assertSee(
                'https://wa.me/6281234567890?text=Halo%2C%20saya%20ingin%20menanyakan%20perkembangan%20proyek%20PRJ-WA-001%20%E2%80%94%20Data%20%26%20Portal%3F.',
                false,
            )
            ->assertSee('rel=\'noopener noreferrer\'', false);
    }

    public function test_customer_mutation_routes_keep_csrf_and_expose_no_project_status_or_progress_endpoint(): void
    {
        foreach ([
            'customer.projects.files.store',
            'customer.projects.files.versions.store',
            'customer.projects.revisions.store',
            'customer.profile.update',
            'customer.password.update',
        ] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertContains('web', $route->gatherMiddleware());
            $this->assertContains('role:customer', $route->gatherMiddleware());
        }

        $customerRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'customer.'));

        $this->assertFalse($customerRoutes->contains(
            fn ($route): bool => preg_match('#/(status|progress)$#', $route->uri()) === 1,
        ));
        $this->assertFalse($customerRoutes->contains(
            fn ($route): bool => in_array('DELETE', $route->methods(), true),
        ));
    }
}
