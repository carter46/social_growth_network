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
use App\Support\ProductTaskProfile;
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

        $status = match ($request->string('status')->toString()) {
            'deactivated', PlatformProductStatus::Draft->value, PlatformProductStatus::Archived->value => 'deactivated',
            default => 'active',
        };

        $base = PlatformProduct::query()
            ->offered()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('title', 'like', $term)
                        ->orWhere('slug', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
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
            });

        $activeCount = (clone $base)->where('status', PlatformProductStatus::Published->value)->count();
        $deactivatedCount = (clone $base)->where('status', '!=', PlatformProductStatus::Published->value)->count();

        $products = $base
            ->with(['serviceCategory', 'productType.serviceCategory', 'heroMedia.variants', 'activeVariants'])
            ->where('status', $status === 'active' ? '=' : '!=', PlatformProductStatus::Published->value)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate(20)
            ->withQueryString();

        $data = [
            'products' => $products,
            'status' => $status,
            'activeCount' => $activeCount,
            'deactivatedCount' => $deactivatedCount,
        ];

        if ($request->headers->get('X-Dashboard-Tab') === '1') {
            return view('dashboard.admin.platform-products._table', $data);
        }

        return view('dashboard.admin.platform-products', $data + [
            'types' => PlatformProductType::cases(),
            'serviceCategories' => ServiceCategory::query()->listed()->orderBy('sort_order')->orderBy('name')->get(),
            'services' => ProductType::query()
                ->with('serviceCategory')
                ->whereHas('serviceCategory', fn ($q) => $q->system())
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'filters' => [
                'q' => $request->string('q')->toString(),
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

        $profile = $platformProduct->taskProfile();
        $allowedTasks = array_map(fn ($task) => $task->value, ProductTaskProfile::allowedTasks($platformProduct->slug));

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in([
                PlatformProductStatus::Draft->value,
                PlatformProductStatus::Published->value,
            ])],
            'hero_media_id' => ['nullable', 'integer', $this->mediaPaths->existsRule()],
            'pricing' => ['required', 'array'],
            'pricing.min_units' => ['required', 'integer', 'min:1'],
            'pricing.max_units' => ['required', 'integer', 'gte:pricing.min_units'],
            'pricing.pricing_units' => ['required', 'integer', 'min:1'],
            'pricing.price' => ['required', 'numeric', 'min:0.01'],
            'agent_reward_percent' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'estimated_minutes' => [
                Rule::requiredIf($profile->needsWatchMinutes()),
                'nullable',
                'integer',
                'min:1',
                'max:10080',
            ],
            'completion_minutes' => [
                Rule::requiredIf($profile->requiresCompletionTime()),
                'nullable',
                'integer',
                'min:1',
                'max:10080',
            ],
            'task_order' => ['nullable', 'array'],
            'task_order.*' => ['string', Rule::in($allowedTasks)],
            'task_limits' => ['nullable', 'array'],
            'task_limits.*' => ['array'],
            'task_limits.*.min' => ['required', 'integer', 'min:0', 'max:1000000'],
            'task_limits.*.max' => ['required', 'integer', 'min:1', 'max:1000000'],
        ], [
            'pricing.max_units.gte' => 'Maximum purchase must be greater than or equal to the minimum purchase.',
            'estimated_minutes.required' => 'Enter the watch time in minutes.',
            'completion_minutes.required' => 'Enter the time to complete in minutes.',
        ]);

        $watchMinutes = (int) ($data['estimated_minutes'] ?? 0);
        $completionMinutes = (int) ($data['completion_minutes'] ?? 0);
        if ($completionMinutes > 0 && $profile->needsWatchMinutes() && $watchMinutes > 0 && $completionMinutes <= $watchMinutes) {
            throw ValidationException::withMessages([
                'completion_minutes' => "Time to complete must be longer than the watch time ({$watchMinutes} minutes), so agents have time for every step.",
            ]);
        }

        $taskOrder = null;
        if (count($allowedTasks) > 1) {
            $normalized = ProductTaskProfile::normalizeOrder(
                ProductTaskProfile::allowedTasks($platformProduct->slug),
                $data['task_order'] ?? $platformProduct->task_order ?? $allowedTasks,
            );
            if ($normalized === null) {
                throw ValidationException::withMessages([
                    'task_order' => 'The task order must include each task exactly once.',
                ]);
            }
            $taskOrder = array_map(fn ($task) => $task->value, $normalized);
        }

        $taskLimits = $platformProduct->task_limits;
        $companions = $profile->companionTasks();
        if ($companions !== [] && array_key_exists('task_limits', $data)) {
            $taskLimits = [];
            $limitErrors = [];
            foreach ($companions as $task) {
                $row = $data['task_limits'][$task->value] ?? null;
                $label = strtolower($profile->quantityLabel($task));
                if (! is_array($row)) {
                    $limitErrors['task_limits.'.$task->value.'.min'] = "Enter the minimum and maximum {$label}.";

                    continue;
                }
                if ((int) $row['max'] < (int) $row['min']) {
                    $limitErrors['task_limits.'.$task->value.'.max'] = "Maximum {$label} must be greater than or equal to the minimum.";

                    continue;
                }
                $taskLimits[$task->value] = ['min' => (int) $row['min'], 'max' => (int) $row['max']];
            }
            if ($limitErrors !== []) {
                throw ValidationException::withMessages($limitErrors);
            }
        }

        $unitPrice = PlatformProductVariant::computeUnitPriceFromPackagePrice(
            $data['pricing']['price'],
            (int) $data['pricing']['pricing_units'],
        );
        if (bccomp(PlatformProductVariant::agentRewardForUnitPrice($unitPrice, $data['agent_reward_percent']), PlatformProductVariant::MIN_AGENT_REWARD, 4) < 0) {
            throw ValidationException::withMessages([
                'agent_reward_percent' => 'At '.money($unitPrice, 2, 6).' per unit, this percentage pays agents less than '
                    .money(PlatformProductVariant::MIN_AGENT_REWARD, 2, 4).'. Raise the price or the percentage.',
            ]);
        }

        $heroMediaId = filled($data['hero_media_id'] ?? null) ? (int) $data['hero_media_id'] : null;
        $heroPath = $this->mediaPaths->legacyPathFromMediaId($heroMediaId);

        $updatePayload = [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            'is_featured' => $request->boolean('is_featured'),
            'hero_media_id' => $heroMediaId,
            'hero_image' => $heroPath,
            'is_campaign' => true,
            'agent_reward_percent' => $data['agent_reward_percent'],
            'estimated_minutes' => $data['estimated_minutes'] ?? null,
            'completion_minutes' => $completionMinutes > 0 ? $completionMinutes : null,
            'task_order' => $taskOrder,
            'task_limits' => $taskLimits,
        ];

        DB::transaction(function () use ($platformProduct, $updatePayload, $data, $unitPrice, $heroMediaId) {
            $platformProduct->update($updatePayload);

            $this->syncPricing($platformProduct, $data['pricing'], $unitPrice);
            SortOrder::normalize($this->systemProductSiblingsQuery());

            $this->mediaUsages->syncUsages($platformProduct, [
                'hero' => $heroMediaId,
            ]);

            if ($data['status'] === PlatformProductStatus::Published->value) {
                $this->assertPublishable($platformProduct->fresh(['variants']));
            }
        });

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
     * Upsert the product's single pricing row. Other rows are deactivated, not deleted, because past orders reference them.
     *
     * @param  array{min_units: int|string, max_units: int|string, pricing_units: int|string, price: float|string}  $pricing
     */
    private function syncPricing(PlatformProduct $product, array $pricing, string $unitPrice): void
    {
        $minUnits = (int) $pricing['min_units'];

        DB::transaction(function () use ($product, $pricing, $unitPrice, $minUnits) {
            $payload = [
                'price' => number_format((float) $pricing['price'], 2, '.', ''),
                'pricing_mode' => PlatformProductVariant::PRICING_PER_UNIT,
                'pricing_units' => (int) $pricing['pricing_units'],
                'unit_price' => $unitPrice,
                'min_units' => $minUnits,
                'max_units' => (int) $pricing['max_units'],
                'sort_order' => 0,
                'is_active' => true,
                'is_default' => true,
            ];

            $variant = $product->pricingVariant();
            if ($variant) {
                $variant->update($payload);
            } else {
                $variant = PlatformProductVariant::query()->create(array_merge($payload, [
                    'platform_product_id' => $product->id,
                    'name' => 'Standard',
                    'label' => 'Standard',
                    'sku' => Str::slug($product->slug.'-standard').'-'.Str::lower(Str::random(4)),
                    'duration_months' => null,
                ]));
            }

            $product->variants()
                ->whereKeyNot($variant->id)
                ->update(['is_active' => false, 'is_default' => false]);

            $product->update(['base_price' => round((float) $unitPrice * $minUnits, 2)]);
        });
    }

    private function assertPublishable(PlatformProduct $product): void
    {
        if (! $product->isOffered()) {
            throw ValidationException::withMessages([
                'status' => 'This service is no longer offered, so it cannot be activated.',
            ]);
        }
        if (! $product->pricingVariant()) {
            throw ValidationException::withMessages([
                'status' => 'Active products need pricing (minimum, maximum, pricing units and price).',
            ]);
        }
        if ((float) ($product->agent_reward_percent ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'status' => 'Active products need an agent reward percentage.',
            ]);
        }
        $taskIssues = $product->taskSetupIssues();
        if ($taskIssues !== []) {
            throw ValidationException::withMessages([
                'status' => 'Fix this before activating: '.implode(', ', $taskIssues).'.',
            ]);
        }
    }
}
