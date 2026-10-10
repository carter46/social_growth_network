<?php

namespace Database\Seeders;

use App\Enums\PlatformProductStatus;
use App\Enums\PlatformProductType;
use App\Models\PlatformProduct;
use App\Models\PlatformProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class PlatformCatalogSeeder extends Seeder
{
    /**
     * Existing Views products: rename in place (preserve IDs).
     * Lookup: new slug first → else old slug → else create.
     *
     * @var array<int, array{new_slug: string, old_slug: string, title: string, base_price: int, sort_order: int}>
     */
    private const VIEWS_RENAMES = [
        [
            'new_slug' => 'youtube-views',
            'old_slug' => 'youtube-views-lite',
            'title' => 'YouTube Views',
            'base_price' => 5000,
            'sort_order' => 0,
        ],
    ];

    /**
     * New products created when missing (Likes / Comments / YouTube extras).
     * Subscribers starts as a draft until an admin sets pricing and publishes it.
     *
     * @var array<int, array{slug: string, title: string, base_price: int, sort_order: int, status?: PlatformProductStatus}>
     */
    private const ADDITIONAL_PRODUCTS = [
        ['slug' => 'youtube-likes', 'title' => 'YouTube Likes', 'base_price' => 5500, 'sort_order' => 1],
        ['slug' => 'youtube-comments', 'title' => 'YouTube Comments', 'base_price' => 6000, 'sort_order' => 2],
        ['slug' => 'youtube-watch-hours', 'title' => 'YouTube Watch Hours', 'base_price' => 8000, 'sort_order' => 3],
        ['slug' => 'youtube-subscribers', 'title' => 'YouTube Subscribers', 'base_price' => 10000, 'sort_order' => 4, 'status' => PlatformProductStatus::Draft],
    ];

    public static function descriptionFor(string $slug, string $title): string
    {
        $metric = str_starts_with($slug, 'youtube-') ? substr($slug, strlen('youtube-')) : '';

        return match ($metric) {
            'watch-hours' => 'Add watch time to a public YouTube video. Choose a package, add your video link at checkout, and track your campaign from your account.',
            'subscribers' => 'Grow the subscriber count of a public YouTube channel. Choose a package, add your channel link at checkout, and track your campaign from your account.',
            'views' => 'Order views for a public YouTube video. Choose a package, add your video link at checkout, and track your campaign from your account.',
            'likes', 'comments' => "Order {$metric} for a public YouTube video. Choose a package, add the link at checkout, and track progress from your account.",
            default => "Choose a {$title} package, add your link at checkout, and track progress from your account.",
        };
    }

    public function run(): void
    {
        $type = PlatformProductType::SocialService->value;

        foreach (self::VIEWS_RENAMES as $row) {
            $product = $this->findOrCreateViewsProduct($row, $type);
            $this->ensureStandardVariant($product, (float) $row['base_price']);
        }

        foreach (self::ADDITIONAL_PRODUCTS as $row) {
            $product = $this->upsertAdditionalProduct($row, $type);
            $this->ensureStandardVariant($product, (float) $row['base_price']);
        }

        $this->clearMarketingJson();

        if (Schema::hasColumn('platform_products', 'service_category_id')) {
            Artisan::call('catalog:flatten-category-products');
        }

        if (Schema::hasTable('platform_products')) {
            \App\Support\SortOrder::normalize(
                PlatformProduct::query()->where('product_type', PlatformProductType::SocialService->value)
            );
        }
    }

    /**
     * @param  array{new_slug: string, old_slug: string, title: string, base_price: int, sort_order: int}  $row
     */
    private function findOrCreateViewsProduct(array $row, string $type): PlatformProduct
    {
        $product = PlatformProduct::query()->where('slug', $row['new_slug'])->first()
            ?? PlatformProduct::query()->where('slug', $row['old_slug'])->first();

        if ($product) {
            $isRename = $product->slug === $row['old_slug'];
            $updates = [
                'slug' => $row['new_slug'],
                'product_type' => $type,
            ];

            // Only force catalog title/status when renaming from the retired slug.
            if ($isRename) {
                $updates['title'] = $row['title'];
                $updates['status'] = PlatformProductStatus::Published;
                $updates['sort_order'] = $row['sort_order'];
            }

            if (! filled($product->description)) {
                $updates['description'] = self::descriptionFor($row['new_slug'], $row['title']);
            }
            if ($product->base_price === null || (float) $product->base_price <= 0) {
                $updates['base_price'] = $row['base_price'];
            }
            if (Schema::hasColumn('platform_products', 'currency') && ! filled($product->currency)) {
                $updates['currency'] = 'NGN';
            }

            $product->forceFill($updates)->save();

            return $product->refresh();
        }

        return $this->forceCreateProduct([
            'slug' => $row['new_slug'],
            'title' => $row['title'],
            'product_type' => $type,
            'description' => self::descriptionFor($row['new_slug'], $row['title']),
            'status' => PlatformProductStatus::Published,
            'is_featured' => $row['sort_order'] < 20,
            'sort_order' => $row['sort_order'],
            'base_price' => $row['base_price'],
            'currency' => 'NGN',
            'provider' => 'manual',
            'fulfillment_mode' => 'manual',
            'auto_renew' => false,
        ]);
    }

    /**
     * @param  array{slug: string, title: string, base_price: int, sort_order: int, status?: PlatformProductStatus}  $row
     */
    private function upsertAdditionalProduct(array $row, string $type): PlatformProduct
    {
        $existing = PlatformProduct::query()->where('slug', $row['slug'])->first();

        if ($existing) {
            $updates = [
                'product_type' => $type,
            ];

            if (! filled($existing->description)) {
                $updates['description'] = self::descriptionFor($row['slug'], $row['title']);
            }
            if ($existing->base_price === null || (float) $existing->base_price <= 0) {
                $updates['base_price'] = $row['base_price'];
            }
            if (Schema::hasColumn('platform_products', 'currency') && ! filled($existing->currency)) {
                $updates['currency'] = 'NGN';
            }

            $existing->forceFill($updates)->save();

            return $existing->refresh();
        }

        return $this->forceCreateProduct([
            'slug' => $row['slug'],
            'title' => $row['title'],
            'product_type' => $type,
            'description' => self::descriptionFor($row['slug'], $row['title']),
            'status' => $row['status'] ?? PlatformProductStatus::Published,
            'is_featured' => false,
            'sort_order' => $row['sort_order'],
            'base_price' => $row['base_price'],
            'currency' => 'NGN',
            'provider' => 'manual',
            'fulfillment_mode' => 'manual',
            'auto_renew' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function forceCreateProduct(array $attrs): PlatformProduct
    {
        if (! Schema::hasColumn('platform_products', 'currency')) {
            unset($attrs['currency']);
        }

        $product = new PlatformProduct;
        $product->forceFill($attrs)->save();

        return $product->refresh();
    }

    private function ensureStandardVariant(PlatformProduct $product, float $baselinePrice): void
    {
        if (! Schema::hasTable('platform_product_variants')) {
            return;
        }

        $existing = PlatformProductVariant::query()
            ->where('platform_product_id', $product->id)
            ->where('name', 'Standard')
            ->first();

        if ($existing) {
            return;
        }

        $units = PlatformProductVariant::REFERENCE_UNITS;
        $payload = [
            'platform_product_id' => $product->id,
            'name' => 'Standard',
            'price' => $baselinePrice,
            'duration_months' => 1,
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 0,
        ];

        if (Schema::hasColumn('platform_product_variants', 'pricing_mode')) {
            $payload['pricing_mode'] = PlatformProductVariant::PRICING_PER_UNIT;
            $payload['unit_price'] = PlatformProductVariant::computeUnitPriceFromPackagePrice($baselinePrice, $units);
            $payload['min_units'] = $units;
            $payload['max_units'] = PlatformProductVariant::DEFAULT_MAX_UNITS;
        }
        if (Schema::hasColumn('platform_product_variants', 'pricing_units')) {
            $payload['pricing_units'] = $units;
        }

        PlatformProductVariant::query()->create($payload);
    }

    private function clearMarketingJson(): void
    {
        $allowed = config('platform_products.social_service', []);
        if ($allowed === []) {
            return;
        }

        $columns = ['features', 'requirements', 'whats_included', 'faqs', 'support_text'];
        $payload = [];
        foreach ($columns as $column) {
            if (Schema::hasColumn('platform_products', $column)) {
                $payload[$column] = null;
            }
        }

        if ($payload === []) {
            return;
        }

        PlatformProduct::query()
            ->whereIn('slug', $allowed)
            ->update($payload);
    }
}
