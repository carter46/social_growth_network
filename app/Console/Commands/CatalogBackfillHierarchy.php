<?php

namespace App\Console\Commands;

use App\Enums\PlatformProductType;
use App\Models\ProductType;
use App\Models\ServiceCategory;
use App\Support\PlatformCatalogCms;
use App\Support\PlatformCatalogTrim;
use App\Support\SortOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CatalogBackfillHierarchy extends Command
{
    protected $signature = 'catalog:backfill-hierarchy';

    protected $description = 'Idempotently seed service_categories / product_types and reparent platform_products';

    public function handle(): int
    {
        if (! Schema::hasTable('service_categories') || ! Schema::hasTable('product_types')) {
            $this->error('Hierarchy tables missing. Run migrations first.');

            return self::FAILURE;
        }

        $categoriesCreated = $this->seedServiceCategories();
        $servicesCreated = $this->seedProductTypes();
        $productsLinked = $this->linkPlatformProducts();
        $providersSet = $this->setProviderDefaults();
        $trimmed = PlatformCatalogTrim::apply();
        $retired = $trimmed['products'];
        $retiredServices = $trimmed['services'];
        $cms = PlatformCatalogCms::normalizeHeroAndBenefits();
        $normalized = $this->normalizeSortOrders();

        $this->info("Service categories upserted: {$categoriesCreated}");
        $this->info("Services (product_types) upserted: {$servicesCreated}");
        $this->info("Products linked to services: {$productsLinked}");
        $this->info("Provider defaults applied: {$providersSet}");
        foreach ($retired as $type => $count) {
            $this->info("Retired disallowed {$type} products: {$count}");
        }
        foreach ($retiredServices as $type => $count) {
            $this->info("Retired service {$type}: {$count}");
        }
        $this->info("Platform CMS normalized: {$cms['categories']} categories, {$cms['services']} services");
        $this->info("Sort groups normalized to 1..N: {$normalized}");

        return self::SUCCESS;
    }

    private function seedServiceCategories(): int
    {
        $count = 0;
        $sort = 1;

        // Registry owns which categories exist; insert by expected_id so fresh DBs match production ids.
        $registry = collect(config('platform_categories', []))
            ->filter(fn ($meta) => is_array($meta) && ! empty($meta['slug']))
            ->sortBy(fn ($meta) => (int) ($meta['expected_id'] ?? 999));

        foreach ($registry as $key => $meta) {
            $slug = (string) $meta['slug'];
            $group = config('catalog.groups.'.$slug, []);

            $existing = ServiceCategory::query()->where('slug', $slug)->first();
            if ($existing) {
                // Non-destructive: only ensure permanent key is set; never overwrite CMS.
                if (Schema::hasColumn('service_categories', 'key') && $existing->key !== $key) {
                    $existing->forceFill(['key' => $key])->save();
                }
                $count++;
                $sort++;

                continue;
            }

            $mode = ! empty($group['route']) || ($slug === 'trust-escrow')
                ? 'marketplace_link'
                : 'catalog';

            $category = new ServiceCategory;
            $label = $group['label'] ?? str_replace('-', ' ', ucfirst($slug));
            $category->forceFill([
                'slug' => $slug,
                'name' => $label,
                'sort_order' => $sort,
                'is_active' => true,
                'banner_image' => $group['banner_image'] ?? null,
                'card_image' => $group['card_image'] ?? null,
                'short_description' => $group['short_description'] ?? null,
                'hero_title' => $label,
                'hero_subtitle' => $group['short_description'] ?? null,
                'benefits' => [],
                'faq' => $group['faq'] ?? [],
                'mode' => $mode,
                'cta_label' => $group['cta'] ?? ($mode === 'marketplace_link' ? 'Open marketplace' : null),
            ]);
            if (Schema::hasColumn('service_categories', 'key')) {
                $category->key = $key;
            }
            $category->save();
            $count++;
            $sort++;
        }

        return $count;
    }

    private function seedProductTypes(): int
    {
        $count = 0;
        $sortByCategory = [];

        $retiredServices = config('platform_products.retired_services', []);

        foreach (PlatformProductType::cases() as $case) {
            if (in_array($case->value, $retiredServices, true)) {
                continue;
            }

            $slug = $case->value;
            $categorySlug = $this->categorySlugFromConfig($slug);

            if (! $categorySlug) {
                $this->warn("No service category mapping for type [{$slug}], skipped.");

                continue;
            }

            // Only attach under registry categories.
            $registrySlugs = collect(config('platform_categories', []))
                ->map(fn ($meta) => is_array($meta) ? ($meta['slug'] ?? null) : null)
                ->filter()
                ->all();
            if (! in_array($categorySlug, $registrySlugs, true)) {
                $this->warn("Category [{$categorySlug}] for [{$slug}] is not in platform_categories registry, skipped.");

                continue;
            }

            $category = ServiceCategory::query()->where('slug', $categorySlug)->first();
            if (! $category) {
                $this->warn("Service category [{$categorySlug}] missing for [{$slug}], skipped.");

                continue;
            }

            $typeConfig = config('catalog.types.'.$slug, []);
            $sortByCategory[$category->id] = ($sortByCategory[$category->id] ?? 1);

            $existing = ProductType::query()->where('slug', $slug)->first();
            if ($existing) {
                // Non-destructive: keep CMS; only ensure parent link if missing/wrong.
                if ((int) $existing->service_category_id !== (int) $category->id) {
                    $existing->forceFill(['service_category_id' => $category->id])->save();
                }
                $count++;
                $sortByCategory[$category->id]++;

                continue;
            }

            $service = new ProductType;
            $label = $typeConfig['label'] ?? $case->label();
            $service->forceFill([
                'slug' => $slug,
                'service_category_id' => $category->id,
                'name' => $label,
                'sort_order' => $sortByCategory[$category->id]++,
                'is_active' => true,
                'banner_image' => $typeConfig['banner_image'] ?? null,
                'card_image' => $typeConfig['card_image'] ?? null,
                'short_description' => $typeConfig['short_description'] ?? null,
                'hero_title' => $label,
                'hero_subtitle' => $typeConfig['short_description'] ?? null,
                'benefits' => [],
                'faq' => $typeConfig['faq'] ?? [],
            ]);
            $service->save();
            $count++;
        }

        return $count;
    }

    private function categorySlugFromConfig(string $typeSlug): ?string
    {
        foreach (config('catalog.groups', []) as $slug => $group) {
            if (in_array($typeSlug, $group['types'] ?? [], true)) {
                return $slug;
            }
        }

        return null;
    }

    private function linkPlatformProducts(): int
    {
        if (! Schema::hasColumn('platform_products', 'product_type_id')) {
            return 0;
        }

        $slugToId = ProductType::query()->pluck('id', 'slug');
        $updated = 0;

        foreach ($slugToId as $slug => $id) {
            $updated += DB::table('platform_products')
                ->where('product_type', $slug)
                ->where(function ($q) use ($id) {
                    $q->whereNull('product_type_id')->orWhere('product_type_id', '!=', $id);
                })
                ->update(['product_type_id' => $id]);
        }

        return $updated;
    }

    private function setProviderDefaults(): int
    {
        if (! Schema::hasColumn('platform_products', 'provider')) {
            return 0;
        }

        return (int) DB::table('platform_products')
            ->whereNull('provider')
            ->update([
                'provider' => 'manual',
                'fulfillment_mode' => 'manual',
                'auto_renew' => false,
            ]);
    }

    /** Renumber categories, services, and products each to contiguous global 1..N. */
    private function normalizeSortOrders(): int
    {
        $groups = 0;

        SortOrder::normalize(ServiceCategory::query()->system());
        $groups++;

        SortOrder::normalize(
            ProductType::query()->whereHas('serviceCategory', fn ($q) => $q->system())
        );
        $groups++;

        SortOrder::normalize(
            \App\Models\PlatformProduct::query()
                ->whereHas('productType.serviceCategory', fn ($q) => $q->system())
        );
        $groups++;

        return $groups;
    }
}