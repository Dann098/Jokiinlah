<?php

namespace Tests\Feature;

use App\Models\Consultation;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_consultation_attachments_have_no_public_download_route(): void
    {
        $this->assertFalse(Route::has('consultations.attachments.download'));
        $this->get('/storage/consultations/private-document.pdf')->assertNotFound();
    }

    public function test_customer_and_staff_cannot_manage_initial_consultations(): void
    {
        $consultation = Consultation::factory()->create();
        $customer = User::factory()->customer()->create();
        $staff = User::factory()->staff()->create();

        $this->assertFalse($customer->can('view', $consultation));
        $this->assertFalse($staff->can('view', $consultation));
    }

    public function test_admin_placeholder_is_safe_until_filament_is_built(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Tahap 5');
        $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk()->assertSee('Tahap 5');
    }

    public function test_public_submission_uses_web_middleware_and_csrf_token(): void
    {
        $route = Route::getRoutes()->getByName('consultations.store');

        $this->assertContains('web', $route->gatherMiddleware());
        $this->get(route('contact.index'))->assertOk()->assertSee('_token', false);
    }

    public function test_whatsapp_link_encodes_dynamic_message(): void
    {
        SiteSetting::query()->create(['key' => 'whatsapp_number', 'value' => '081234567890', 'type' => 'string', 'group' => 'contact', 'is_public' => true]);
        $service = Service::factory()->create(['name' => 'Data & Web?']);

        $this->get(route('services.show', $service))
            ->assertOk()
            ->assertSee('https://wa.me/6281234567890?text=Halo%2C%20saya%20ingin%20berkonsultasi%20mengenai%20layanan%20Data%20%26%20Web%3F.', false);
    }

    public function test_non_production_robots_disallows_indexing(): void
    {
        $this->get(route('robots'))->assertOk()->assertSee('Disallow: /');
    }
}
