<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PlatformCategory;
use App\Models\PlatformProduct;
use App\Modules\Catalog\Services\CatalogBrowseService;
use App\Modules\Catalog\Services\CatalogContentResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ServiceController extends Controller
{
    /** @var array<string, string> legacy division slug → group slug (target must differ) */
    private const DIVISION_TO_GROUP = [
        'digital-services' => 'network-services',
        'web-solutions' => 'website-services',
        'trust-protection' => 'trust-escrow',
    ];

    public function __construct(
        private CatalogBrowseService $browse,
        private CatalogContentResolver $content,
        private \App\Services\Analytics\UserActivityRecorder $activity,
    ) {}

    public function index(Request $request): View
    {
        $q = $request->string('q')->toString();
        $searchResults = null;

        if ($q !== '') {
            $types = $this->browse->allGroupTypeValues();
            $searchResults = PlatformProduct::query()
                ->visibleToPublic()
                ->ofTypeMany($types)
                ->with(['productType.serviceCategory', 'activeVariants'])
                ->where(function ($inner) use ($q) {
                    $inner->where('title', 'like', "%{$q}%")
                        ->orWhere('short_description', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%");
                })
                ->orderByDesc('is_featured')
                ->orderBy('sort_order')
                ->orderBy('title')
                ->paginate(12)
                ->withQueryString();
        }

        $groups = $this->browse->groupCards($this->content);

        return view('pages.services', [
            'groups' => $groups,
            'searchResults' => $searchResults,
            'q' => $q,
        ]);
    }

    public function group(Request $request, string $group): View
    {
        abort_unless($this->browse->isGroup($group), 404);

        $resolved = $this->content->forGroup($group);
        $typeKeys = $resolved['types'] ?? config('catalog.groups.'.$group.'.types', []);

        if ($this->browse->usesDbHierarchy()) {
            $category = $this->browse->findServiceCategory($group);
            abort_unless($category, 404);
            $typeKeys = $category->services()->active()->orderBy('sort_order')->pluck('slug')->all();
        }

        $typeFilter = $request->string('type')->toString();
        if ($typeFilter !== '' && ! in_array($typeFilter, $typeKeys, true)) {
            $typeFilter = '';
        }

        $activeTypes = $typeFilter !== '' ? [$typeFilter] : $typeKeys;
        $categoryId = $request->integer('category') ?: null;
        $q = $request->string('q')->toString();

        $categories = collect();
        if (Schema::hasTable('platform_categories')) {
            $categories = PlatformCategory::query()
                ->where('is_active', true)
                ->whereIn('product_type', $activeTypes)
                ->orderBy('sort_order')
                ->get();

            if ($categoryId && ! $categories->contains('id', $categoryId)) {
                $categoryId = null;
            }
        } else {
            $categoryId = null;
        }

        // Always list products on the category page (skip intermediate service cards).
        $products = PlatformProduct::query()
            ->visibleToPublic()
            ->ofTypeMany($activeTypes)
            ->with(['productType.serviceCategory', 'activeVariants'])
            ->when($categoryId, fn ($builder) => $builder->where('platform_category_id', $categoryId))
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($inner) use ($q) {
                    $inner->where('title', 'like', "%{$q}%")
                        ->orWhere('short_description', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate(12)
            ->withQueryString();

        return view('pages.services-group', [
            'groupSlug' => $group,
            'content' => $resolved,
            'typeKeys' => $typeKeys,
            'typeCards' => collect(),
            'categories' => $categories,
            'products' => $products,
            'filters' => [
                'q' => $q,
                'category' => $categoryId,
                'type' => $typeFilter !== '' ? $typeFilter : null,
            ],
        ]);
    }

    public function type(Request $request, string $type, ?string $preferGroupSlug = null): View
    {
        abort_unless($this->browse->isType($type), 404);

        $resolved = $this->content->forType($type);
        $groupSlug = $preferGroupSlug ?? $this->browse->groupForType($type);
        $groupContent = $groupSlug ? $this->content->forGroup($groupSlug) : null;

        if ($preferGroupSlug && $groupContent) {
            // Only inherit group hero when this is a single-service category (no service card layer).
            $groupTypeCount = count($groupContent['types'] ?? []);
            if ($this->browse->usesDbHierarchy()) {
                $cat = $this->browse->findServiceCategory($preferGroupSlug);
                $groupTypeCount = $cat
                    ? $cat->services()->active()->count()
                    : $groupTypeCount;
            }
            if ($groupTypeCount === 1) {
                $resolved = array_merge($resolved, [
                    'label' => $groupContent['label'] ?? $resolved['label'],
                    'hero_title' => $groupContent['hero_title'] ?? $resolved['hero_title'] ?? null,
                    'hero_subtitle' => $groupContent['hero_subtitle'] ?? $resolved['hero_subtitle'] ?? null,
                    'short_description' => $groupContent['short_description'] ?? $resolved['short_description'] ?? null,
                    'banner_image' => $groupContent['banner_image'] ?? $resolved['banner_image'] ?? null,
                ]);
            }
        }

        $categoryId = $request->integer('category') ?: null;
        $q = $request->string('q')->toString();

        $categories = collect();
        $activeCategory = null;
        if (Schema::hasTable('platform_categories')) {
            $categories = PlatformCategory::query()
                ->where('is_active', true)
                ->where('product_type', $type)
                ->orderBy('sort_order')
                ->get();

            if ($categoryId) {
                $activeCategory = $categories->firstWhere('id', $categoryId);
                if ($activeCategory) {
                    $resolved = $this->content->forCategory($activeCategory);
                } else {
                    $categoryId = null;
                }
            }
        } else {
            $categoryId = null;
        }

        $products = PlatformProduct::query()
            ->visibleToPublic()
            ->ofType($type)
            ->with(['productType.serviceCategory', 'activeVariants'])
            ->when($categoryId && Schema::hasColumn('platform_products', 'platform_category_id'), fn ($builder) => $builder->where('platform_category_id', $categoryId))
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($inner) use ($q) {
                    $inner->where('title', 'like', "%{$q}%")
                        ->orWhere('short_description', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate(12)
            ->withQueryString();

        $featuredQuery = PlatformProduct::query()
            ->visibleToPublic()
            ->featured()
            ->ofType($type)
            ->with(['productType.serviceCategory', 'activeVariants'])
            ->when($categoryId && Schema::hasColumn('platform_products', 'platform_category_id'), fn ($builder) => $builder->where('platform_category_id', $categoryId))
            ->orderBy('sort_order')
            ->limit(6);

        $featured = $featuredQuery->get();

        $canonicalGroup = $groupSlug;
        $filterAction = $canonicalGroup
            ? route('services.type', ['category' => $canonicalGroup, 'service' => $type])
            : route('services.segment', $type);

        return view('pages.services-type', [
            'typeKey' => $type,
            'content' => $resolved,
            'groupSlug' => $groupSlug,
            'groupContent' => $groupContent,
            'preferGroupSlug' => $preferGroupSlug ?: $canonicalGroup,
            'filterAction' => $filterAction,
            'categories' => $categories,
            'activeCategory' => $activeCategory,
            'featured' => $featured,
            'products' => $products,
            'filters' => [
                'q' => $q,
                'category' => $categoryId,
            ],
        ]);
    }

    /**
     * Two-segment URL: /services/{category}/{service} OR legacy /services/{type}/{productSlug}.
     */
    public function pair(Request $request, string $category, string $service): View|RedirectResponse
    {
        // Nested service listing under its category.
        if ($this->browse->isGroup($category) && $this->browse->isType($service)
            && $this->browse->typeBelongsToGroup($service, $category)) {
            return $this->type($request, $service, $category);
        }

        // Legacy (and still valid) product detail: first segment is the service/type slug.
        if ($this->browse->isType($category)) {
            return $this->show($category, $service);
        }

        abort(404);
    }

    /**
     * Nested product: /services/{category}/{service}/{productSlug}.
     */
    public function nestedShow(string $category, string $service, string $productSlug): View|RedirectResponse
    {
        if (! $this->browse->isGroup($category) || ! $this->browse->isType($service)
            || ! $this->browse->typeBelongsToGroup($service, $category)) {
            abort(404);
        }

        return $this->show($service, $productSlug);
    }

    public function show(string $type, string $productSlug): View|RedirectResponse
    {
        $product = PlatformProduct::query()
            ->visibleToPublic()
            ->where('slug', $productSlug)
            ->with(['productType.serviceCategory', 'images', 'activeVariants', 'heroMedia.variants'])
            ->firstOrFail();

        $typeSlug = $product->typeSlug();
        if ($typeSlug !== $type) {
            return $this->redirectToCanonicalProduct($product);
        }

        $groupSlug = $product->productType?->serviceCategory?->slug
            ?? $this->browse->groupForType($typeSlug);

        // Prefer nested canonical product URL when category is known.
        $request = request();
        if ($groupSlug && ! $request->routeIs('services.nested.show')) {
            return redirect()->to($this->browse->productUrl($product), 301);
        }

        if ($request->user()) {
            $this->activity->record($request->user()->id, 'viewed', $product, 'service.viewed');
        }

        return view('pages.services-show', [
            'product' => $product,
            'typeKey' => $typeSlug,
            'groupSlug' => $groupSlug,
            'groupContent' => $groupSlug ? $this->content->forGroup($groupSlug) : null,
            'typeContent' => $this->content->forType($typeSlug),
            'isFavorited' => $this->isFavorited($product),
        ]);
    }

    /**
     * Legacy /services/{slug}: group, type, old division, or product slug.
     */
    public function segment(string $segment): View|RedirectResponse
    {
        if (isset(self::DIVISION_TO_GROUP[$segment])) {
            $target = self::DIVISION_TO_GROUP[$segment];
            if ($target !== $segment) {
                return redirect()->route('services.segment', $target, 301);
            }
        }

        if ($this->browse->isGroup($segment)) {
            if ($this->browse->usesDbHierarchy()) {
                $category = $this->browse->findServiceCategory($segment);
                if ($category?->isMarketplaceLink()) {
                    return redirect()->route('services', status: 301);
                }
            } else {
                $routeName = config('catalog.groups.'.$segment.'.route');
                if ($routeName) {
                    return redirect()->route($routeName, status: 301);
                }
            }

            return $this->group(request(), $segment);
        }

        if ($this->browse->isType($segment)) {
            $categorySlug = $this->browse->groupForType($segment);
            if ($categorySlug) {
                return redirect()->route('services.type', [
                    'category' => $categorySlug,
                    'service' => $segment,
                ], 301);
            }

            return $this->type(request(), $segment);
        }

        $product = PlatformProduct::query()
            ->visibleToPublic()
            ->where('slug', $segment)
            ->with(['productType.serviceCategory'])
            ->first();

        if ($product) {
            return $this->redirectToCanonicalProduct($product);
        }

        abort(404);
    }

    private function redirectToCanonicalProduct(PlatformProduct $product): RedirectResponse
    {
        return redirect()->to($this->browse->productUrl($product), 301);
    }

    private function isFavorited(PlatformProduct $product): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $product->favorites()->where('user_id', $user->id)->exists();
    }
}
