<?php

namespace Tests\Feature;

use App\Actions\Consultations\ApproveCustomerProjectRequest;
use App\Actions\Consultations\ConvertConsultationToProject;
use App\Actions\Consultations\RejectCustomerProjectRequest;
use App\Actions\Consultations\RequestCustomerProjectInformation;
use App\Enums\ConsultationStatus;
use App\Models\Consultation;
use App\Models\Service;
use App\Models\User;
use App\Notifications\NewConsultationNotification;
use App\Notifications\ProjectRequestStatusNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerProjectRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_project_request_history(): void
    {
        $this->get(route('customer.project-requests.index'))->assertRedirect(route('login'));
    }

    public function test_staff_cannot_open_customer_project_requests(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->get(route('customer.project-requests.index'))
            ->assertForbidden();
    }

    public function test_unverified_customer_is_redirected_to_email_verification(): void
    {
        $this->actingAs(User::factory()->customer()->unverified()->create())
            ->get(route('customer.project-requests.index'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_customer_can_open_project_request_history(): void
    {
        $this->actingAs(User::factory()->customer()->create())
            ->get(route('customer.project-requests.index'))
            ->assertOk()
            ->assertSee('Permintaan Proyek');
    }

    public function test_create_page_only_lists_active_services(): void
    {
        $active = Service::factory()->create(['name' => 'Layanan Aktif', 'is_active' => true]);
        $inactive = Service::factory()->create(['name' => 'Layanan Mati', 'is_active' => false]);

        $this->actingAs(User::factory()->customer()->create())
            ->get(route('customer.project-requests.create'))
            ->assertOk()
            ->assertSee($active->name)
            ->assertDontSee($inactive->name);
    }

    public function test_customer_submission_creates_linked_consultation_without_project(): void
    {
        Notification::fake();
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)
            ->post(route('customer.project-requests.store'), $this->validPayload())
            ->assertRedirect();

        $consultation = Consultation::query()->sole();
        $this->assertSame($customer->id, $consultation->user_id);
        $this->assertSame('customer_portal', $consultation->source);
        $this->assertSame(ConsultationStatus::New, $consultation->status);
        $this->assertDatabaseCount('projects', 0);
    }

    public function test_customer_identity_is_always_derived_from_authenticated_user(): void
    {
        Notification::fake();
        $customer = User::factory()->customer()->create([
            'name' => 'Nama Asli',
            'email' => 'asli@example.test',
        ]);
        $other = User::factory()->customer()->create();

        $this->actingAs($customer)->post(route('customer.project-requests.store'), array_merge(
            $this->validPayload(),
            ['user_id' => $other->id, 'name' => 'Penyerang', 'email' => 'serang@example.test'],
        ));

        $this->assertDatabaseHas('consultations', [
            'user_id' => $customer->id,
            'name' => 'Nama Asli',
            'email' => 'asli@example.test',
        ]);
    }

    public function test_customer_cannot_choose_status_or_create_project_fields(): void
    {
        Notification::fake();
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)->post(route('customer.project-requests.store'), array_merge(
            $this->validPayload(),
            [
                'status' => ConsultationStatus::Converted->value,
                'assigned_staff_id' => User::factory()->staff()->create()->id,
                'project_code' => 'INJECTED',
                'payment_status' => 'lunas',
            ],
        ));

        $this->assertSame(ConsultationStatus::New, Consultation::query()->sole()->status);
        $this->assertDatabaseCount('projects', 0);
    }

    public function test_submission_requires_service_title_description_and_consents(): void
    {
        $this->actingAs(User::factory()->customer()->create())
            ->post(route('customer.project-requests.store'), [])
            ->assertSessionHasErrors([
                'service_id',
                'project_title',
                'description',
                'privacy',
                'academic_integrity',
            ]);
    }

    public function test_inactive_service_is_rejected(): void
    {
        $service = Service::factory()->create(['is_active' => false]);

        $this->actingAs(User::factory()->customer()->create())
            ->post(route('customer.project-requests.store'), $this->validPayload([
                'service_id' => $service->id,
            ]))
            ->assertSessionHasErrors('service_id');
    }

    public function test_past_deadline_is_rejected(): void
    {
        $this->actingAs(User::factory()->customer()->create())
            ->post(route('customer.project-requests.store'), $this->validPayload([
                'deadline' => now()->subDay()->format('Y-m-d'),
            ]))
            ->assertSessionHasErrors('deadline');
    }

    public function test_profile_phone_is_used_when_form_phone_is_omitted(): void
    {
        Notification::fake();
        $customer = User::factory()->customer()->create(['phone' => '081234567890']);
        $payload = $this->validPayload();
        unset($payload['phone']);

        $this->actingAs($customer)->post(route('customer.project-requests.store'), $payload);

        $this->assertSame('6281234567890', Consultation::query()->sole()->phone);
    }

    public function test_form_phone_is_used_when_profile_phone_is_empty(): void
    {
        Notification::fake();
        $customer = User::factory()->customer()->create(['phone' => null]);

        $this->actingAs($customer)->post(
            route('customer.project-requests.store'),
            $this->validPayload(['phone' => '081298765432']),
        );

        $this->assertSame('6281298765432', Consultation::query()->sole()->phone);
    }

    public function test_optional_attachment_is_stored_privately(): void
    {
        Notification::fake();
        Storage::fake('local');
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)->post(route('customer.project-requests.store'), $this->validPayload([
            'attachment' => UploadedFile::fake()->create('brief.pdf', 24, 'application/pdf'),
        ]));

        $consultation = Consultation::query()->sole();
        $this->assertNotNull($consultation->attachment_path);
        Storage::disk('local')->assertExists($consultation->attachment_path);
    }

    public function test_new_customer_request_notifies_active_admins_only(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $inactiveAdmin = User::factory()->admin()->inactive()->create();
        $staff = User::factory()->staff()->create();

        $this->actingAs(User::factory()->customer()->create())
            ->post(route('customer.project-requests.store'), $this->validPayload());

        Notification::assertSentTo($admin, NewConsultationNotification::class);
        Notification::assertNotSentTo($inactiveAdmin, NewConsultationNotification::class);
        Notification::assertNotSentTo($staff, NewConsultationNotification::class);
    }

    public function test_history_only_contains_authenticated_customers_requests(): void
    {
        $customer = User::factory()->customer()->create();
        $own = Consultation::factory()->create(['user_id' => $customer->id, 'project_title' => 'Permintaan Milik Saya']);
        $foreign = Consultation::factory()->create(['project_title' => 'Permintaan Rahasia']);

        $this->actingAs($customer)
            ->get(route('customer.project-requests.index'))
            ->assertSee($own->project_title)
            ->assertDontSee($foreign->project_title);
    }

    public function test_history_search_is_scoped_and_escaped(): void
    {
        $customer = User::factory()->customer()->create();
        Consultation::factory()->create(['user_id' => $customer->id, 'project_title' => 'Aplikasi Inventori']);
        Consultation::factory()->create(['user_id' => $customer->id, 'project_title' => 'Analisis Statistik']);

        $this->actingAs($customer)
            ->get(route('customer.project-requests.index', ['q' => 'Inventori']))
            ->assertSee('Aplikasi Inventori')
            ->assertDontSee('Analisis Statistik');
    }

    public function test_history_can_filter_customer_facing_status(): void
    {
        $customer = User::factory()->customer()->create();
        Consultation::factory()->create([
            'user_id' => $customer->id,
            'project_title' => 'Masih Menunggu',
            'status' => ConsultationStatus::New,
        ]);
        Consultation::factory()->create([
            'user_id' => $customer->id,
            'project_title' => 'Perlu Informasi',
            'status' => ConsultationStatus::Contacted,
        ]);

        $this->actingAs($customer)
            ->get(route('customer.project-requests.index', ['status' => ConsultationStatus::Contacted->value]))
            ->assertSee('Perlu Informasi')
            ->assertDontSee('Masih Menunggu');
    }

    public function test_history_is_paginated(): void
    {
        $customer = User::factory()->customer()->create();
        Consultation::factory()->count(13)->create(['user_id' => $customer->id]);

        $response = $this->actingAs($customer)->get(route('customer.project-requests.index'));

        $this->assertSame(10, $response->viewData('consultations')->count());
    }

    public function test_customer_can_view_own_request_detail(): void
    {
        $customer = User::factory()->customer()->create();
        $consultation = Consultation::factory()->create(['user_id' => $customer->id]);

        $this->actingAs($customer)
            ->get(route('customer.project-requests.show', $consultation))
            ->assertOk()
            ->assertSee($consultation->request_code);
    }

    public function test_customer_cannot_view_another_customers_request(): void
    {
        $consultation = Consultation::factory()->create([
            'user_id' => User::factory()->customer()->create()->id,
        ]);

        $this->actingAs(User::factory()->customer()->create())
            ->get(route('customer.project-requests.show', $consultation))
            ->assertForbidden();
    }

    public function test_customer_can_update_description_only_when_information_is_requested(): void
    {
        $customer = User::factory()->customer()->create();
        $consultation = Consultation::factory()->create([
            'user_id' => $customer->id,
            'status' => ConsultationStatus::Contacted,
            'description' => 'Deskripsi awal yang cukup panjang untuk validasi.',
        ]);

        $this->actingAs($customer)
            ->patch(route('customer.project-requests.update', $consultation), [
                'description' => 'Deskripsi tambahan yang lebih lengkap untuk kebutuhan proyek.',
                'status' => ConsultationStatus::Converted->value,
                'user_id' => User::factory()->customer()->create()->id,
            ])
            ->assertRedirect(route('customer.project-requests.show', $consultation));

        $consultation->refresh();
        $this->assertSame('Deskripsi tambahan yang lebih lengkap untuk kebutuhan proyek.', $consultation->description);
        $this->assertSame(ConsultationStatus::New, $consultation->status);
        $this->assertSame($customer->id, $consultation->user_id);
    }

    public function test_customer_cannot_update_request_while_waiting_review(): void
    {
        $customer = User::factory()->customer()->create();
        $consultation = Consultation::factory()->create([
            'user_id' => $customer->id,
            'status' => ConsultationStatus::New,
        ]);

        $this->actingAs($customer)
            ->patch(route('customer.project-requests.update', $consultation), [
                'description' => 'Deskripsi baru yang sengaja cukup panjang untuk validasi.',
            ])
            ->assertForbidden();
    }

    public function test_request_information_action_updates_status_audits_and_notifies_customer(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        $consultation = Consultation::factory()->create(['user_id' => $customer->id]);

        app(RequestCustomerProjectInformation::class)->execute(
            $consultation,
            $admin,
            'Mohon lengkapi sumber data dan ruang lingkup.',
        );

        $this->assertSame(ConsultationStatus::Contacted, $consultation->refresh()->status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'consultation.information_requested']);
        Notification::assertSentTo($customer, ProjectRequestStatusNotification::class);
    }

    public function test_approve_action_updates_status_and_notifies_customer(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        $consultation = Consultation::factory()->create(['user_id' => $customer->id]);

        app(ApproveCustomerProjectRequest::class)->execute($consultation, $admin, 'Permintaan dapat dikerjakan.');

        $this->assertSame(ConsultationStatus::Reviewed, $consultation->refresh()->status);
        Notification::assertSentTo($customer, ProjectRequestStatusNotification::class);
    }

    public function test_reject_action_requires_reason_and_exposes_it_to_customer(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        $consultation = Consultation::factory()->create(['user_id' => $customer->id]);

        app(RejectCustomerProjectRequest::class)->execute(
            $consultation,
            $admin,
            'Permintaan berada di luar ruang lingkup layanan.',
        );

        $this->actingAs($customer)
            ->get(route('customer.project-requests.show', $consultation))
            ->assertSee('Permintaan berada di luar ruang lingkup layanan.');
        Notification::assertSentTo($customer, ProjectRequestStatusNotification::class);
    }

    public function test_conversion_is_one_time_links_project_and_notifies_customer(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        $consultation = Consultation::factory()->create([
            'user_id' => $customer->id,
            'email' => $customer->email,
            'status' => ConsultationStatus::Reviewed,
            'source' => 'customer_portal',
        ]);

        $project = app(ConvertConsultationToProject::class)->execute($consultation, $admin);

        $this->actingAs($customer)
            ->get(route('customer.project-requests.show', $consultation))
            ->assertSee($project->project_code)
            ->assertSee(route('customer.projects.show', $project));
        Notification::assertSentTo($customer, ProjectRequestStatusNotification::class);
    }

    public function test_customer_request_policy_separates_admin_customer_and_staff_capabilities(): void
    {
        $customer = User::factory()->customer()->create();
        $consultation = Consultation::factory()->create([
            'user_id' => $customer->id,
            'status' => ConsultationStatus::Contacted,
        ]);

        $this->assertTrue(Gate::forUser($customer)->allows('viewRequest', $consultation));
        $this->assertTrue(Gate::forUser($customer)->allows('updateRequest', $consultation));
        $this->assertFalse(Gate::forUser(User::factory()->staff()->create())->allows('viewRequest', $consultation));
        $this->assertTrue(Gate::forUser(User::factory()->admin()->create())->allows('view', $consultation));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'service_id' => Service::factory()->create()->id,
            'project_title' => 'Sistem Informasi Penjualan',
            'description' => 'Saya membutuhkan sistem informasi penjualan dengan laporan dan pengelolaan stok.',
            'phone' => '081234567890',
            'deadline' => now()->addMonth()->format('Y-m-d'),
            'technology' => 'Laravel dan Livewire',
            'budget' => 5000000,
            'privacy' => '1',
            'academic_integrity' => '1',
        ], $overrides);
    }
}
