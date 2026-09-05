<?php

namespace App\Support;

use App\Models\PlatformProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PlatformCatalogTrim
{
    /**
     * Apply product allow-list and retire whole services from config/platform_products.php.
     *
     * @return array{products: array<string, int>, services: array<string, int>}
     */
    public static function apply(): array
    {
        return [
            'products' => self::retireDisallowedProducts(),
            'services' => self::retireServices(),
        ];
    }

    /**
     * @return array<string, int> type slug => deleted count
     */
    public static function retireDisallowedProducts(): array
    {
        if (! Schema::hasTable('platform_products')) {
            return [];
        }

        $config = config('platform_products', []);
        $removed = [];
        $allowedTypes = [];

        foreach ($config as $typeSlug => $keepSlugs) {
            if ($typeSlug === 'retired_services' || ! is_array($keepSlugs)) {
                continue;
            }

            $allowedTypes[] = $typeSlug;

            $query = DB::table('platform_products')->where('product_type', $typeSlug);

            if ($keepSlugs !== []) {
                $query->whereNotIn('slug', $keepSlugs);
            }

            $productIds = $query->pluck('id');

            if ($productIds->isEmpty()) {
                $removed[$typeSlug] = 0;

                continue;
            }

            if (Schema::hasTable('favorites')) {
                DB::table('favorites')
                    ->where('favoritable_type', PlatformProduct::class)
                    ->whereIn('favoritable_id', $productIds)
                    ->delete();
            }

            $removed[$typeSlug] = DB::table('platform_products')
                ->whereIn('id', $productIds)
                ->delete();
        }

        // Delete products whose type is not in the allow-list at all
        if ($allowedTypes !== []) {
            $orphanIds = DB::table('platform_products')
                ->whereNotIn('product_type', $allowedTypes)
                ->pluck('id');

            if ($orphanIds->isNotEmpty()) {
                if (Schema::hasTable('favorites')) {
                    DB::table('favorites')
                        ->where('favoritable_type', PlatformProduct::class)
                        ->whereIn('favoritable_id', $orphanIds)
                        ->delete();
                }

                $removed['_orphaned_types'] = DB::table('platform_products')
                    ->whereIn('id', $orphanIds)
                    ->delete();
            }
        }

        return $removed;
    }

    /**
     * @return array<string, int> service slug => deleted count (0 or 1)
     */
    public static function retireServices(): array
    {
        $retired = config('platform_products.retired_services', []);
        $removed = [];

        foreach ($retired as $slug) {
            if (! is_string($slug) || $slug === '') {
                continue;
            }

            if (Schema::hasTable('platform_categories')) {
                DB::table('platform_categories')->where('product_type', $slug)->delete();
            }

            if (Schema::hasTable('catalog_page_contents')) {
                DB::table('catalog_page_contents')
                    ->where('scope', 'type')
                    ->where('key', $slug)
                    ->delete();
            }

            $deleted = 0;
            if (Schema::hasTable('product_types')) {
                $deleted = DB::table('product_types')->where('slug', $slug)->delete();
            }

            $removed[$slug] = $deleted;
        }

        return $removed;
    }
}
