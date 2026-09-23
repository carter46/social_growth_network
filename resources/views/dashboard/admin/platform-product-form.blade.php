@extends('layouts.dashboard-admin')

@section('title', 'Edit Product')

@section('content')
@php
    $isCampaignProduct = (bool) old('is_campaign', $product->is_campaign ?? false);
    $variantRows = old('variants', $product->relationLoaded('variants') && $product->variants->isNotEmpty()
        ? $product->variants->sortBy('sort_order')->values()->map(fn ($v) => [
            'id' => $v->id,
            'name' => $v->name,
            'price' => $v->price,
            'description' => $v->description,
            'per_unit' => $v->isPerUnit(),
            'min_units' => $v->min_units,
            'max_units' => $v->max_units,
            'unit_label' => $v->unit_label,
            'included_units' => $v->included_units,
        ])->all()
        : [
            [
                'id' => null,
                'name' => 'Standard',
                'price' => $product->base_price ?? 0,
                'description' => '',
                'per_unit' => false,
                'min_units' => null,
                'max_units' => null,
                'unit_label' => '',
                'included_units' => null,
            ],
        ]);
    $heroId = old('hero_media_id', $product->hero_media_id);
    $heroPreview = $heroId
        ? \App\Models\MediaAsset::query()->with('variants')->find((int) $heroId)?->thumbnailUrl()
        : null;
    $refUnits = \App\Models\PlatformProductVariant::REFERENCE_UNITS;
@endphp
<x-layout.page
    title="Edit Product"
    subtitle="Fixed platform product — title, description, plan prices, image, featured, and status."
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Products', route('admin.platform-products')],
        ['Edit', null],
    ]"
>
    <x-dashboard.card>
        @if (session('status'))
            <x-dashboard.alert type="success" class="mb-4">{{ session('status') }}</x-dashboard.alert>
        @endif
        @if (session('error'))
            <x-dashboard.alert type="danger" class="mb-4">{{ session('error') }}</x-dashboard.alert>
        @endif
        <form
            method="POST"
            action="{{ route('admin.platform-products.update', $product) }}"
            class="w-full space-y-4"
            x-data="{
                submitting: false,
                isCampaign: @js($isCampaignProduct),
                variants: @js($variantRows),
                refUnits: {{ (int) $refUnits }},
                addVariant() {
                    this.variants.push({
                        id: null,
                        name: '',
                        price: '',
                        description: '',
                        per_unit: false,
                        min_units: null,
                        max_units: null,
                        unit_label: '',
                        included_units: null,
                    });
                },
                removeVariant(index) {
                    if (this.variants.length <= 1) return;
                    this.variants.splice(index, 1);
                },
                unitRate(variant) {
                    const price = Number(variant.price);
                    if (!Number.isFinite(price) || price < 0) return null;
                    return price / this.refUnits;
                },
                formatMoney(n) {
                    if (n === null || n === undefined || !Number.isFinite(n)) return '—';
                    return '₦' + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 4 });
                }
            }"
            @submit="submitting = true"
        >
            @csrf
            @method('PUT')

            <p class="text-xs text-text-muted">
                Slug frozen: <span class="font-mono">{{ $product->slug }}</span>
                · Category frozen:
                <span class="font-medium text-text-secondary">{{ $product->serviceCategory?->name ?? '—' }}</span>
                @if($product->productType)
                    · Service (legacy/CMS): {{ $product->productType->name }}
                @endif
                · Sort #{{ max(1, (int) $product->sort_order) }} (auto-managed)
            </p>

            <x-dashboard.input label="Title" name="title" :value="old('title', $product->title)" required />
            <div>
                <label class="block text-sm font-medium mb-1">Description</label>
                <textarea name="description" rows="6" class="w-full rounded-xl border border-border-default bg-elevated px-3 py-2.5 text-sm">{{ old('description', $product->description) }}</textarea>
            </div>

            <x-dashboard.select label="Status" name="status" required>
                <option value="published" @selected(old('status', $product->status?->value ?? $product->status) === 'published')>Published (active)</option>
                <option value="draft" @selected(old('status', $product->status?->value ?? $product->status) === 'draft')>Draft (deactivated)</option>
            </x-dashboard.select>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured))>
                Featured (show in featured sections on public / user pages)
            </label>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_campaign" value="1" @checked(old('is_campaign', $product->is_campaign ?? false)) x-model="isCampaign">
                Campaign package (creates a campaign agents can work on after purchase)
            </label>

            @php
                $engagementMetric = \App\Enums\EngagementMetric::fromProductSlug($product->slug);
                $minutesLabel = $engagementMetric?->requiresTimedSession(
                    \App\Enums\EngagementMetric::platformFromProductSlug($product->slug)
                )
                    ? 'Required watch session (minutes)'
                    : 'Estimated minutes per task';
                $minutesHint = $engagementMetric?->requiresTimedSession(
                    \App\Enums\EngagementMetric::platformFromProductSlug($product->slug)
                )
                    ? 'Minutes the agent must complete in the watch session before claiming. Not a guarantee of platform watch hours or views.'
                    : 'Shown to agents as estimated time — not a retake lock.';
            @endphp
            <div class="grid gap-4 sm:grid-cols-2">
                <x-dashboard.input
                    label="Agent reward per completion (NGN)"
                    name="agent_reward_per_completion"
                    type="number"
                    step="0.01"
                    min="0.01"
                    :value="old('agent_reward_per_completion', $product->agent_reward_per_completion)"
                    hint="Locked into each campaign at purchase. Changing this later does not affect existing campaigns."
                />
                <x-dashboard.input
                    :label="$minutesLabel"
                    name="estimated_minutes"
                    type="number"
                    min="1"
                    :value="old('estimated_minutes', $product->estimated_minutes)"
                    :hint="$minutesHint"
                />
            </div>

            <x-dashboard.media-picker
                name="hero_media_id"
                label="Image"
                hint="Product image shown on the product page (single image, not a gallery)."
                preview="wide"
                :value="$heroId"
                :preview-url="$heroPreview"
            />

            <div class="space-y-3 rounded-xl border border-border-subtle px-4 py-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-text-primary">Plans / variants</p>
                        <p class="text-xs text-text-muted">
                            Each plan can be fixed-price or per-unit. Per-unit rate is always price ÷ {{ $refUnits }} (platform reference). Min/max options appear only when Per unit is checked.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-lg border border-border-default bg-elevated px-3 py-1.5 text-xs font-medium text-text-primary hover:bg-muted"
                        @click="addVariant()"
                    >Add plan</button>
                </div>

                <template x-for="(variant, index) in variants" :key="index">
                    <div class="space-y-2 rounded-xl border border-border-default bg-muted/20 p-3">
                        <input type="hidden" :name="'variants[' + index + '][id]'" :value="variant.id || ''">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-xs font-medium text-text-muted" x-text="'Plan ' + (index + 1)"></p>
                            <button
                                type="button"
                                class="text-xs font-medium text-danger hover:underline disabled:opacity-40"
                                @click="removeVariant(index)"
                                :disabled="variants.length <= 1"
                            >Remove</button>
                        </div>
                        <div class="grid grid-cols-1 gap-2 md:grid-cols-2 items-end">
                            <div>
                                <label class="mb-1 block text-xs text-text-muted">Plan name</label>
                                <input
                                    type="text"
                                    class="w-full rounded-xl border border-border-default bg-elevated px-3 py-2.5 text-sm"
                                    :name="'variants[' + index + '][name]'"
                                    x-model="variant.name"
                                    required
                                    placeholder="1,000 Views"
                                >
                            </div>
                            <div>
                                <label class="mb-1 block text-xs text-text-muted">Price (NGN)</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="w-full rounded-xl border border-border-default bg-elevated px-3 py-2.5 text-sm"
                                    :name="'variants[' + index + '][price]'"
                                    x-model="variant.price"
                                    required
                                >
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-text-muted">Plan description</label>
                            <textarea
                                rows="2"
                                class="w-full rounded-xl border border-border-default bg-elevated px-3 py-2.5 text-sm"
                                :name="'variants[' + index + '][description]'"
                                x-model="variant.description"
                                placeholder="What this plan includes…"
                            ></textarea>
                        </div>

                        <label class="flex items-center gap-2 text-sm pt-1">
                            <input
                                type="checkbox"
                                value="1"
                                :name="'variants[' + index + '][per_unit]'"
                                x-model="variant.per_unit"
                            >
                            Per unit (creators enter total units; rate = price ÷ {{ $refUnits }})
                        </label>

                        <div class="space-y-2 rounded-lg border border-border-default bg-elevated/60 p-3" x-show="variant.per_unit" x-cloak>
                            <p class="text-xs text-text-muted" x-text="'Calculated rate: ' + formatMoney(unitRate(variant)) + ' per unit (₦' + (Number(variant.price) || 0).toLocaleString() + ' ÷ ' + refUnits + ')'"></p>
                            <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
                                <div>
                                    <label class="mb-1 block text-xs text-text-muted">Minimum units</label>
                                    <input
                                        type="number"
                                        min="1"
                                        class="w-full rounded-xl border border-border-default bg-elevated px-3 py-2.5 text-sm"
                                        :name="'variants[' + index + '][min_units]'"
                                        x-model="variant.min_units"
                                        :required="variant.per_unit"
                                    >
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs text-text-muted">Maximum units</label>
                                    <input
                                        type="number"
                                        min="1"
                                        class="w-full rounded-xl border border-border-default bg-elevated px-3 py-2.5 text-sm"
                                        :name="'variants[' + index + '][max_units]'"
                                        x-model="variant.max_units"
                                        placeholder="100000"
                                    >
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs text-text-muted">Unit label (optional)</label>
                                    <input
                                        type="text"
                                        maxlength="32"
                                        class="w-full rounded-xl border border-border-default bg-elevated px-3 py-2.5 text-sm"
                                        :name="'variants[' + index + '][unit_label]'"
                                        x-model="variant.unit_label"
                                        placeholder="view"
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="pt-1" x-show="isCampaign && !variant.per_unit" x-cloak>
                            <label class="mb-1 block text-xs text-text-muted">Included units (campaign completions in this fixed package)</label>
                            <input
                                type="number"
                                min="1"
                                class="w-full max-w-xs rounded-xl border border-border-default bg-elevated px-3 py-2.5 text-sm"
                                :name="'variants[' + index + '][included_units]'"
                                x-model="variant.included_units"
                                :required="isCampaign && !variant.per_unit"
                            >
                        </div>
                    </div>
                </template>
            </div>

            <div class="flex flex-wrap gap-2 pt-2">
                <x-dashboard.button type="submit" variant="primary" x-bind:disabled="submitting">Save</x-dashboard.button>
                <x-dashboard.button :href="route('admin.platform-products')" variant="secondary">Cancel</x-dashboard.button>
            </div>
        </form>
    </x-dashboard.card>
</x-layout.page>
@endsection
