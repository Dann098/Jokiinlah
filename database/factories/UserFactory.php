<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'phone' => '+628'.fake()->numerify('##########'),
            'password' => static::$password ??= Hash::make('Password123!'),
            'role' => UserRole::Customer,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => array_merge(
            ['role' => UserRole::Admin],
            $this->enabledTwoFactorState(),
        ));
    }

    public function staff(): static
    {
        return $this->state(fn () => array_merge(
            ['role' => UserRole::Staff],
            $this->enabledTwoFactorState(),
        ));
    }

    public function customer(): static
    {
        return $this->state(fn () => ['role' => UserRole::Customer]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function withoutTwoFactor(): static
    {
        return $this->state(fn () => [
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes_viewed_at' => null,
        ]);
    }

    private function enabledTwoFactorState(): array
    {
        $secret = (new Google2FA)->generateSecretKey();

        return [
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode([
                Str::random(32),
                Str::random(32),
            ])),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes_viewed_at' => now(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
