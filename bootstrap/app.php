<?php

use App\Exceptions\UnsafeUploadException;
use App\Http\Middleware\ConfiguredTrustProxies;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withCommands()
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->replace(TrustProxies::class, ConfiguredTrustProxies::class);
        $middleware->web(append: [
            EnsureAccountIsActive::class,
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'active' => EnsureAccountIsActive::class,
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (UnsafeUploadException $exception, Request $request) {
            $field = $request->hasFile('attachment') ? 'attachment' : 'file';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'errors' => [$field => [$exception->getMessage()]],
                ], 422);
            }

            return back()
                ->withInput($request->except(['file', 'attachment']))
                ->withErrors([$field => $exception->getMessage()]);
        });
    })->create();
