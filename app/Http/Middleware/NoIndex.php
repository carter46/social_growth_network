<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a page out of search results while leaving it open to anyone with the link.
 * Crawlers must be allowed to fetch the page (no robots.txt Disallow), or they never see this.
 */
class NoIndex
{
    public const VALUE = 'noindex, nofollow, noarchive';

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('noindex', true);

        $response = $next($request);
        $response->headers->set('X-Robots-Tag', self::VALUE);

        return $response;
    }
}
