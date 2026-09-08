<?php

namespace App\Console\Commands;

use App\Models\PlatformProduct;
use App\Models\ServiceCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent Phase-1 flatten: Category → Product ownership via service_category_id.
 * Does NOT null product_type_id (kept for legacy/CMS compatibility).
 */
class CatalogFlattenCategoryProducts extends Command
{
    protected $signature = 'catalog:flatten-category-products';

    protected $description = 'Upsert platform ServiceCategories and backfill platform_products.service_category_id (does not clear product_type_id)';

    public function handle(): int
    {
        if (! Schema::hasTable('service_categories') || ! Schema::hasTable('platform_products')) {
            $this->error('Required tables missing. Run migrations first.');

            return self::FAILURE;
        }

        if (! Schema::hasColumn('platform_products', 'service_category_id')) {
            $this->error('Column service_category_id missing. Run migrations first.');

            return self::FAILURE;
        }

        $categoriesUpserted = 0;
        $productsLinked = 0;
        $sort = 1;

        $registry = collect(config('platform_categories', []))
            ->filter(fn ($meta) => is_array($meta) && ! empty($meta['slug']));

        foreach ($registry->sortBy(fn ($meta) => (int) ($meta['expected_id'] ?? 999)) as $key => $meta) {
            $slug = (string) $meta['slug'];
            $label = (string) ($meta['label'] ?? str_replace('-', ' ', ucfirst($slug)));

            $category = ServiceCategory::query()->where('slug', $slug)->first();
            if (! $category) {
                $category = new ServiceCategory;
                $payload = [
                    'slug' => $slug,
                    'name' => $label,
                    'sort_order' => $sort,
                    'is_active' => true,
                    'mode' => 'catalog',
                    'short_description' => $label.' campaign packages.',
                    'hero_title' => $label,
                    'hero_subtitle' => 'Browse predefined '.$label.' packages.',
                    'benefits' => [],
                    'faq' => [],
                ];
                // Only force expected_id when that PK is free (avoid collisions on shared DBs).
                $expectedId = (int) ($meta['expected_id'] ?? 0);
                if ($expectedId > 0 && ! ServiceCategory::query()->whereKey($expectedId)->exists()) {
                    $payload['id'] = $expectedId;
                }
                $category->forceFill($payload);
                if (Schema::hasColumn('service_categories', 'key')) {
                    $category->key = $key;
                }
                $category->save();
                $categoriesUpserted++;
            } else {
                if (Schema::hasColumn('service_categories', 'key') && $category->key !== $key) {
                    $category->forceFill(['key' => $key])->save();
                }
                $categoriesUpserted++;
            }

            $productSlugs = $meta['products'] ?? [];
            if (! is_array($productSlugs) || $productSlugs === []) {
                $sort++;

                continue;
            }

            foreach ($productSlugs as $productSlug) {
                $product = PlatformProduct::query()->where('slug', $productSlug)->first();
                if (! $product) {
                    $this->warn("Product slug not found (skipped): {$productSlug}");

                    continue;
                }

                if ((int) $product->service_category_id !== (int) $category->id) {
                    $product->forceFill(['service_category_id' => $category->id])->save();
                    $productsLinked++;
                }
            }

            $sort++;
        }

        // Fallback: any remaining nulls inherit from ProductType parent (compat / non-registry products).
        $legacyLinked = 0;
        if (Schema::hasColumn('platform_products', 'product_type_id')) {
            $rows = DB::table('platform_products as pp')
                ->join('product_types as pt', 'pt.id', '=', 'pp.product_type_id')
                ->whereNull('pp.service_category_id')
                ->whereNotNull('pt.service_category_id')
                ->select(['pp.id', 'pt.service_category_id'])
                ->get();

            foreach ($rows as $row) {
                DB::table('platform_products')
                    ->where('id', $row->id)
                    ->update(['service_category_id' => $row->service_category_id]);
                $legacyLinked++;
            }
        }

        $this->info("Service categories upserted/verified: {$categoriesUpserted}");
        $this->info("Products linked via service_category_id (registry): {$productsLinked}");
        if ($legacyLinked > 0) {
            $this->info("Products linked via ProductType fallback: {$legacyLinked}");
        }
        $this->line('product_type_id left unchanged (Phase 1 compatibility).');

        return self::SUCCESS;
    }
}
