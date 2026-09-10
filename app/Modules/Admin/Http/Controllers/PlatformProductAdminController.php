<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Enums\PlatformProductStatus;
use App\Enums\PlatformProductType;
use App\Http\Controllers\Controller;
use App\Models\PlatformProduct;
use App\Models\PlatformProductVariant;
use App\Models\ProductType;
use App\Models\ServiceCategory;
use App\Services\Media\MediaPathService;
use App\Services\Media\MediaUsageService;
use App\Support\SortOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlatformProductAdminController extends Controller
{
    public function __construct(
        private MediaUsageService $mediaUsages,
        private MediaPathService $mediaPaths,
    ) {}

    public function index(Request $request): View
    {
        $siblings = $this->systemProductSiblingsQuery();
        if ((clone $siblings)->where('sort_order', '<', 1)->exists()) {
            SortOrder::normalize($siblings);
        }

        $products = PlatformProduct::query()
            ->with(['serviceCategory', 'productType.serviceCategory', 'heroMedia.variants', 'activeVariants'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('title', 'like', $term)
                        ->orWhere('slug', 'like', $term)
                        ->orWhere('short_description', 'like', $term);
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->when($request->filled('service'), fn ($q) => $q->where('product_type_id', $request->integer('service')))
            ->when($request->filled('category'), function ($q) use ($request) {
                $q->where('service_category_id', $request->integer('category'));
            })
            ->when($request->filled('type') && ! $request->filled('service'), function ($q) use ($request) {
                $q->ofType($request->string('type')->toString());
            })
            ->when($request->filled('featured'), function ($q) use ($request) {
                if ($request->get('featured') === '1') {
                    $q->where('is_featured', true);
                } elseif ($request->get('featured') === '0') {
                    $q->where('is_featured', false);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.admin.platform-products', [
            'products' => $products,
            'types' => PlatformProductType::cases(),
            'serviceCategories' => ServiceCategory::query()->system()->orderBy('sort_order')->orderBy('name')->get(),
            'services' => ProductType::query()
                ->with('serviceCategory')
                ->whereHas('serviceCategory', fn ($q) => $q->system())
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'filters' => [
                'q' => $request->string('q')->toString(),
                'status' => $request->get('status'),
                'category' => $request->get('category'),
                'service' => $request->get('service'),
                'type' => $request->get('type'),
                'featured' => $request->get('featured'),
            ],
            'lockedCatalog' => true,
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()
            ->route('admin.platform-products')
            ->with('error', __('Platform products are fixed. You cannot add new ones.'));
    }

    public function store(): RedirectResponse
    {
        return redirect()
            ->route('admin.platform-products')
            ->with('error', __('Platform products are fixed. You cannot add new ones.'));
    }

    public function edit(PlatformProduct $platformProduct): View|RedirectResponse
    {
        $platformProduct->load(['variants', 'serviceCategory', 'productType.serviceCategory', 'heroMedia.variants']);

        if (! $this->isUnderSystemCatalog($platformProduct)) {
            return redirect()
                ->route('admin.platform-products')
                ->with('error', __('That product is not under a fixed platform category.'));
        }

        $siblings = $this->systemProductSiblingsQuery();
        if ((int) $platformProduct->sort_order < 1 || (clone $siblings)->where('sort_order', '<', 1)->exists()) {
            SortOrder::normalize($siblings);
            $platformProduct->refresh();
        }

        return view('dashboard.admin.platform-product-form', [
            'product' => $platformProduct,
            'lockedCatalog' => true,
            'serviceCategories' => ServiceCategory::query()->system()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, PlatformProduct $platformProduct): RedirectResponse
    {
        $platformProduct->loadMissing(['serviceCategory', 'productType.serviceCategory']);
        if (! $this->isUnderSystemCatalog($platformProduct)) {
            return redirect()
                ->route('admin.platform-products')
                ->with('error', __('That product is not under a fixed platform category.'));
        }

        $systemCategoryIds = ServiceCategory::query()->system()->pluck('id')->all();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'service_category_id' => ['required', 'integer', Rule::in($systemCategoryIds)],
            'status' => ['required', Rule::in([
                PlatformProductStatus::Draft->value,
                PlatformProductStatus::Published->value,
            ])],
            'hero_media_id' => ['nullable', 'integer', $this->mediaPaths->existsRule()],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['required', 'string', 'max:120'],
            'variants.*.price' => ['required', 'numeric', 'min:0'],
            'variants.*.description' => ['nullable', 'string', 'max:2000'],
            'is_campaign' => ['sometimes', 'boolean'],
            'agent_reward_per_completion' => ['nullable', 'numeric', 'min:0'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
        ]);

        $heroMediaId = filled($data['hero_media_id'] ?? null) ? (int) $data['hero_media_id'] : null;
        $heroPath = $this->mediaPaths->legacyPathFromMediaId($heroMediaId);

        $updatePayload = [
            'title' => $data['title'],
            'short_description' => $data['short_description'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            'is_featured' => $request->boolean('is_featured'),
            'hero_media_id' => $heroMediaId,
            'hero_image' => $heroPath,
            'is_campaign' => $request->boolean('is_campaign'),
            'agent_reward_per_completion' => $data['agent_reward_per_completion'] ?? null,
            'estimated_minutes' => $data['estimated_minutes'] ?? null,
        ];

        $platformProduct->update($updatePayload);
        $platformProduct->forceFill([
            'service_category_id' => (int) $data['service_category_id'],
        ])->save();

        $this->syncVariants($platformProduct, $data['variants']);
        SortOrder::normalize($this->systemProductSiblingsQuery());

        $this->mediaUsages->syncUsages($platformProduct, [
            'hero' => $heroMediaId,
        ]);

        if ($data['status'] === PlatformProductStatus::Published->value) {
            $this->assertPublishable($platformProduct->fresh(['variants']));
        }

        return redirect()
            ->route('admin.platform-products.edit', $platformProduct)
            ->with('status', 'Product updated.');
    }

    public function toggle(PlatformProduct $platformProduct): RedirectResponse
    {
        $platformProduct->loadMissing(['serviceCategory', 'productType.serviceCategory']);
        if (! $this->isUnderSystemCatalog($platformProduct)) {
            return back()->with('error', __('That product is not under a fixed platform category.'));
        }

        if ($platformProduct->status === PlatformProductStatus::Published) {
            $platformProduct->update(['status' => PlatformProductStatus::Draft]);
            $message = 'Product deactivated.';
        } else {
            $this->assertPublishable($platformProduct->fresh(['variants']));
            $platformProduct->update(['status' => PlatformProductStatus::Published]);
            $message = 'Product activated.';
        }

        return back()->with('status', $message);
    }

    public function destroy(): RedirectResponse
    {
        return redirect()
            ->route('admin.platform-products')
            ->with('error', __('Platform products cannot be deleted. Deactivate them instead.'));
    }

    private function isUnderSystemCatalog(PlatformProduct $product): bool
    {
        if ($product->serviceCategory?->isSystem()) {
            return true;
        }

        // Dual-write fallback before flatten backfill.
        return (bool) $product->productType?->serviceCategory?->isSystem();
    }

    private function systemProductSiblingsQuery()
    {
        return PlatformProduct::query()->where(function ($q) {
            $q->whereHas('serviceCategory', fn ($cat) => $cat->system())
                ->orWhereHas('productType.serviceCategory', fn ($cat) => $cat->system());
        });
    }

    /**
     * @param  list<array{id?: int|null, name: string, price: mixed, description?: string|null}>  $variants
     */
    private function syncVariants(PlatformProduct $product, array $variants): void
    {
        $existingIds = $product->variants()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $keepIds = [];
        $prices = [];

        DB::transaction(function () use ($product, $variants, $existingIds, &$keepIds, &$prices) {
            foreach (array_values($variants) as $index => $row) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    throw ValidationException::withMessages([
                        'variants' => 'Each plan needs a name.',
                    ]);
                }

                $price = (float) $row['price'];
                $prices[] = $price;
                $description = array_key_exists('description', $row)
                    ? (trim((string) ($row['description'] ?? '')) ?: null)
                    : null;

                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    if (! in_array($id, $existingIds, true)) {
                        throw ValidationException::withMessages([
                            'variants' => 'One of the plans could not be found for this product.',
                        ]);
                    }

                    PlatformProductVariant::query()
                        ->where('id', $id)
                        ->where('platform_product_id', $product->id)
                        ->update([
                            'name' => $name,
                            'label' => $name,
                            'price' => $price,
                            'description' => $description,
                            'sort_order' => $index,
                            'is_active' => true,
                            'is_default' => $index === 0,
                        ]);

                    $keepIds[] = $id;

                    continue;
                }

                $created = PlatformProductVariant::query()->create([
                    'platform_product_id' => $product->id,
                    'name' => $name,
                    'label' => $name,
                    'sku' => Str::slug($product->slug.'-'.$name).'-'.Str::lower(Str::random(4)),
                    'price' => $price,
                    'description' => $description,
                    'duration_months' => null,
                    'sort_order' => $index,
                    'is_default' => $index === 0,
                    'is_active' => true,
                ]);
                $keepIds[] = (int) $created->id;
            }

            $product->variants()
                ->whereNotIn('id', $keepIds)
                ->delete();
        });

        if ($prices !== []) {
            $product->update(['base_price' => min($prices)]);
        }
    }

    private function assertPublishable(PlatformProduct $product): void
    {
        $hasActive = $product->variants()->where('is_active', true)->exists();
        if (! $hasActive && (float) $product->base_price <= 0) {
            throw ValidationException::withMessages([
                'status' => 'Published products require an active variant or a base price.',
            ]);
        }
        if ($product->variants()->exists() && ! $hasActive) {
            throw ValidationException::withMessages([
                'status' => 'Published products require at least one active variant.',
            ]);
        }
    }
}
