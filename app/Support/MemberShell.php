<?php

namespace App\Support;

use Illuminate\Http\Request;

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
}
