<?php

use App\Http\Controllers\Realtime\BroadcastAuthController;
use App\Http\Middleware\EnsureActiveStaff;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PrivateQueueResponse;
use App\Http\Middleware\StaffPrivacy;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Custom POST route retains web CSRF protection, unlike Broadcast::routes().
            Route::post('broadcasting/auth', BroadcastAuthController::class)
                ->middleware(['web', 'auth', 'staff.active', 'throttle:realtime.auth', StaffPrivacy::class])
                ->name('broadcasting.auth');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'staff.active' => EnsureActiveStaff::class,
            'role' => EnsureRole::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            EnsureActiveStaff::class,
            AuthenticateSession::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response): Response {
            return request()->is('antrean', 'antrean/*', 'staff/antrean/*', 'admin/*', 'barber/*', 'dashboard', 'broadcasting/auth')
                ? PrivateQueueResponse::secure($response) : $response;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
