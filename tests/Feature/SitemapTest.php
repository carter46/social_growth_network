<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_txt_uses_absolute_sitemap_url(): void
    {
        $response = $this->get(route('robots'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $body = $response->getContent();
        $this->assertStringContainsString('Disallow: /admin', $body);
        $this->assertStringContainsString('Sitemap: '.rtrim(config('app.url'), '/').'/sitemap.xml', $body);
        $this->assertStringNotContainsString('Sitemap: /sitemap.xml', $body);
    }

    public function test_static_robots_file_has_the_same_disallow_rules(): void
    {
        preg_match_all('/^Disallow: .+$/m', $this->get(route('robots'))->getContent(), $routeRules);
        preg_match_all('/^Disallow: .+$/m', (string) file_get_contents(public_path('robots.txt')), $fileRules);

        $this->assertContains('Disallow: /admin', $routeRules[0]);
        $this->assertSame($routeRules[0], array_map('rtrim', $fileRules[0]));
    }

    public function test_agent_pages_are_crawlable_so_search_engines_can_see_noindex(): void
    {
        $route = $this->get(route('robots'))->getContent();
        $file = (string) file_get_contents(public_path('robots.txt'));

        foreach (['/agents', '/agent-login', '/agent-onboarding'] as $path) {
            $this->assertStringNotContainsString('Disallow: '.$path, $route);
            $this->assertStringNotContainsString('Disallow: '.$path, $file);
        }
    }

    public function test_agent_pages_are_open_but_marked_noindex(): void
    {
        foreach ([route('agents'), route('agent-login'), route('agent-onboarding')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
                ->assertSee('<meta name="robots" content="noindex, nofollow, noarchive">', false);
        }

        $this->get(route('agents'))->assertDontSee('rel="canonical"', false);
    }

    public function test_public_creator_pages_stay_indexable(): void
    {
        foreach ([route('home'), route('services'), route('about')] as $url) {
            $response = $this->get($url)->assertOk();
            $this->assertNull($response->headers->get('X-Robots-Tag'), $url);
            $response->assertDontSee('name="robots"', false);
        }
    }

    public function test_sitemap_returns_xml_with_static_urls(): void
    {
        Cache::forget('sitemap.xml.v4');

        $response = $this->get(route('sitemap'));

        $response->assertOk();
        $this->assertStringContainsString('application/xml', (string) $response->headers->get('Content-Type'));
        $response->assertSee(route('home'), false);
        $response->assertSee(route('services'), false);
        $response->assertDontSee('/creators', false);
        $response->assertDontSee('<loc>'.route('agents').'</loc>', false);
        $response->assertDontSee('/dashboard/', false);
        $response->assertSee('<?xml version="1.0"', false);
    }
}
