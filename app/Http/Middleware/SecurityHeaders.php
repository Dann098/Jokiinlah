<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            $this->connectSource($request),
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]);

        $cspHeader = config('security.headers.csp_report_only')
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';
        if (! $response->headers->has('Content-Security-Policy')
            && ! $response->headers->has('Content-Security-Policy-Report-Only')) {
            $response->headers->set($cspHeader, $csp);
        }

        if (app()->environment('production') && $request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age='.max(0, (int) config('security.headers.hsts_max_age')).'; includeSubDomains',
            );
        }

        return $response;
    }

    private function connectSource(Request $request): string
    {
        if (app()->environment('production')) {
            return "connect-src 'self'";
        }

        $host = $request->getHost();

        return "connect-src 'self' http://{$host}:5173 ws://{$host}:5173";
    }
}
