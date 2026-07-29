<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePanelTwoFactorEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ($user->isAdmin() || $user->isStaff()) && ! $user->hasEnabledTwoFactorAuthentication()) {
            return redirect()->route('security.two-factor.setup')
                ->with('warning', 'Aktifkan autentikasi dua faktor sebelum menggunakan panel operasional.');
        }

        if ($user && ($user->isAdmin() || $user->isStaff())
            && (int) $request->session()->get('security.two_factor_passed_user') !== (int) $user->getKey()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Masuk melalui halaman ini untuk menyelesaikan challenge autentikasi dua faktor.']);
        }

        return $next($request);
    }
}
