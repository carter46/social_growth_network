<?php

namespace App\Modules\Catalog\Services;

use App\Enums\PlatformProductStatus;
use App\Enums\PlatformProductType;
use App\Models\PlatformProduct;
use App\Models\PlatformProductVariant;
use App\Models\ProductType;
use App\Models\ServiceCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class CatalogBrowseService
{
    public const HOME_ECOSYSTEM_LIMIT = 8;

    public function usesDbHierarchy(): bool
    {
        if (! config('catalog.use_db_hierarchy', true)) {
            return false;
        }

        if (! Schema::hasTable('service_categories')) {
            return false;
        }

        if (Schema::hasColumn('service_categories', 'key')) {
            return ServiceCategory::query()->system()->exists();
        }

        return ServiceCategory::query()->exists();
    }

    /** @return list<string> */
    public function groupSlugs(): array
    {
        if ($this->usesDbHierarchy()) {
            return ServiceCategory::query()
                ->system()
                ->active()
                ->orderBy('sort_order')
                ->pluck('slug')
                ->all();
        }

        return array_keys(config('catalog.groups', []));
    }

    /** @return list<string> */
    public function typeKeys(): array
    {
        if ($this->usesDbHierarchy()) {
            return ProductType::query()
                ->active()
                ->whereHas('serviceCategory', fn ($q) => $q->system()->active())
                ->orderBy('sort_order')
                ->pluck('slug')
                ->all();
        }

        return array_keys(config('catalog.types', []));
    }

    /** @return list<string> */
    public function allGroupTypeValues(): array
    {
        if ($this->usesDbHierarchy()) {
            return ProductType::query()
                ->whereHas('serviceCategory', fn ($q) => $q->system()->where('mode', 'catalog')->active())
                ->active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->pluck('slug')
                ->unique()
                ->values()
                ->all();
        }

        return collect(config('catalog.groups', []))
            ->flatMap(fn (array $group) => $group['types'] ?? [])
            ->unique()
            ->values()
            ->all();
    }

    public function isGroup(string $slug): bool
    {
        if ($this->usesDbHierarchy()) {
            return ServiceCategory::query()
                ->system()
                ->where('slug', $slug)
                ->where('is_active', true)
                ->exists();
        }

        return isset(config('catalog.groups')[$slug]);
    }

    public function isType(string $key): bool
    {
        if ($this->usesDbHierarchy()) {
            return ProductType::query()
                ->active()
                ->where('slug', $key)
                ->whereHas('serviceCategory', fn ($q) => $q->system()->active())
                ->exists();
        }

        return isset(config('catalog.types')[$key]);
    }

    public function groupForType(string $type): ?string
    {
        if ($this->usesDbHierarchy()) {
            $service = ProductType::query()->with('serviceCategory')->where('slug', $type)->first();

            return $service?->serviceCategory?->slug;
        }

        foreach (config('catalog.groups', []) as $slug => $group) {
            if (in_array($type, $group['types'] ?? [], true)) {
                return $slug;
            }
        }

        return null;
    }

    public function typeBelongsToGroup(string $type, string $group): bool
    {
        return $this->groupForType($type) === $group;
    }

    /** Canonical public URL for a service (product type) listing. */
    public function serviceListingUrl(string $serviceSlug, ?string $categorySlug = null): string
    {
        $categorySlug ??= $this->groupForType($serviceSlug);
        if ($categorySlug) {
            return route('services.type', [
                'category' => $categorySlug,
                'service' => $serviceSlug,
            ]);
        }

        return route('services.segment', $serviceSlug);
    }

    /** Canonical public URL for a product detail page. */
    public function productUrl(PlatformProduct $product): string
    {
        $typeSlug = $product->typeSlug() ?? 'social_service';
        $categorySlug = $product->relationLoaded('productType')
            ? $product->productType?->serviceCategory?->slug
            : null;
        $categorySlug ??= $this->groupForType($typeSlug);

        if ($categorySlug) {
            return route('services.nested.show', [
                'category' => $categorySlug,
                'service' => $typeSlug,
                'productSlug' => $product->slug,
            ]);
        }

        return route('services.show', [
            'type' => $typeSlug,
            'productSlug' => $product->slug,
        ]);
    }

    public function findServiceCategory(string $slug): ?ServiceCategory
    {
        return ServiceCategory::query()
            ->system()
            ->active()
            ->where('slug', $slug)
            ->first();
    }

    public function findService(string $slug): ?ProductType
    {
        return ProductType::query()
            ->with('serviceCategory')
            ->active()
            ->where('slug', $slug)
            ->whereHas('serviceCategory', fn ($q) => $q->system()->active())
            ->first();
    }

    /**
     * @param  list<string>  $types
     * @return array{count: int, from_price: ?float}
     */
    public function statsForTypes(array $types): array
    {
        if ($types === []) {
            return ['count' => 0, 'from_price' => null];
        }

        $count = PlatformProduct::query()
            ->visibleToPublic()
            ->where(function ($q) use ($types) {
                $q->whereIn('product_type', $types);
                if (Schema::hasColumn('platform_products', 'product_type_id')) {
                    $q->orWhereHas('productType', fn ($inner) => $inner->whereIn('slug', $types));
                }
            })
            ->count();

        $productMin = PlatformProduct::query()
            ->visibleToPublic()
            ->where(function ($q) use ($types) {
                $q->whereIn('product_type', $types);
                if (Schema::hasColumn('platform_products', 'product_type_id')) {
                    $q->orWhereHas('productType', fn ($inner) => $inner->whereIn('slug', $types));
                }
            })
            ->where('base_price', '>', 0)
            ->min('base_price');

        $variantMin = PlatformProductVariant::query()
            ->where('is_active', true)
            ->whereHas('product', function ($q) use ($types) {
                $q->where('status', PlatformProductStatus::Published)
                    ->whereHas('productType', function ($service) {
                        $service->where('is_active', true)
                            ->whereHas('serviceCategory', fn ($cat) => $cat->where('is_active', true));
                    })
                    ->where(function ($inner) use ($types) {
                        $inner->whereIn('product_type', $types);
                        if (Schema::hasColumn('platform_products', 'product_type_id')) {
                            $inner->orWhereHas('productType', fn ($pt) => $pt->whereIn('slug', $types));
                        }
                    });
            })
            ->min('price');

        $candidates = array_filter([
            $productMin !== null ? (float) $productMin : null,
            $variantMin !== null ? (float) $variantMin : null,
        ], fn ($v) => $v !== null && $v > 0);

        return [
            'count' => $count,
            'from_price' => $candidates === [] ? null : min($candidates),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function groupCards(CatalogContentResolver $content): Collection
    {
        if ($this->usesDbHierarchy()) {
            return ServiceCategory::query()
                ->system()
                ->active()
                ->orderBy('sort_order')
                ->with([
                    'cardMedia.variants',
                    'bannerMedia.variants',
                    'services' => fn ($q) => $q->active()->orderBy('sort_order'),
                ])
                ->get()
                ->map(function (ServiceCategory $category) use ($content) {
                    $resolved = $content->forServiceCategory($category);
                    $typeSlugs = $category->services->pluck('slug')->all();
                    $stats = $this->statsForTypes($typeSlugs);

                    return array_merge($resolved, [
                        'slug' => $category->slug,
                        'count' => $stats['count'],
                        'from_price' => $stats['from_price'],
                        'href' => route('services.segment', $category->slug),
                        'cta' => $category->cta_label ?: 'Explore',
                        'mode' => $category->mode,
                    ]);
                })
                ->values();
        }

        return collect(config('catalog.groups', []))->map(function (array $group, string $slug) use ($content) {
            $resolved = $content->forGroup($slug);
            $stats = $this->statsForTypes($group['types'] ?? []);
            $routeName = $group['route'] ?? null;

            return array_merge($resolved, [
                'slug' => $slug,
                'count' => $stats['count'],
                'from_price' => $stats['from_price'],
                'href' => $routeName ? route($routeName) : route('services.segment', $slug),
                'cta' => $group['cta'] ?? 'Explore',
            ]);
        })->values();
    }

    /**
     * @param  list<string>  $types
     * @return Collection<int, array<string, mixed>>
     */
    public function typeCards(array $types, CatalogContentResolver $content): Collection
    {
        if ($this->usesDbHierarchy()) {
            return ProductType::query()
                ->active()
                ->whereIn('slug', $types)
                ->whereHas('serviceCategory', fn ($q) => $q->system()->active())
                ->orderBy('sort_order')
                ->get()
                ->map(function (ProductType $service) use ($content) {
                    $resolved = $content->forService($service);
                    $stats = $this->statsForTypes([$service->slug]);

                    return array_merge($resolved, [
                        'slug' => $service->slug,
                        'count' => $stats['count'],
                        'from_price' => $stats['from_price'],
                        'href' => route('services.segment', $service->slug),
                    ]);
                })
                ->values();
        }

        return collect($types)->map(function (string $type) use ($content) {
            $resolved = $content->forType($type);
            $stats = $this->statsForTypes([$type]);

            return array_merge($resolved, [
                'slug' => $type,
                'count' => $stats['count'],
                'from_price' => $stats['from_price'],
                'href' => $this->serviceListingUrl($type),
                'cta' => 'View products',
                'meta' => $this->cardMeta($stats['count'], $stats['from_price']),
            ]);
        })->values();
    }

    /**
     * Service (product_type) cards for a service category.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function serviceCardsForCategory(ServiceCategory $category, CatalogContentResolver $content): Collection
    {
        $services = $category->relationLoaded('services')
            ? $category->services->where('is_active', true)->sortBy('sort_order')->values()
            : $category->services()->active()->with([
                'cardMedia.variants',
                'bannerMedia.variants',
                'serviceCategory.cardMedia.variants',
                'serviceCategory.bannerMedia.variants',
            ])->orderBy('sort_order')->get();

        return $services->map(function (ProductType $service) use ($content, $category) {
            $resolved = $content->forService($service);
            $stats = $this->statsForTypes([$service->slug]);

            return array_merge($resolved, [
                'slug' => $service->slug,
                'count' => $stats['count'],
                'from_price' => $stats['from_price'],
                'href' => $this->serviceListingUrl($service->slug, $category->slug),
                'cta' => 'View products',
                'meta' => $this->cardMeta($stats['count'], $stats['from_price']),
            ]);
        })->values();
    }

    private function cardMeta(int $count, mixed $fromPrice): string
    {
        $meta = $count.' '.\Illuminate\Support\Str::plural('product', $count);
        if ($fromPrice) {
            $meta .= ' · from ₦'.number_format((float) $fromPrice, 0);
        }

        return $meta;
    }

    /**
     * Home page "What we do" cards: active catalog services.
     *
     * @return list<array{icon: string, title: string, body: string, href: string}>
     */
    public function homeEcosystemItems(CatalogContentResolver $content): array
    {
        $items = [];

        foreach ($this->homeCatalogServiceCards($content) as $card) {
            $items[] = [
                'icon' => $card['icon'],
                'title' => $card['title'],
                'body' => $card['body'],
                'href' => $card['href'],
            ];
        }

        return array_slice($items, 0, self::HOME_ECOSYSTEM_LIMIT);
    }

    /**
     * Active catalog services with ≥1 public product, in admin sort order.
     *
     * @return Collection<int, ProductType>
     */
    public function orderedCatalogServicesWithPublicProducts(): Collection
    {
        if (! Schema::hasTable('product_types')) {
            return collect();
        }

        $query = ProductType::query()
            ->with('serviceCategory')
            ->active()
            ->whereHas('products', fn ($q) => $q->visibleToPublic());

        if ($this->usesDbHierarchy()) {
            $query->whereHas('serviceCategory', fn ($q) => $q->system()->active()->where('mode', 'catalog'));
        } else {
            $query->whereIn('slug', $this->allGroupTypeValues());
        }

        return $query
            ->reorder()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Active internal-catalog services that have at least one public product.
     *
     * @return Collection<int, array{icon: string, title: string, body: string, href: string}>
     */
    public function homeCatalogServiceCards(CatalogContentResolver $content): Collection
    {
        $services = $this->orderedCatalogServicesWithPublicProducts();

        if ($services->isNotEmpty()) {
            return $services
                ->map(fn (ProductType $service) => $this->mapHomeServiceCard($service, $content))
                ->values();
        }

        return collect($this->allGroupTypeValues())
            ->filter(fn (string $slug) => $this->statsForTypes([$slug])['count'] > 0)
            ->map(function (string $slug) use ($content) {
                $resolved = $content->forType($slug);
                $enum = PlatformProductType::tryFrom($slug);

                return [
                    'icon' => $resolved['icon'] ?? $enum?->icon() ?? 'grid',
                    'title' => $resolved['label'] ?? str_replace('_', ' ', ucfirst($slug)),
                    'body' => $resolved['short_description'] ?? '',
                    'href' => $this->serviceListingUrl($slug),
                ];
            })
            ->values();
    }

    /**
     * @return array{icon: string, title: string, body: string, href: string}
     */
    private function mapHomeServiceCard(ProductType $service, CatalogContentResolver $content): array
    {
        $resolved = $content->forService($service);
        $enum = PlatformProductType::tryFrom($service->slug);

        return [
            'icon' => $resolved['icon'] ?? $enum?->icon() ?? 'grid',
            'title' => $resolved['label'] ?? $service->name,
            'body' => $resolved['short_description'] ?? $service->short_description ?? '',
            'href' => $this->serviceListingUrl($service->slug, $service->serviceCategory?->slug),
        ];
    }
}
