<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $sitemap = rtrim((string) config('app.url'), '/').'/sitemap.xml';

        // Agent pages are kept out of search with a noindex header instead of Disallow:
        // a blocked page can't be crawled, so search engines would never see the noindex.
        $body = implode("\n", [
            'User-agent: *',
            'Allow: /',
            '',
            'Disallow: /dashboard',
            'Disallow: /admin',
            'Disallow: /api',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /checkout',
            '',
            'Sitemap: '.$sitemap,
            '',
        ]);

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
