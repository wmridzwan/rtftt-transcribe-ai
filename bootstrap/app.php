<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')->group(base_path('routes/translation.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => AdminMiddleware::class,
        ]);

        // P7-005: global (prepended) so every HTTP response carries the
        // correlation id, including 404/419 and the `/up` health endpoint.
        $middleware->prepend(AssignRequestId::class);

        // P7-006: hardening headers + CSP on every response (appended, so
        // it runs after route middleware and can audit denial statuses).
        // CSP reports are cross-context posts: CSRF-exempt + throttled.
        $middleware->append(SecurityHeaders::class);
        $middleware->validateCsrfTokens(except: ['csp-report']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
