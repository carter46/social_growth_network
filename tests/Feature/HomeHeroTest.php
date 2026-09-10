<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeHeroTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_hero_uses_three_fading_background_images(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('assets/images/homeslider1.jpg', false)
            ->assertSee('assets/images/homeslider2.jpg', false)
            ->assertSee('assets/images/homeslider3.jpg', false)
            ->assertSee('transition-opacity duration-1000', false)
            ->assertSee(route('services'), false);
    }

    public function test_home_marketplace_shows_products_with_category_filters(): void
    {
        \Illuminate\Support\Facades\Artisan::call('catalog:backfill-hierarchy');
        $this->seed(\Database\Seeders\PlatformCatalogSeeder::class);
        \Illuminate\Support\Facades\Artisan::call('catalog:backfill-hierarchy');

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('What do you want to grow?', false)
            ->assertSee('YouTube', false)
            ->assertSee('TikTok', false)
            ->assertSee('Twitter', false)
            ->assertSee('>All<', false)
            ->assertDontSee('Crypto Cash Exchange', false);

        $catalog = app(\App\Modules\Catalog\Services\CatalogBrowseService::class)->homeMarketplaceCatalog();
        $this->assertLessThanOrEqual(6, count($catalog['products']['all'] ?? []));
        $this->assertArrayHasKey('tiktok', $catalog['products']);
        $this->assertArrayHasKey('twitter', $catalog['products']);
        $this->assertLessThanOrEqual(6, count($catalog['products']['youtube'] ?? []));
    }
}
