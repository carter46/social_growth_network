<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PlatformCategory;
use App\Models\PlatformProduct;
use App\Modules\Catalog\Services\CatalogBrowseService;
use App\Modules\Catalog\Services\CatalogContentResolver;
use App\Support\PlatformProductSlugRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ServiceController extends Controller
{
    /** @var array<string, true> retired catalog division/group slugs → 301 to /services hub */
    private const LEGACY_HUB_REDIRECTS = [
        'digital-services' => true,
        'web-solutions' => true,
        'trust-protection' => true,
        'network-services' => true,
        'website-services' => true,
        'trust-escrow' => true,
        'communication' => true,
        'business-documents' => true,
    ];

    public function __construct(
        private CatalogBrowseService $browse,
        private CatalogContentResolver $content,
        private \App\Services\Analytics\UserActivityRecorder $activity,
    ) {}

    public function index(Request $request): View
    {
        $q = $request->string('q')->toString();
        $categorySlug = $request->string('category')->toString();
        $sort = $request->string('sort')->toString() ?: 'popular';
        $budget = $request->string('budget')->toString();

        $groups = $this->browse->groupCards($this->content);

        if ($categorySlug !== '' && ! $this->browse->isGroup($categorySlug)) {
            $categorySlug = '';
        }

        $productsQuery = PlatformProduct::query()
            ->visibleToPublic()
            ->with([
                'serviceCategory',
                'productType.serviceCategory',
                'activeVariants',
                'heroMedia.variants',
            ]);

        if ($categorySlug !== '') {
            $category = $this->browse->findServiceCategory($categorySlug);
            if ($category && Schema::hasColumn('platform_products', 'service_category_id')) {
                $productsQuery->ofCategory($category);
            }
        }

        if ($q !== '') {
            $productsQuery->where(function ($inner) use ($q) {
                $inner->where('title', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        // Budget presets use catalog starting price (base_price mirrors package floor).
        if ($budget === 'under_10k') {
            $productsQuery->where('base_price', '<', 10000);
        } elseif ($budget === '10k_25k') {
            $productsQuery->whereBetween('base_price', [10000, 25000]);
        } elseif ($budget === '25k_plus') {
            $productsQuery->where('base_price', '>', 25000);
        }

        match ($sort) {
            'price_asc' => $productsQuery->orderBy('base_price')->orderBy('title'),
            'price_desc' => $productsQuery->orderByDesc('base_price')->orderBy('title'),
            default => $productsQuery
                ->orderByDesc('is_featured')
                ->orderBy('sort_order')
                ->orderBy('title'),
        };

        $products = $productsQuery
            ->paginate(12)
            ->withQueryString();

        $totalVisible = PlatformProduct::query()->visibleToPublic()->count();

        $payload = [
            'groups' => $groups,
            'products' => $products,
            'q' => $q,
            'activeCategory' => $categorySlug,
            'sort' => $sort,
            'budget' => $budget,
            'totalVisible' => $totalVisible,
            'popularTags' => $this->browse->homePopularSearchTags(5),
        ];

        if ($request->headers->get('X-Services-Filter') === '1' || $request->boolean('partial')) {
            return view('partials.catalog.services-results', $payload);
        }

        return view('pages.services', $payload);
    }

    /**
     * Category page: /services/{category} — lists products owned by ServiceCategory.
     * /services path is presentation only; ownership is Category → Product.
     */
    public function group(Request $request, string $group): View
    {
        abort_unless($this->browse->isGroup($group), 404);

        $resolved = $this->content->forGroup($group);
        $serviceCategory = $this->browse->findServiceCategory($group);
        abort_unless($serviceCategory || ! $this->browse->usesDbHierarchy(), 404);

        $q = $request->string('q')->toString();

        $productsQuery = PlatformProduct::query()
            ->visibleToPublic()
            ->with(['serviceCategory', 'productType.serviceCategory', 'activeVariants']);

        if ($serviceCategory && Schema::hasColumn('platform_products', 'service_category_id')) {
            $productsQuery->ofCategory($serviceCategory);
        } else {
            $typeKeys = $resolved['types'] ?? config('catalog.groups.'.$group.'.types', []);
            $productsQuery->ofTypeMany($typeKeys);
        }

        $products = $productsQuery
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($inner) use ($q) {
                    $inner->where('title', 'like', "%{$q}%")
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
            'typeKeys' => [],
            'typeCards' => collect(),
            'categories' => collect(),
            'products' => $products,
            'filters' => [
                'q' => $q,
                'category' => null,
                'type' => null,
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
     * Two-segment URL: /services/{category}/{productSlug} (canonical Category→Product)
     * OR legacy /services/{category}/{service} listing OR /services/{type}/{productSlug}.
     * The /services prefix is presentation only — product ownership is service_category_id.
     */
    public function pair(Request $request, string $category, string $service): View|RedirectResponse
    {
        // Canonical: category owns product (Category → Product).
        if ($this->browse->isGroup($category)) {
            if ($redirect = $this->redirectLegacyProductSlug($service)) {
                return $redirect;
            }

            $product = PlatformProduct::query()
                ->visibleToPublic()
                ->where('slug', $service)
                ->with(['serviceCategory', 'productType.serviceCategory', 'images', 'activeVariants', 'heroMedia.variants'])
                ->first();

            if ($product) {
                if ($product->categorySlug() === $category) {
                    return $this->renderProduct($product);
                }

                // Wrong category segment — 301 to the owning category URL.
                return $this->redirectToCanonicalProduct($product);
            }
        }

        // Legacy nested service listing under its category (ProductType mid-layer).
        if ($this->browse->isGroup($category) && $this->browse->isType($service)
            && $this->browse->typeBelongsToGroup($service, $category)) {
            return $this->type($request, $service, $category);
        }

        // Legacy product detail: first segment is the service/type slug.
        if ($this->browse->isType($category)) {
            return $this->show($category, $service);
        }

        abort(404);
    }

    /**
     * Legacy nested product: /services/{category}/{service}/{productSlug} → 301 to Category→Product URL.
     */
    public function nestedShow(string $category, string $service, string $productSlug): View|RedirectResponse
    {
        if ($redirect = $this->redirectLegacyProductSlug($productSlug)) {
            return $redirect;
        }

        $product = PlatformProduct::query()
            ->visibleToPublic()
            ->where('slug', $productSlug)
            ->with(['serviceCategory', 'productType.serviceCategory'])
            ->first();

        if ($product) {
            return $this->redirectToCanonicalProduct($product);
        }

        abort(404);
    }

    public function show(string $type, string $productSlug): View|RedirectResponse
    {
        if ($redirect = $this->redirectLegacyProductSlug($productSlug)) {
            return $redirect;
        }

        $product = PlatformProduct::query()
            ->visibleToPublic()
            ->where('slug', $productSlug)
            ->with(['serviceCategory', 'productType.serviceCategory', 'images', 'activeVariants', 'heroMedia.variants'])
            ->firstOrFail();

        // If first segment is a type slug that doesn't match, or category differs, canonicalize.
        $categorySlug = $product->categorySlug();
        if ($categorySlug && $type !== $categorySlug && $product->typeSlug() !== $type) {
            return $this->redirectToCanonicalProduct($product);
        }

        if ($categorySlug && $type === $categorySlug) {
            return $this->renderProduct($product);
        }

        $typeSlug = $product->typeSlug();
        if ($typeSlug && $typeSlug === $type && ! $categorySlug) {
            // Visible via legacy ProductType path with no category slug yet — render, do not redirect-loop.
            return $this->renderProduct($product);
        }

        if ($typeSlug !== $type || $categorySlug) {
            return $this->redirectToCanonicalProduct($product);
        }

        return $this->renderProduct($product);
    }

    private function renderProduct(PlatformProduct $product): View
    {
        $typeSlug = $product->typeSlug();
        $groupSlug = $product->categorySlug()
            ?? $product->productType?->serviceCategory?->slug
            ?? ($typeSlug ? $this->browse->groupForType($typeSlug) : null);

        if (request()->user()) {
            $this->activity->record(request()->user()->id, 'viewed', $product, 'service.viewed');
        }

        return view('pages.services-show', [
            'product' => $product,
            'typeKey' => $typeSlug,
            'groupSlug' => $groupSlug,
            'groupContent' => $groupSlug ? $this->content->forGroup($groupSlug) : null,
            'typeContent' => $typeSlug ? $this->content->forType($typeSlug) : null,
            'isFavorited' => $this->isFavorited($product),
        ]);
    }

    /**
     * Legacy /services/{slug}: group, type, old division, or product slug.
     */
    public function segment(string $segment): View|RedirectResponse
    {
        if (isset(self::LEGACY_HUB_REDIRECTS[$segment])) {
            return redirect()->route('services', status: 301);
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

        if ($redirect = $this->redirectLegacyProductSlug($segment)) {
            return $redirect;
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

    /**
     * 301 old Views product slugs to the owning-category canonical URL.
     */
    private function redirectLegacyProductSlug(string $slug): ?RedirectResponse
    {
        $canonicalSlug = PlatformProductSlugRedirect::resolve($slug);
        if ($canonicalSlug === null) {
            return null;
        }

        $product = PlatformProduct::query()
            ->visibleToPublic()
            ->where('slug', $canonicalSlug)
            ->with(['serviceCategory', 'productType.serviceCategory'])
            ->first();

        if ($product) {
            return $this->redirectToCanonicalProduct($product);
        }

        return null;
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
