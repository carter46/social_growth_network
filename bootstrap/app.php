<?php

use App\Http\Middleware\EnsureNotSuspended;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $trustedProxies = env('TRUSTED_PROXIES');
        $middleware->trustProxies(
            at: is_string($trustedProxies) && $trustedProxies !== ''
                ? array_map('trim', explode(',', $trustedProxies))
                : (env('APP_ENV') === 'production' ? '*' : null),
        );
        $middleware->append(SecurityHeaders::class);
        $middleware->validateCsrfTokens(except: [
            'webhooks/monnify',
            'webhooks/site-integrations/*',
        ]);
        $middleware->alias([
            'not_suspended' => EnsureNotSuspended::class,
            'has_wallet' => \App\Http\Middleware\EnsureHasWallet::class,
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);
        $middleware->appendToGroup('web', EnsureNotSuspended::class);
        $middleware->appendToGroup('api', EnsureNotSuspended::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('Your session expired. Please refresh the page and try again.'),
                ], 419);
            }

            $redirectTo = $request->headers->get('referer');
            if (! is_string($redirectTo) || $redirectTo === '') {
                $redirectTo = route('login');
            }

            return redirect()
                ->to($redirectTo)
                ->withInput($request->except('password', 'password_confirmation', '_token'))
                ->with('error', __('Your session expired. Please refresh the page and try again.'));
        });

        if (class_exists(\Sentry\Laravel\Integration::class)) {
            \Sentry\Laravel\Integration::handles($exceptions);
        }
    })->create();
