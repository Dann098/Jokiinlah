<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Laravel\Fortify\Fortify;

class TwoFactorSecurityController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $setupKey = null;
        $qrCode = null;
        $recoveryCodes = [];

        if ($user->two_factor_secret && ! $user->two_factor_confirmed_at) {
            $setupKey = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);
            $qrCode = $user->twoFactorQrCodeSvg();
        }

        if ($user->hasEnabledTwoFactorAuthentication() && ! $user->two_factor_recovery_codes_viewed_at) {
            $recoveryCodes = $user->recoveryCodes();
            $user->forceFill(['two_factor_recovery_codes_viewed_at' => now()])->saveQuietly();
        }

        return view('auth.two-factor-setup', compact('user', 'setupKey', 'qrCode', 'recoveryCodes'));
    }
}
