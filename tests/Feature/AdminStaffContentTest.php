<?php

namespace Tests\Feature;

use App\Actions\SiteSettings\UpdateSiteSetting;
use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\Faq;
use App\Models\Portfolio;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminStaffContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_content_policies_are_admin_only_while_public_scopes_remain_correct(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create();

        foreach ([Service::class, Portfolio::class, Article::class, Testimonial::class, Faq::class] as $model) {
            $this->assertTrue($admin->can('viewAny', $model));
            $this->assertFalse($staff->can('viewAny', $model));
            $this->assertFalse($customer->can('viewAny', $model));
        }

        $draft = Article::factory()->create(['is_published' => false, 'published_at' => null]);
        $future = Article::factory()->create(['is_published' => true, 'published_at' => now()->addDay()]);
        $published = Article::factory()->create(['is_published' => true, 'published_at' => now()->subDay()]);
        $response = $this->get(route('articles.index'));
        $response->assertOk()->assertSee($published->title)->assertDontSee($draft->title)->assertDontSee($future->title);
    }

    public function test_site_settings_are_whitelisted_validated_and_audited(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $whatsapp = SiteSetting::query()->create([
            'key' => 'whatsapp_number', 'value' => '628123456789', 'type' => 'string', 'group' => 'general', 'is_public' => true,
        ]);
        $secret = SiteSetting::query()->create([
            'key' => 'api_secret', 'value' => 'old', 'type' => 'string', 'group' => 'secret', 'is_public' => false,
        ]);

        $this->assertTrue($admin->can('update', $whatsapp));
        $this->assertFalse($staff->can('update', $whatsapp));
        $this->assertFalse($admin->can('create', SiteSetting::class));
        $this->assertFalse($admin->can('delete', $whatsapp));

        app(UpdateSiteSetting::class)->execute($whatsapp, '+628111222333', $admin);
        $this->assertSame('+628111222333', $whatsapp->refresh()->value);
        $this->assertDatabaseHas('activity_logs', ['action' => 'site_setting.updated', 'model_id' => $whatsapp->id]);

        foreach ([[$whatsapp, 'javascript:alert(1)'], [$secret, 'new-secret']] as [$setting, $value]) {
            try {
                app(UpdateSiteSetting::class)->execute($setting, $value, $admin);
                $this->fail('Nilai atau key pengaturan yang tidak aman seharusnya ditolak.');
            } catch (ValidationException) {
                $this->assertNotSame($value, $setting->refresh()->value);
            }
        }
    }

    public function test_activity_log_is_immutable_from_every_panel_role(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create();
        $log = ActivityLog::factory()->create(['metadata' => ['changed_fields' => ['name']]]);

        $this->assertTrue($admin->can('view', $log));
        $this->assertFalse($staff->can('view', $log));
        $this->assertFalse($customer->can('view', $log));
        $this->assertFalse($admin->can('create', ActivityLog::class));
        $this->assertFalse($admin->can('update', $log));
        $this->assertFalse($admin->can('delete', $log));
        $this->actingAs($admin)->get("/admin/activity-logs/{$log->id}")->assertOk()->assertDontSee('user_agent');
        $this->get("/admin/activity-logs/{$log->id}/edit")->assertNotFound();
    }
}
