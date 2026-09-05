<?php

namespace Tests\Feature\Catalog;

use App\Enums\PlatformProductType;
use App\Models\PlatformProduct;
use App\Support\PlatformCatalogTrim;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->assertDatabaseHas('platform_products', ['slug' => 'instagram-growth-pack']);
        $this->assertDatabaseHas('platform_products', ['slug' => 'tiktok-engagement-boost']);
        $this->assertDatabaseHas('platform_products', ['slug' => 'youtube-views-lite']);
        $this->assertDatabaseHas('platform_products', ['slug' => 'twitter-audience-pack']);
        $this->assertDatabaseHas('platform_products', ['slug' => 'facebook-growth-pack']);

        $this->assertDatabaseMissing('platform_products', ['slug' => 'linkedin-lead-boost']);
        $this->assertDatabaseMissing('platform_products', ['slug' => 'multi-platform-starter']);

        $this->assertSame(5, PlatformProduct::query()->ofType(PlatformProductType::SocialService)->count());
    }
}
