<?php

use App\Http\Middleware\EnsureNotSuspended;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->homeRoute() ?? '/dashboard');
        $middleware->appendToGroup('web', EnsureNotSuspended::class);
        $middleware->appendToGroup('api', EnsureNotSuspended::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Laravel converts TokenMismatchException to a 419 HttpException before render callbacks run.
        $exceptions->render(function (HttpException $e, Request $request) {
            if (! $e->getPrevious() instanceof TokenMismatchException) {
                return null;
            }

            $loggedIn = $request->user() !== null;
            $message = $loggedIn
                ? __('Your session was refreshed. Please try again.')
                : __('Your session expired. Please log in again.');

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'redirect' => $loggedIn ? null : route('login'),
                ], 419);
            }

            $referer = (string) $request->headers->get('referer', '');
            $sameSite = $referer !== '' && str_starts_with($referer, $request->getSchemeAndHttpHost().'/');

            if (! $loggedIn) {
                if ($sameSite && ! str_starts_with($referer, route('login'))) {
                    redirect()->setIntendedUrl($referer);
                }

                return redirect()->route('login')->with('status', $message);
            }

            return redirect()
                ->to($sameSite ? $referer : url('/dashboard'))
                ->withInput($request->except('password', 'password_confirmation', '_token'))
                ->with('error', $message);
        });

        if (class_exists(\Sentry\Laravel\Integration::class)) {
            \Sentry\Laravel\Integration::handles($exceptions);
        }
    })->create();
