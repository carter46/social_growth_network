<?php

namespace Database\Seeders;

use App\Enums\PlatformProductStatus;
use App\Enums\PlatformProductType;
use App\Models\PlatformCategory;
use App\Models\PlatformProduct;
use App\Models\PlatformProductImage;
use App\Models\PlatformProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PlatformCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $titles = [
            'Instagram Growth Pack',
            'TikTok Engagement Boost',
            'YouTube Views Lite',
            'Twitter Audience Pack',
            'Facebook Growth Pack',
        ];

        $categorySlugs = [
            'social-growth',
            'social-engagement',
            'social-growth',
            'social-engagement',
            'social-growth',
        ];

        $type = PlatformProductType::SocialService->value;

        foreach ($titles as $i => $title) {
            $slug = Str::slug($title);
            $categoryId = null;
            if (Schema::hasTable('platform_categories') && isset($categorySlugs[$i])) {
                $categoryId = PlatformCategory::where('slug', $categorySlugs[$i])->value('id');
            }

            $attrs = [
                'product_type' => $type,
                'title' => $title,
                'short_description' => "Ready-to-use {$title} for social growth.",
                'description' => "Get started quickly with {$title}. Includes setup guidance, support, and clear deliverables. Admin can edit or remove this seeded product anytime.",
                'status' => PlatformProductStatus::Published,
                'is_featured' => $i < 2,
                'sort_order' => $i,
                'base_price' => 5000 + ($i * 2500),
                'currency' => 'NGN',
            ];

            if (Schema::hasColumn('platform_products', 'platform_category_id')) {
                $attrs['platform_category_id'] = $categoryId;
            }

            $product = PlatformProduct::query()->updateOrCreate(
                ['slug' => $slug],
                $attrs
            );

            if (Schema::hasTable('platform_product_variants')) {
                PlatformProductVariant::query()->updateOrCreate(
                    [
                        'platform_product_id' => $product->id,
                        'name' => 'Standard',
                    ],
                    [
                        'price' => $product->base_price,
                        'duration_months' => 1,
                        'is_default' => true,
                        'sort_order' => 0,
                    ]
                );
            }

            if (Schema::hasTable('platform_product_images') && ! $product->images()->exists()) {
                PlatformProductImage::query()->create([
                    'platform_product_id' => $product->id,
                    'path' => 'assets/images/Social_Media.jpg',
                    'sort_order' => 0,
                    'is_primary' => true,
                ]);
            }
        }

        // Ownership: Category → Product (keeps product_type_id populated separately).
        if (Schema::hasColumn('platform_products', 'service_category_id')) {
            Artisan::call('catalog:flatten-category-products');
        }
    }
}
