<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowed = array_values(array_filter(array_map(
            static fn (string $role): ?UserRole => UserRole::tryFrom($role),
            $roles,
        )));

        abort_unless($request->user() && in_array($request->user()->role, $allowed, true), 403);

        return $next($request);
    }
}
