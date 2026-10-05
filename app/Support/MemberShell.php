<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class MemberShell
{
    public static function prefix(?Request $request = null): string
    {
        $request ??= request();

        if ($request->routeIs('agent') || $request->routeIs('agent.*')) {
            return 'agent';
        }

        return 'dashboard';
    }

    public static function layout(?Request $request = null): string
    {
        return self::prefix($request) === 'agent'
            ? 'layouts.dashboard-agent'
            : 'layouts.dashboard-user';
    }

    public static function route(string $suffix, mixed $parameters = [], bool $absolute = true, ?Request $request = null): string
    {
        $name = self::prefix($request).($suffix !== '' ? '.'.$suffix : '');

        return route($name, $parameters, $absolute);
    }

    public static function isAgent(?Request $request = null): bool
    {
        return self::prefix($request) === 'agent';
    }

    /**
     * Member route for the signed-in user's role, usable outside the member shells
     * (marketing pages, shared components) where prefix() cannot infer the role.
     */
    public static function userRoute(string $suffix, mixed $parameters = []): string
    {
        $name = (auth()->user()?->isAgent() ? 'agent' : 'dashboard').'.'.$suffix;

        return route(Route::has($name) ? $name : 'dashboard.'.$suffix, $parameters);
    }
}
