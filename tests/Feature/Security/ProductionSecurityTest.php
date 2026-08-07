<?php

namespace Tests\Feature\Security;

use App\Models\Consultation;
use App\Notifications\NewConsultationNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_responses_include_restrictive_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringNotContainsString('*', $csp);
        $this->assertFalse($response->headers->has('Strict-Transport-Security'));
    }

    public function test_hsts_is_only_sent_for_secure_production_requests(): void
    {
        $this->app->instance('env', 'production');

        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/')
            ->assertHeader('Strict-Transport-Security');
    }

    public function test_readiness_guard_fails_for_local_configuration(): void
    {
        $this->artisan('jokiinlah:readiness')
            ->expectsOutputToContain('LibreOffice binary tersedia')
            ->expectsOutputToContain('Isolasi LibreOffice diverifikasi')
            ->expectsOutputToContain('Ekstensi ZIP aktif')
            ->expectsOutputToContain('Workspace konversi privat')
            ->expectsOutputToContain('Process execution PHP aktif')
            ->expectsOutputToContain('Deployment production harus ditunda.')
            ->assertFailed();
    }

    public function test_production_session_cookie_can_be_secure_http_only_and_same_site(): void
    {
        config([
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
        ]);

        $response = $this->withSession(['security_probe' => true])->get('https://localhost/');
        $cookie = collect($response->headers->getCookies())->first(
            fn ($cookie): bool => $cookie->getName() === config('session.cookie'),
        );

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
    }

    public function test_security_maintenance_commands_are_registered_with_scheduler(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('jokiinlah:retention-evaluate')
            ->expectsOutputToContain('jokiinlah:purge')
            ->expectsOutputToContain('jokiinlah:files-reconcile')
            ->assertSuccessful();
    }

    public function test_consultation_notification_is_queued_after_commit_with_retry_policy(): void
    {
        $notification = new NewConsultationNotification(Consultation::factory()->make());

        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertTrue($notification->afterCommit);
        $this->assertSame(3, $notification->tries);
        $this->assertSame(30, $notification->timeout);
        $this->assertSame([30, 120, 300], $notification->backoff());
    }
}
