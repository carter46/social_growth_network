<?php

use App\Models\PlatformProductVariant;
use Database\Seeders\PlatformCatalogSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restores YouTube Subscribers as a draft product so an admin can set price and reward before publishing.
 */
return new class extends Migration
{
    private const SLUG = 'youtube-subscribers';

    private const BASE_PRICE = 10000;

    public function up(): void
    {
        if (! Schema::hasTable('platform_products')) {
            return;
        }

        $now = now();
        $productId = DB::table('platform_products')->where('slug', self::SLUG)->value('id');

        if (! $productId) {
            $row = [
                'slug' => self::SLUG,
                'title' => 'YouTube Subscribers',
                'product_type' => 'social_service',
                'description' => PlatformCatalogSeeder::descriptionFor(self::SLUG, 'YouTube Subscribers'),
                'status' => 'draft',
                'sort_order' => (int) DB::table('platform_products')->max('sort_order') + 1,
                'base_price' => self::BASE_PRICE,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            foreach ([
                'is_featured' => false,
                'currency' => 'NGN',
                'provider' => 'manual',
                'fulfillment_mode' => 'manual',
                'auto_renew' => false,
            ] as $column => $value) {
                if (Schema::hasColumn('platform_products', $column)) {
                    $row[$column] = $value;
                }
            }

            $productId = DB::table('platform_products')->insertGetId($row);
        }

        $this->attachToYoutube((int) $productId);
        $this->ensureStandardVariant((int) $productId, $now);
    }

    public function down(): void
    {
        // Data-only: the product row is kept so orders and campaigns never lose their product.
    }

    private function attachToYoutube(int $productId): void
    {
        $product = DB::table('platform_products')->where('id', $productId)->first();
        $sibling = DB::table('platform_products')->where('slug', 'youtube-views')->first();
        $updates = [];

        if (Schema::hasColumn('platform_products', 'service_category_id') && empty($product->service_category_id)) {
            $categoryId = Schema::hasTable('service_categories')
                ? DB::table('service_categories')->where('slug', 'youtube')->value('id')
                : null;
            $categoryId ??= $sibling->service_category_id ?? null;
            if ($categoryId) {
                $updates['service_category_id'] = $categoryId;
            }
        }

        if (Schema::hasColumn('platform_products', 'product_type_id')
            && empty($product->product_type_id)
            && ! empty($sibling?->product_type_id)) {
            $updates['product_type_id'] = $sibling->product_type_id;
        }

        if ($updates !== []) {
            DB::table('platform_products')->where('id', $productId)->update($updates);
        }
    }

    private function ensureStandardVariant(int $productId, $now): void
    {
        if (! Schema::hasTable('platform_product_variants')) {
            return;
        }

        $exists = DB::table('platform_product_variants')
            ->where('platform_product_id', $productId)
            ->exists();
        if ($exists) {
            return;
        }

        $units = PlatformProductVariant::REFERENCE_UNITS;
        $row = [
            'platform_product_id' => $productId,
            'name' => 'Standard',
            'price' => self::BASE_PRICE,
            'duration_months' => 1,
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('platform_product_variants', 'pricing_mode')) {
            $row['pricing_mode'] = PlatformProductVariant::PRICING_PER_UNIT;
            $row['unit_price'] = PlatformProductVariant::computeUnitPriceFromPackagePrice(self::BASE_PRICE, $units);
            $row['min_units'] = $units;
            $row['max_units'] = PlatformProductVariant::DEFAULT_MAX_UNITS;
        }
        if (Schema::hasColumn('platform_product_variants', 'pricing_units')) {
            $row['pricing_units'] = $units;
        }

        DB::table('platform_product_variants')->insert($row);
    }
};