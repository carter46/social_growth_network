<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $sitemap = rtrim((string) config('app.url'), '/').'/sitemap.xml';

        $body = implode("\n", [
            'User-agent: *',
            'Allow: /',
            '',
            'Disallow: /dashboard',
            'Disallow: /admin',
            'Disallow: /api',
            'Disallow: /login',
            'Disallow: /agent-login',
            'Disallow: /register',
            'Disallow: /agent-onboarding',
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
