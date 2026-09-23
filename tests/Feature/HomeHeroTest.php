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
            ->assertSee('Grow your YouTube watch hours', false)
            ->assertSee('id="youtube-services"', false)
            ->assertSee('Start Watch Hours', false)
            ->assertSee('Browse YouTube services', false)
            ->assertDontSee('assets/images/homeslider1.jpg', false)
            ->assertDontSee('assets/images/creators-hero.jpg', false)
            ->assertDontSee('home-services-q', false)
            ->assertDontSee('Search campaign services', false);
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
            ->assertSee('Need Facebook, Instagram, TikTok, or Twitter?', false)
            ->assertSee('>All<', false)
            ->assertSee('TikTok', false)
            ->assertSee('Twitter', false)
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
