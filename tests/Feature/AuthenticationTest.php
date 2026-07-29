<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
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
        $response = $this->from('/login')->post('/login', ['email' => 'inactive@example.com', 'password' => 'Password123!']);
        $response->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_customer_and_staff_receive_role_aware_login_redirects(): void
    {
        $customer = User::factory()->customer()->create();
        $this->post('/login', ['email' => $customer->email, 'password' => 'Password123!'])->assertRedirect('/dashboard');
        $this->post('/logout');
        $staff = User::factory()->staff()->withoutTwoFactor()->create();
        $this->post('/login', ['email' => $staff->email, 'password' => 'Password123!'])->assertRedirect('/admin');
        $this->get('/admin')->assertRedirect(route('security.two-factor.setup'));
        $this->post('/logout');

        $admin = User::factory()->admin()->withoutTwoFactor()->create();
        $this->post('/login', ['email' => $admin->email, 'password' => 'Password123!'])->assertRedirect('/admin');
        $this->get('/admin')->assertRedirect(route('security.two-factor.setup'));
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

    public function test_password_can_be_reset_with_the_issued_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');

        $token = null;
        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use (&$token): bool {
                $token = $notification->token;

                return true;
            },
        );

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Jkl!T7_2026_Unik#Zebra9842',
            'password_confirmation' => 'Jkl!T7_2026_Unik#Zebra9842',
        ])->assertSessionHas('status');

        $this->assertTrue(Hash::check('Jkl!T7_2026_Unik#Zebra9842', $user->fresh()->password));
    }

    public function test_signed_email_verification_link_verifies_the_customer(): void
    {
        $user = User::factory()->unverified()->create();
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinute(),
            ['id' => $user->getKey(), 'hash' => sha1($user->email)],
        );

        $this->actingAs($user)->get($verificationUrl)->assertRedirect('/dashboard?verified=1');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_login_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => 'missing@example.com', 'password' => 'invalid']);
        }
        $this->post('/login', ['email' => 'missing@example.com', 'password' => 'invalid'])->assertStatus(429);
    }
}
