<?php

namespace Tests\Feature;

use App\Models\PlatformProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ServicesYouTubeSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('catalog:backfill-hierarchy');
        $this->seed(\Database\Seeders\PlatformCatalogSeeder::class);
        Artisan::call('catalog:backfill-hierarchy');
    }

    public function test_services_page_separates_youtube_from_other_social_catalog(): void
    {
        $response = $this->get(route('services'));

        $response->assertOk()
            ->assertSee('id="youtube-watch-hours"', false)
            ->assertSee('services-yt-featured', false)
            ->assertSee('YouTube Watch Hours', false)
            ->assertSee('Other YouTube services', false)
            ->assertSee('Other social media services', false)
            ->assertSee('id="other-social-services"', false)
            ->assertDontSee('home-hero relative flex items-center overflow-hidden bg-slate-900', false);

        $html = $response->getContent();

        $ytPos = strpos($html, 'id="youtube-watch-hours"');
        $otherPos = strpos($html, 'id="other-social-services"');
        $this->assertNotFalse($ytPos);
        $this->assertNotFalse($otherPos);
        $this->assertLessThan($otherPos, $ytPos);

        $ytChunk = substr($html, $ytPos, $otherPos - $ytPos);
        $this->assertStringContainsString('YouTube Watch Hours', $ytChunk);
        $this->assertStringContainsString('YouTube Views', $ytChunk);
        $this->assertStringContainsString('YouTube Likes', $ytChunk);
        $this->assertStringContainsString('YouTube Comments', $ytChunk);

        $otherChunk = substr($html, $otherPos);
        $this->assertStringNotContainsString('name="category" value="youtube"', $otherChunk);
        $this->assertStringNotContainsString('>YouTube</', $otherChunk);
        $this->assertStringNotContainsString('YouTube Watch Hours', $otherChunk);
        $this->assertStringNotContainsString('YouTube Views', $otherChunk);
        $this->assertStringContainsString('Facebook', $otherChunk);
        $this->assertStringContainsString('Instagram', $otherChunk);
        $this->assertStringContainsString('TikTok', $otherChunk);
        $this->assertStringContainsString('Twitter', $otherChunk);
    }

    public function test_direct_youtube_product_urls_remain_accessible(): void
    {
        $product = PlatformProduct::query()
            ->where('slug', 'youtube-watch-hours')
            ->first();

        $this->assertNotNull($product);

        $this->get(route('services.segment', 'youtube-watch-hours'))
            ->assertRedirect();

        $canonical = app(\App\Modules\Catalog\Services\CatalogBrowseService::class)->productUrl($product);
        $this->get($canonical)->assertOk();
    }

    public function test_category_youtube_query_does_not_leak_into_other_social_grid(): void
    {
        $response = $this->get(route('services', ['category' => 'youtube']));

        $response->assertOk();
        $html = $response->getContent();
        $otherPos = strpos($html, 'id="other-social-services"');
        $this->assertNotFalse($otherPos);
        $otherChunk = substr($html, $otherPos);
        $this->assertStringNotContainsString('YouTube Watch Hours', $otherChunk);
        $this->assertStringNotContainsString('YouTube Views', $otherChunk);
    }
}
