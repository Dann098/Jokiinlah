<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_roles_must_configure_two_factor_but_customers_do_not(): void
    {
        $admin = User::factory()->admin()->withoutTwoFactor()->create();
        $staff = User::factory()->staff()->withoutTwoFactor()->create();
        $customer = User::factory()->customer()->create();

        $this->actingAs($admin)->get('/admin')->assertRedirect(route('security.two-factor.setup'));
        $this->actingAs($staff)->get('/admin')->assertRedirect(route('security.two-factor.setup'));
        $this->actingAs($customer)->get(route('customer.dashboard'))->assertOk();
    }

    public function test_admin_can_enable_confirm_and_view_recovery_codes_once(): void
    {
        $admin = User::factory()->admin()->withoutTwoFactor()->create();
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);

        $this->post(route('two-factor.enable'))->assertRedirect();
        $admin->refresh();
        $this->assertNotNull($admin->two_factor_secret);
        $this->assertNull($admin->two_factor_confirmed_at);

        $secret = Fortify::currentEncrypter()->decrypt($admin->two_factor_secret);
        $code = (new Google2FA)->getCurrentOtp($secret);
        $this->post(route('two-factor.confirm'), ['code' => $code])->assertRedirect();

        $admin->refresh();
        $recoveryCode = $admin->recoveryCodes()[0];
        $this->assertNotNull($admin->two_factor_confirmed_at);
        $this->get(route('security.two-factor.setup'))->assertOk()->assertSee($recoveryCode);
        $this->get(route('security.two-factor.setup'))->assertOk()->assertDontSee($recoveryCode);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'security.two_factor_enabled',
        ]);
    }

    public function test_valid_totp_completes_panel_login_and_invalid_code_is_rejected(): void
    {
        $secret = $this->secret();
        $admin = $this->enabledAdmin($secret);

        $this->post('/login', ['email' => $admin->email, 'password' => 'Password123!'])
            ->assertRedirect(route('two-factor.login'));

        $this->post(route('two-factor.login.store'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $code = (new Google2FA)->getCurrentOtp($secret);
        $this->post(route('two-factor.login.store'), ['code' => $code])
            ->assertRedirect(route('filament.admin.pages.dashboard'));
        $this->assertAuthenticatedAs($admin);
        $this->get(route('filament.admin.pages.dashboard'))->assertOk();
    }

    public function test_panel_session_without_two_factor_challenge_is_terminated(): void
    {
        $admin = $this->enabledAdmin($this->secret());
        $this->be($admin);

        $this->get(route('filament.admin.pages.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_recovery_code_is_single_use(): void
    {
        $admin = $this->enabledAdmin($this->secret(), ['once-only-code']);

        $this->post('/login', ['email' => $admin->email, 'password' => 'Password123!']);
        $this->post(route('two-factor.login.store'), ['recovery_code' => 'once-only-code'])
            ->assertRedirect(route('filament.admin.pages.dashboard'));
        $this->assertAuthenticatedAs($admin);

        $this->post('/logout');
        $this->post('/login', ['email' => $admin->email, 'password' => 'Password123!']);
        $this->post(route('two-factor.login.store'), ['recovery_code' => 'once-only-code'])
            ->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
    }

    public function test_two_factor_challenge_is_rate_limited(): void
    {
        $admin = $this->enabledAdmin($this->secret());
        $this->post('/login', ['email' => $admin->email, 'password' => 'Password123!']);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('two-factor.login.store'), ['code' => '000000'])
                ->assertSessionHasErrors('code');
        }

        $this->post(route('two-factor.login.store'), ['code' => '000000'])
            ->assertTooManyRequests();
        $this->assertGuest();
    }

    public function test_setup_page_never_redisplays_enabled_secrets_or_recovery_codes(): void
    {
        $secret = $this->secret();
        $admin = $this->enabledAdmin($secret, ['do-not-expose']);

        $this->actingAs($admin)
            ->get(route('security.two-factor.setup'))
            ->assertOk()
            ->assertDontSee($secret)
            ->assertDontSee('do-not-expose');
    }

    public function test_setup_page_prevents_sensitive_two_factor_data_from_being_cached(): void
    {
        $admin = User::factory()->admin()->withoutTwoFactor()->create();

        $response = $this->actingAs($admin)
            ->get(route('security.two-factor.setup'))
            ->assertOk();

        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
        );
    }

    /**
     * @param  array<int, string>  $recoveryCodes
     */
    private function enabledAdmin(string $secret, array $recoveryCodes = ['backup-code']): User
    {
        return User::factory()->admin()->create([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode($recoveryCodes)),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes_viewed_at' => now(),
        ]);
    }

    private function secret(): string
    {
        return (new Google2FA)->generateSecretKey();
    }
}
