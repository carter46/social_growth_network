<?php

namespace Tests\Feature;

use App\Modules\Catalog\Services\CatalogBrowseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class HomeHeroTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_hero_uses_white_youtube_watch_hours_treatment(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('assets/images/home_whitepng.png', false)
            ->assertSee('Grow your', false)
            ->assertSee('<span class="text-red-600">YouTube</span>', false)
            ->assertSee('watch hours with ready campaign packages', false)
            ->assertSee('id="youtube-services"', false)
            ->assertSee('Pay For Watch Hours', false)
            ->assertSee('Watch &amp; Earn', false)
            ->assertSee(route('register'), false)
            ->assertSee(route('agents'), false)
            ->assertSee('bg-red-600', false)
            ->assertDontSee('· YouTube Watch Hours', false)
            ->assertDontSee('Browse YouTube services', false)
            ->assertDontSee('assets/images/homeslider1.jpg', false)
            ->assertDontSee('home-services-q', false);
    }

    public function test_home_marketplace_excludes_youtube_and_surfaces_youtube_catalog(): void
    {
        Artisan::call('catalog:backfill-hierarchy');
        $this->seed(\Database\Seeders\PlatformCatalogSeeder::class);
        Artisan::call('catalog:backfill-hierarchy');

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('YouTube services', false)
            ->assertSee('YouTube Watch Hours', false)
            ->assertSee('home-yt-featured', false)
            ->assertSee('Other platforms', false)
            ->assertSee('Need other social media services?', false)
            ->assertSee('Earn by completing available digital tasks', false)
            ->assertSee('Learn more', false)
            ->assertSee('Start earning', false)
            ->assertSee(route('register.agent'), false)
            ->assertSee('>All<', false)
            ->assertSee('TikTok', false)
            ->assertSee('Twitter', false)
            ->assertDontSee('id="creators"', false)
            ->assertDontSee('Ready to grow YouTube Watch Hours?', false)
            ->assertDontSee('Guaranteed verified human activity', false)
            ->assertDontSee('Crypto Cash Exchange', false);

        $html = $response->getContent();
        $servicesPos = strpos($html, 'id="services"');
        $this->assertNotFalse($servicesPos);
        $servicesChunk = substr($html, $servicesPos, 2500);
        $this->assertStringNotContainsString(">YouTube</button>", $servicesChunk);

        $browse = app(CatalogBrowseService::class);

        $marketplace = $browse->homeMarketplaceCatalog();
        $this->assertArrayNotHasKey('youtube', $marketplace['products']);
        $this->assertLessThanOrEqual(6, count($marketplace['products']['all'] ?? []));
        $this->assertArrayHasKey('tiktok', $marketplace['products']);
        $this->assertArrayHasKey('twitter', $marketplace['products']);
        foreach ($marketplace['filters'] as $filter) {
            $this->assertNotSame('youtube', $filter['slug'] ?? null);
        }

        $youtube = $browse->homeYouTubeCatalog();
        $this->assertNotNull($youtube['featured']);
        $this->assertSame('YouTube Watch Hours', $youtube['featured']['title'] ?? null);
        $this->assertNotEmpty($youtube['others']);
        $otherTitles = collect($youtube['others'])->pluck('title')->all();
        $this->assertContains('YouTube Views', $otherTitles);
        $this->assertContains('YouTube Likes', $otherTitles);
        $this->assertContains('YouTube Comments', $otherTitles);
    }
}
