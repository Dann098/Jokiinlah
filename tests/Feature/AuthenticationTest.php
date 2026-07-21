<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_always_creates_an_unverified_active_customer(): void
    {
        $response = $this->post('/register', [
            'name' => 'Pelanggan Baru', 'email' => 'new@example.com', 'phone' => '+6281234567890',
            'password' => 'Jkl!Phase2_2026Safe', 'password_confirmation' => 'Jkl!Phase2_2026Safe',
            'role' => 'admin', 'is_active' => false,
        ]);

        $response->assertRedirect('/dashboard');
        $user = User::query()->where('email', 'new@example.com')->firstOrFail();
        $this->assertSame(UserRole::Customer, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertNull($user->email_verified_at);
    }

    public function test_inactive_user_cannot_login_and_error_is_generic(): void
    {
        User::factory()->inactive()->create(['email' => 'inactive@example.com']);
        $response = $this->from('/login')->post('/login', ['email' => 'inactive@example.com', 'password' => 'password']);
        $response->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_customer_and_staff_receive_role_aware_login_redirects(): void
    {
        $customer = User::factory()->customer()->create();
        $this->post('/login', ['email' => $customer->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->post('/logout');
        $staff = User::factory()->staff()->create();
        $this->post('/login', ['email' => $staff->email, 'password' => 'password'])->assertRedirect('/admin');
    }

    public function test_unverified_user_is_blocked_from_dashboard(): void
    {
        $this->actingAs(User::factory()->unverified()->create())->get('/dashboard')->assertRedirect('/email/verify');
    }

    public function test_password_reset_notification_can_be_requested(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_login_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => 'missing@example.com', 'password' => 'invalid']);
        }
        $this->post('/login', ['email' => 'missing@example.com', 'password' => 'invalid'])->assertStatus(429);
    }
}
