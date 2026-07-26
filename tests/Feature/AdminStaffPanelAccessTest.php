<?php

namespace Tests\Feature;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminStaffPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_access_is_restricted_to_active_verified_admin_and_staff(): void
    {
        $this->get('/admin')->assertRedirect(route('filament.admin.auth.login'));

        foreach ([User::factory()->admin()->create(), User::factory()->staff()->create()] as $user) {
            $this->actingAs($user)->get('/admin')->assertOk()->assertSee('Jokiinlah Operasional');
            auth()->logout();
        }

        $this->actingAs(User::factory()->customer()->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->admin()->inactive()->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->staff()->unverified()->create())->get('/admin')->assertForbidden();

        $this->assertFalse(Route::has('filament.admin.auth.register'));
    }

    public function test_admin_can_open_all_resource_entry_pages_and_read_only_routes_are_closed(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([
            '/admin/customer', '/admin/staff', '/admin/consultations', '/admin/projects',
            '/admin/services', '/admin/portfolios', '/admin/articles', '/admin/testimonials',
            '/admin/faqs', '/admin/site-settings', '/admin/activity-logs',
            '/admin/customer/create', '/admin/staff/create', '/admin/projects/create',
            '/admin/services/create', '/admin/portfolios/create', '/admin/articles/create',
            '/admin/testimonials/create', '/admin/faqs/create',
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }

        $this->actingAs($admin)->get('/admin/site-settings/create')->assertNotFound();
        $this->actingAs($admin)->get('/admin/activity-logs/create')->assertNotFound();
    }

    public function test_staff_navigation_and_direct_urls_follow_the_same_authorization(): void
    {
        $staff = User::factory()->staff()->create();
        $otherStaff = User::factory()->staff()->create();
        $assigned = Project::factory()->create(['assigned_staff_id' => $staff->id]);
        $other = Project::factory()->create(['assigned_staff_id' => $otherStaff->id]);
        $unassigned = Project::factory()->create(['assigned_staff_id' => null]);

        $response = $this->actingAs($staff)->get('/admin');
        $response->assertOk()
            ->assertSee('Proyek')
            ->assertDontSee('Konsultasi')
            ->assertDontSee('Pembayaran terbuka');

        $this->get('/admin/projects')->assertOk()->assertSee($assigned->project_code)
            ->assertDontSee($other->project_code)
            ->assertDontSee($unassigned->project_code);
        $this->get("/admin/projects/{$assigned->id}")->assertOk();
        $this->get("/admin/projects/{$other->id}")->assertNotFound();
        $this->get("/admin/projects/{$unassigned->id}")->assertNotFound();

        foreach ([
            '/admin/consultations', '/admin/customer', '/admin/staff', '/admin/services',
            '/admin/portfolios', '/admin/articles', '/admin/testimonials', '/admin/faqs',
            '/admin/site-settings', '/admin/activity-logs',
        ] as $url) {
            $this->get($url)->assertForbidden();
        }

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->assertSame([$assigned->id], ProjectResource::getEloquentQuery()->pluck('id')->all());
    }

    public function test_customer_cannot_reach_hidden_panel_resource_urls(): void
    {
        $customer = User::factory()->customer()->create();

        foreach (['/admin', '/admin/projects', '/admin/customer', '/admin/consultations'] as $url) {
            $this->actingAs($customer)->get($url)->assertForbidden();
        }
    }
}
