<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\ActivityLogger;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;

class AuditTwoFactorSecurityEvent
{
    public function __construct(private ActivityLogger $logger) {}

    public function handle(object $event): void
    {
        $user = $event->user ?? null;

        if (! $user instanceof User) {
            return;
        }

        [$action, $description] = match (true) {
            $event instanceof TwoFactorAuthenticationEnabled => [
                'security.two_factor_setup_started',
                'Pengguna panel memulai konfigurasi autentikasi dua faktor.',
            ],
            $event instanceof TwoFactorAuthenticationConfirmed => [
                'security.two_factor_enabled',
                'Pengguna panel mengaktifkan autentikasi dua faktor.',
            ],
            $event instanceof TwoFactorAuthenticationDisabled => [
                'security.two_factor_disabled',
                'Pengguna panel menonaktifkan autentikasi dua faktor.',
            ],
            $event instanceof RecoveryCodesGenerated => [
                $user->two_factor_confirmed_at ? 'security.recovery_codes_regenerated' : 'security.recovery_codes_generated',
                'Kode pemulihan autentikasi dua faktor dibuat.',
            ],
            $event instanceof RecoveryCodeReplaced => [
                'security.recovery_code_used',
                'Satu kode pemulihan autentikasi dua faktor digunakan.',
            ],
            $event instanceof TwoFactorAuthenticationFailed => [
                'security.two_factor_challenge_failed',
                'Challenge autentikasi dua faktor ditolak.',
            ],
            $event instanceof ValidTwoFactorAuthenticationCodeProvided => [
                'security.two_factor_challenge_passed',
                'Challenge autentikasi dua faktor berhasil.',
            ],
            default => [null, null],
        };

        if ($event instanceof RecoveryCodesGenerated) {
            $user->forceFill(['two_factor_recovery_codes_viewed_at' => null])->saveQuietly();
        }

        if ($event instanceof TwoFactorAuthenticationDisabled) {
            $user->forceFill(['two_factor_recovery_codes_viewed_at' => null])->saveQuietly();
        }

        if ($action) {
            $this->logger->log($action, $description, $user, $user);
        }
    }
}
