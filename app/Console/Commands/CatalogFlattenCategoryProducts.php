<?php

namespace App\Console\Commands;

use App\Models\PlatformProduct;
use App\Models\ServiceCategory;
use Illuminate\Console\Command;
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
                if (! empty($meta['expected_id'])) {
                    $payload['id'] = (int) $meta['expected_id'];
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

        $this->info("Service categories upserted/verified: {$categoriesUpserted}");
        $this->info("Products linked via service_category_id: {$productsLinked}");
        $this->line('product_type_id left unchanged (Phase 1 compatibility).');

        return self::SUCCESS;
    }
}
