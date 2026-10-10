<?php

namespace Tests\Feature;

use App\Models\PlatformProduct;
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
            ->assertSee('Pay People to Subscribe, Watch, Like and Comment on Your <span class="text-red-600">YouTube</span> Videos.', false)
            ->assertSee('id="youtube-services"', false)
            ->assertSee('Buy Watch Hours', false)
            ->assertDontSee('Pay For Watch Hours', false)
            ->assertSee('Watch &amp; Earn', false)
            ->assertSee(route('register'), false)
            ->assertSee(route('agents'), false)
            ->assertSee('bg-red-600', false)
            ->assertDontSee('· YouTube Watch Hours', false)
            ->assertDontSee('Browse YouTube services', false)
            ->assertDontSee('assets/images/homeslider1.jpg', false)
            ->assertDontSee('home-services-q', false);
    }

    public function test_home_shows_only_youtube_catalog(): void
    {
        Artisan::call('catalog:backfill-hierarchy');
        $this->seed(\Database\Seeders\PlatformCatalogSeeder::class);
        Artisan::call('catalog:backfill-hierarchy');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('YouTube services', false)
            ->assertSee('YouTube Watch Hours', false)
            ->assertSee('home-yt-featured', false)
            ->assertSee('Earn by completing available digital tasks', false)
            ->assertSee('Learn more', false)
            ->assertSee('Start earning', false)
            ->assertSee(route('register.agent'), false)
            ->assertDontSee('Other platforms', false)
            ->assertDontSee('Need other social media services?', false)
            ->assertDontSee('TikTok', false)
            ->assertDontSee('Instagram', false)
            ->assertDontSee('id="services"', false)
            ->assertDontSee('id="creators"', false)
            ->assertDontSee('Ready to grow YouTube Watch Hours?', false)
            ->assertDontSee('Guaranteed verified human activity', false)
            ->assertDontSee('Crypto Cash Exchange', false);

        $youtube = app(CatalogBrowseService::class)->homeYouTubeCatalog();
        $this->assertNotNull($youtube['featured']);
        $this->assertSame('YouTube Watch Hours', $youtube['featured']['title'] ?? null);
        $otherTitles = collect($youtube['others'])->pluck('title')->all();
        $this->assertContains('YouTube Views', $otherTitles);
        $this->assertContains('YouTube Likes', $otherTitles);
        $this->assertContains('YouTube Comments', $otherTitles);
        $this->assertNotContains('YouTube Subscribers', $otherTitles);
    }

    public function test_home_youtube_cards_without_admin_image_render_no_stock_image(): void
    {
        Artisan::call('catalog:backfill-hierarchy');
        $this->seed(\Database\Seeders\PlatformCatalogSeeder::class);
        Artisan::call('catalog:backfill-hierarchy');

        PlatformProduct::query()
            ->where('slug', 'like', 'youtube-%')
            ->update(['hero_image' => null, 'hero_media_id' => null]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $start = strpos($html, 'id="youtube-services"');
        $end = strpos($html, 'id="agents"');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $youtubeChunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('More YouTube packages', $youtubeChunk);
        $this->assertStringContainsString('YouTube Likes', $youtubeChunk);
        $this->assertStringContainsString('YouTube Comments', $youtubeChunk);
        $this->assertStringNotContainsString('Social_Media.jpg', $youtubeChunk);
        $this->assertStringNotContainsString('<img', $youtubeChunk);
    }
}
