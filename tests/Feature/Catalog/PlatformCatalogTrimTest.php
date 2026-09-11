<?php

namespace Tests\Feature\Catalog;

use App\Enums\PlatformProductType;
use App\Models\PlatformProduct;
use App\Models\ServiceCategory;
use App\Support\PlatformCatalogTrim;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PlatformCatalogTrimTest extends TestCase
{
    use RefreshDatabase;

    public function test_retires_disallowed_social_media_products(): void
    {
        $this->seed(\Database\Seeders\PlatformCatalogSeeder::class);

        foreach ([
            ['slug' => 'linkedin-lead-boost', 'title' => 'LinkedIn Lead Boost'],
            ['slug' => 'multi-platform-starter', 'title' => 'Multi-Platform Starter'],
        ] as $row) {
            $product = new PlatformProduct;
            $product->forceFill([
                'slug' => $row['slug'],
                'title' => $row['title'],
                'product_type' => PlatformProductType::SocialService,
                'short_description' => 'Test product',
                'description' => 'Test product',
                'status' => 'published',
                'base_price' => 10000,
                'provider' => 'manual',
                'fulfillment_mode' => 'manual',
            ])->save();
        }

        PlatformCatalogTrim::apply();

        $this->assertDatabaseHas('platform_products', ['slug' => 'youtube-views']);
        $this->assertDatabaseHas('platform_products', ['slug' => 'youtube-likes']);
        $this->assertDatabaseHas('platform_products', ['slug' => 'youtube-comments']);
        $this->assertDatabaseHas('platform_products', ['slug' => 'youtube-watch-hours']);
        $this->assertDatabaseMissing('platform_products', ['slug' => 'youtube-subscribers']);
        $this->assertDatabaseHas('platform_products', ['slug' => 'facebook-views']);
        $this->assertDatabaseHas('platform_products', ['slug' => 'instagram-views']);
        $this->assertDatabaseHas('platform_products', ['slug' => 'tiktok-views']);
        $this->assertDatabaseHas('platform_products', ['slug' => 'twitter-views']);

        $this->assertDatabaseMissing('platform_products', ['slug' => 'linkedin-lead-boost']);
        $this->assertDatabaseMissing('platform_products', ['slug' => 'multi-platform-starter']);
        $this->assertDatabaseMissing('platform_products', ['slug' => 'youtube-views-lite']);
        $this->assertDatabaseMissing('platform_products', ['slug' => 'instagram-growth-pack']);

        $this->assertSame(16, PlatformProduct::query()->ofType(PlatformProductType::SocialService)->count());
    }

    public function test_trim_keeps_legacy_views_slugs_until_renamed(): void
    {
        Artisan::call('catalog:backfill-hierarchy');

        $product = new PlatformProduct;
        $product->forceFill([
            'slug' => 'youtube-views-lite',
            'title' => 'YouTube Views Lite',
            'product_type' => PlatformProductType::SocialService,
            'short_description' => 'Legacy',
            'description' => 'Legacy',
            'status' => 'published',
            'base_price' => 5000,
            'provider' => 'manual',
            'fulfillment_mode' => 'manual',
        ])->save();

        PlatformCatalogTrim::apply();

        $this->assertDatabaseHas('platform_products', ['slug' => 'youtube-views-lite']);
    }

    public function test_seeder_is_idempotent_and_assigns_categories(): void
    {
        $this->seed(\Database\Seeders\PlatformCatalogSeeder::class);
        $this->seed(\Database\Seeders\PlatformCatalogSeeder::class);

        $this->assertSame(16, PlatformProduct::query()->ofType(PlatformProductType::SocialService)->count());

        $youtubeId = ServiceCategory::query()->where('slug', 'youtube')->value('id');
        $this->assertNotNull($youtubeId);
        $this->assertSame(
            4,
            PlatformProduct::query()->where('service_category_id', $youtubeId)->count()
        );

        foreach (['facebook', 'instagram', 'tiktok', 'twitter'] as $slug) {
            $categoryId = ServiceCategory::query()->where('slug', $slug)->value('id');
            $this->assertNotNull($categoryId);
            $this->assertSame(
                3,
                PlatformProduct::query()->where('service_category_id', $categoryId)->count()
            );
        }
    }
}
