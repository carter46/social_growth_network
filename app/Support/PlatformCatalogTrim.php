<?php

namespace App\Support;

use App\Enums\PlatformProductStatus;
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
     * @return array<string, int> type slug => archived count
     */
    public static function retireDisallowedProducts(): array
    {
        if (! Schema::hasTable('platform_products')) {
            return [];
        }

        $config = config('platform_products', []);
        $removed = [];
        $allowedTypes = [];
        // Non-type keys in platform_products.php (not product_type allow-lists).
        $metaKeys = ['retired_services', 'slug_redirects'];
        $legacyAliases = PlatformProductSlugRedirect::legacySlugs();

        foreach ($config as $typeSlug => $keepSlugs) {
            if (in_array($typeSlug, $metaKeys, true) || ! is_array($keepSlugs)) {
                continue;
            }

            $allowedTypes[] = $typeSlug;

            $query = DB::table('platform_products')->where('product_type', $typeSlug);

            if ($keepSlugs !== []) {
                // Keep redirect aliases until PlatformCatalogSeeder renames them in place.
                $allowedSlugs = array_values(array_unique(array_merge(
                    array_values($keepSlugs),
                    $typeSlug === 'social_service' ? $legacyAliases : []
                )));
                $query->whereNotIn('slug', $allowedSlugs);
            }

            $removed[$typeSlug] = self::archive($query->pluck('id')->all());
        }

        // Archive products whose type is not in the allow-list at all
        if ($allowedTypes !== []) {
            $orphanIds = DB::table('platform_products')
                ->whereNotIn('product_type', $allowedTypes)
                ->pluck('id')
                ->all();

            if ($orphanIds !== []) {
                $removed['_orphaned_types'] = self::archive($orphanIds);
            }
        }

        return $removed;
    }

    /**
     * Rows are kept for order and campaign history; archived products never reach the catalog.
     *
     * @param  list<int>  $ids
     */
    private static function archive(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return DB::table('platform_products')
            ->whereIn('id', $ids)
            ->where('status', '!=', PlatformProductStatus::Archived->value)
            ->update(['status' => PlatformProductStatus::Archived->value, 'updated_at' => now()]);
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
