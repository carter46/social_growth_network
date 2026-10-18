<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->is_suspended) {
            $loginRoute = $user->isAgent() ? 'agent-login' : 'login';

            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = __('Your account has been suspended. If you think this is a mistake, please contact our support team.');

            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'redirect' => route($loginRoute)], 403);
            }

            return redirect()->route($loginRoute)->with('error', $message);
        }

        return $next($request);
    }
}
