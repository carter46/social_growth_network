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

    public function test_archives_disallowed_products_without_deleting_them(): void
    {
        $this->seed(\Database\Seeders\PlatformCatalogSeeder::class);

        foreach ([
            ['slug' => 'linkedin-lead-boost', 'title' => 'LinkedIn Lead Boost'],
            ['slug' => 'instagram-views', 'title' => 'Instagram Views'],
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

        foreach (['youtube-views', 'youtube-likes', 'youtube-comments', 'youtube-watch-hours'] as $slug) {
            $this->assertDatabaseHas('platform_products', ['slug' => $slug, 'status' => 'published']);
        }

        $this->assertDatabaseHas('platform_products', ['slug' => 'linkedin-lead-boost', 'status' => 'archived']);
        $this->assertDatabaseHas('platform_products', ['slug' => 'instagram-views', 'status' => 'archived']);
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

    public function test_seeder_is_idempotent_and_assigns_youtube_category(): void
    {
        $this->seed(\Database\Seeders\PlatformCatalogSeeder::class);
        $this->seed(\Database\Seeders\PlatformCatalogSeeder::class);

        $youtubeId = ServiceCategory::query()->where('slug', 'youtube')->value('id');
        $this->assertNotNull($youtubeId);

        foreach (['youtube-views', 'youtube-likes', 'youtube-comments', 'youtube-watch-hours', 'youtube-subscribers'] as $slug) {
            $this->assertSame(1, PlatformProduct::query()->where('slug', $slug)->count(), $slug);
            $this->assertSame(
                (int) $youtubeId,
                (int) PlatformProduct::query()->where('slug', $slug)->value('service_category_id'),
                $slug
            );
        }

        $this->assertDatabaseHas('platform_products', ['slug' => 'youtube-subscribers', 'status' => 'draft']);
    }
}
