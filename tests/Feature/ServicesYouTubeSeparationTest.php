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

    public function test_services_page_puts_youtube_and_other_social_beside_the_filters(): void
    {
        $response = $this->get(route('services'));

        $response->assertOk()
            ->assertSee('services-yt-featured', false)
            ->assertSee('Buy Watch Hours', false)
            ->assertSee('Other YouTube services', false)
            ->assertSee('Other social media services', false)
            ->assertDontSee('Campaign services</h1>', false)
            ->assertDontSee('id="marketplace-search"', false)
            ->assertDontSee('Available campaign services', false);

        $html = $response->getContent();

        $filtersPos = strpos($html, 'id="services-filters-panel"');
        $resultsPos = strpos($html, 'id="services-results"');
        $watchHoursPos = strpos($html, 'id="youtube-watch-hours"');
        $otherYouTubePos = strpos($html, 'id="other-youtube-services"');
        $otherSocialPos = strpos($html, 'id="other-social-services"');

        foreach ([$filtersPos, $resultsPos, $watchHoursPos, $otherYouTubePos, $otherSocialPos] as $pos) {
            $this->assertNotFalse($pos);
        }
        $this->assertLessThan($resultsPos, $filtersPos);
        $this->assertLessThan($watchHoursPos, $resultsPos);
        $this->assertLessThan($otherYouTubePos, $watchHoursPos);
        $this->assertLessThan($otherSocialPos, $otherYouTubePos);

        $filters = substr($html, $filtersPos, $resultsPos - $filtersPos);
        $this->assertStringContainsString('value="youtube"', $filters);
        $this->assertStringContainsString('value="facebook"', $filters);
        $this->assertLessThan(strpos($filters, 'value="facebook"'), strpos($filters, 'value="youtube"'));

        $youtubeChunk = substr($html, $otherYouTubePos, $otherSocialPos - $otherYouTubePos);
        $this->assertStringContainsString('YouTube Views', $youtubeChunk);
        $this->assertStringContainsString('YouTube Likes', $youtubeChunk);
        $this->assertStringContainsString('YouTube Comments', $youtubeChunk);
        $this->assertStringNotContainsString('youtube-watch-hours', $youtubeChunk);

        $otherChunk = substr($html, $otherSocialPos);
        $this->assertStringNotContainsString('youtube-watch-hours', $otherChunk);
        $this->assertStringNotContainsString('YouTube Views', $otherChunk);
        $this->assertStringNotContainsString('YouTube Likes', $otherChunk);
        $this->assertStringContainsString('Facebook', $otherChunk);
        $this->assertStringContainsString('Instagram', $otherChunk);
        $this->assertStringContainsString('TikTok', $otherChunk);
        $this->assertStringContainsString('Twitter', $otherChunk);
    }

    public function test_youtube_filter_shows_only_youtube_services(): void
    {
        $html = $this->get(route('services', ['category' => 'youtube']), [
            'X-Services-Filter' => '1',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()->getContent();

        $this->assertStringContainsString('id="youtube-watch-hours"', $html);
        $this->assertStringContainsString('Other YouTube services', $html);
        $this->assertStringContainsString('YouTube Likes', $html);
        $this->assertStringNotContainsString('id="other-social-services"', $html);
        $this->assertStringNotContainsString('Facebook Likes', $html);
    }

    public function test_platform_filter_hides_youtube_blocks(): void
    {
        $html = $this->get(route('services', ['category' => 'facebook']), [
            'X-Services-Filter' => '1',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()->getContent();

        $this->assertStringContainsString('Facebook Likes', $html);
        $this->assertStringNotContainsString('services-yt-featured', $html);
        $this->assertStringNotContainsString('Other YouTube services', $html);
        $this->assertStringNotContainsString('YouTube Likes', $html);
    }

    public function test_products_without_admin_images_render_no_stock_images(): void
    {
        PlatformProduct::query()->update(['hero_image' => null, 'hero_media_id' => null]);

        $html = $this->get(route('services'))->assertOk()->getContent();
        $resultsPos = strpos($html, 'id="services-results"');
        $howPos = strpos($html, 'How ordering works');
        $this->assertNotFalse($resultsPos);
        $this->assertNotFalse($howPos);

        $this->assertStringNotContainsString('Social_Media.jpg', $html);
        $this->assertStringNotContainsString('<img', substr($html, $resultsPos, $howPos - $resultsPos));

        $likes = PlatformProduct::query()->where('slug', 'youtube-likes')->firstOrFail();
        $canonical = app(\App\Modules\Catalog\Services\CatalogBrowseService::class)->productUrl($likes);
        $this->get($canonical)
            ->assertOk()
            ->assertDontSee('src="'.asset('assets/images/Image_ro410gro410gro41.png').'"', false);
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
}
