<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ProductType;
use App\Models\ServiceCategory;
use App\Services\Media\MediaPathService;
use App\Services\Media\MediaUsageService;
use App\Support\FaqNormalizer;
use App\Support\SortOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceAdminController extends Controller
{
    public function __construct(
        private MediaUsageService $mediaUsages,
        private MediaPathService $mediaPaths,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        return redirect()
            ->route('admin.service-categories')
            ->with('status', __('Services are managed as Categories → Products. Use Categories and Products instead.'));
    }

    public function create(): RedirectResponse
    {
        return redirect()
            ->route('admin.service-categories')
            ->with('error', __('Platform services are fixed. You cannot add new ones.'));
    }

    public function store(): RedirectResponse
    {
        return redirect()
            ->route('admin.service-categories')
            ->with('error', __('Platform services are fixed. You cannot add new ones.'));
    }

    public function edit(ProductType $service): RedirectResponse
    {
        return redirect()
            ->route('admin.service-categories')
            ->with('status', __('Services are managed as Categories → Products. Use Categories and Products instead.'));
    }

    public function update(Request $request, ProductType $service): RedirectResponse
    {
        $service->loadMissing('serviceCategory');
        if (! $service->serviceCategory?->isSystem()) {
            return redirect()
                ->route('admin.service-categories')
                ->with('error', __('That service is not under a fixed platform category.'));
        }

        $siblings = ProductType::query()->whereHas('serviceCategory', fn ($q) => $q->system());
        $siblingMax = max(1, (clone $siblings)->count());

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:1', 'max:'.$siblingMax],
            'short_description' => ['nullable', 'string', 'max:500'],
            'faq' => ['nullable', 'array'],
            'faq.*.q' => ['nullable', 'string', 'max:500'],
            'faq.*.a' => ['nullable', 'string', 'max:2000'],
            'faq.*.open' => ['nullable', 'boolean'],
            'card_media_id' => ['nullable', 'integer', $this->mediaPaths->existsRule()],
        ]);

        $cardMediaId = isset($data['card_media_id']) ? (int) $data['card_media_id'] : null;
        $path = $this->mediaPaths->legacyPathFromMediaId($cardMediaId);

        $shortDescription = $data['short_description'] ?? null;

        $service->update([
            'name' => $data['name'],
            'is_active' => $request->boolean('is_active'),
            'short_description' => $shortDescription,
            'hero_title' => $data['name'],
            'hero_subtitle' => $shortDescription,
            'benefits' => [],
            'faq' => FaqNormalizer::fromRequest($data['faq'] ?? null),
            'card_media_id' => $cardMediaId,
            'banner_media_id' => $cardMediaId,
            'card_image' => $path,
            'banner_image' => $path,
        ]);

        $this->mediaUsages->syncUsages($service, [
            'card' => $cardMediaId,
            'banner' => $cardMediaId,
        ]);

        SortOrder::move($service, (int) $data['sort_order'], $siblings);

        return redirect()
            ->route('admin.service-categories')
            ->with('status', 'Service updated.');
    }

    public function toggle(ProductType $service): RedirectResponse
    {
        $service->loadMissing('serviceCategory');
        if (! $service->serviceCategory?->isSystem()) {
            return redirect()
                ->route('admin.service-categories')
                ->with('error', __('That service is not under a fixed platform category.'));
        }

        $service->update(['is_active' => ! $service->is_active]);

        return redirect()
            ->route('admin.service-categories')
            ->with('status', 'Service '.($service->is_active ? 'activated' : 'deactivated').'.');
    }

    public function destroy(): RedirectResponse
    {
        return redirect()
            ->route('admin.service-categories')
            ->with('error', __('Platform services cannot be deleted.'));
    }
}
