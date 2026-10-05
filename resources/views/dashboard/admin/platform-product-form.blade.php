@extends('layouts.dashboard-admin')

@section('title', 'Edit Product')

@section('content')
@php
    $engagementMetric = \App\Enums\EngagementMetric::fromProductSlug($product->slug);
    $pricingVariant = $product->pricingVariant();
    $labelVariant = $pricingVariant ?? new \App\Models\PlatformProductVariant();
    $unitSingular = $labelVariant->resolveUnitLabel($engagementMetric);
    $unitPlural = $labelVariant->resolveUnitLabelPlural($engagementMetric);
    $defaultUnits = \App\Models\PlatformProductVariant::REFERENCE_UNITS;
    $pricing = [
        'min_units' => old('pricing.min_units', $pricingVariant?->min_units ?? $defaultUnits),
        'max_units' => old('pricing.max_units', $pricingVariant?->max_units ?? \App\Models\PlatformProductVariant::DEFAULT_MAX_UNITS),
        'pricing_units' => old('pricing.pricing_units', $pricingVariant?->pricing_units ?? $defaultUnits),
        'price' => old('pricing.price', $pricingVariant?->price ?? ''),
        'reward_percent' => old('agent_reward_percent', $product->agent_reward_percent),
    ];
    $heroId = old('hero_media_id', $product->hero_media_id);
    $heroPreview = $heroId
        ? \App\Models\MediaAsset::query()->with('variants')->find((int) $heroId)?->thumbnailUrl()
        : null;
    $minutesLabel = $engagementMetric?->requiresTimedSession(
        \App\Enums\EngagementMetric::platformFromProductSlug($product->slug)
    )
        ? 'Required watch session (minutes)'
        : 'Estimated minutes per task';
    $minutesHint = $engagementMetric?->requiresTimedSession(
        \App\Enums\EngagementMetric::platformFromProductSlug($product->slug)
    )
        ? 'Minutes the agent must complete in the watch session before claiming. Not a guarantee of platform watch hours or views.'
        : 'Shown to agents as estimated time, not a retake lock.';
    $inputClass = 'w-full rounded-xl border border-border-default bg-elevated px-3 py-2.5 text-sm';
@endphp
<x-layout.page
    title="Edit Product"
    subtitle="Platform product: title, description, pricing, agent reward, image, featured and status."
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
                pricing: @js($pricing),
                unitSingular: @js($unitSingular),
                unitPlural: @js($unitPlural),
                unitPrice() {
                    const price = Number(this.pricing.price);
                    const units = Number(this.pricing.pricing_units);
                    if (!Number.isFinite(price) || price <= 0 || !Number.isFinite(units) || units < 1) return null;
                    return Math.floor((price / units) * 10000) / 10000;
                },
                agentReward() {
                    const unit = this.unitPrice();
                    const pct = Number(this.pricing.reward_percent);
                    if (unit === null || !Number.isFinite(pct) || pct <= 0) return null;
                    return Math.floor(unit * Math.min(100, pct)) / 100;
                },
                exampleUnits() {
                    return Math.max(1, Math.round(Number(this.pricing.min_units) || 1));
                },
                formatMoney(n) {
                    if (n === null || n === undefined || !Number.isFinite(n)) return '-';
                    return '₦' + n.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 4 });
                }
            }"
            @submit="submitting = true"
        >
            @csrf
            @method('PUT')

            <p class="text-xs text-text-muted">
                Slug frozen: <span class="font-mono">{{ $product->slug }}</span>
                · Category frozen:
                <span class="font-medium text-text-secondary">{{ $product->serviceCategory?->name ?? '-' }}</span>
                @if($product->productType)
                    · Service (legacy/CMS): {{ $product->productType->name }}
                @endif
                · Sort #{{ max(1, (int) $product->sort_order) }} (auto-managed)
            </p>

            <x-dashboard.input label="Title" name="title" :value="old('title', $product->title)" required />
            <div>
                <label class="block text-sm font-medium mb-1">Description</label>
                <textarea name="description" rows="6" class="{{ $inputClass }}">{{ old('description', $product->description) }}</textarea>
            </div>

            <x-dashboard.select label="Status" name="status" required>
                <option value="published" @selected(old('status', $product->status?->value ?? $product->status) === 'published')>Published (active)</option>
                <option value="draft" @selected(old('status', $product->status?->value ?? $product->status) === 'draft')>Draft (deactivated)</option>
            </x-dashboard.select>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured))>
                Featured (show in featured sections on public / user pages)
            </label>

            <div class="space-y-4 rounded-xl border border-border-subtle px-4 py-4">
                <div>
                    <p class="text-sm font-medium text-text-primary">Pricing</p>
                    <p class="text-xs text-text-muted">
                        Creators enter any quantity between the minimum and maximum purchase. They pay the unit price for every {{ $unitSingular }}.
                    </p>
                </div>

                @error('pricing')
                    <x-dashboard.alert type="danger">{{ $message }}</x-dashboard.alert>
                @enderror

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs text-text-muted">Minimum purchase ({{ $unitPlural }})</label>
                        <input type="number" min="1" step="1" name="pricing[min_units]" x-model="pricing.min_units" class="{{ $inputClass }}" required>
                        @error('pricing.min_units')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-text-muted">Maximum purchase ({{ $unitPlural }})</label>
                        <input type="number" min="1" step="1" name="pricing[max_units]" x-model="pricing.max_units" class="{{ $inputClass }}" required>
                        @error('pricing.max_units')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-text-muted">Pricing unit (price is per this many {{ $unitPlural }})</label>
                        <input type="number" min="1" step="1" name="pricing[pricing_units]" x-model="pricing.pricing_units" class="{{ $inputClass }}" required>
                        @error('pricing.pricing_units')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-text-muted">
                            Price per <span x-text="Number(pricing.pricing_units || 0).toLocaleString()"></span> {{ $unitPlural }} (NGN)
                        </label>
                        <input type="number" min="0.01" step="0.01" name="pricing[price]" x-model="pricing.price" class="{{ $inputClass }}" required>
                        @error('pricing.price')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-text-muted">Agent reward (% of the unit price)</label>
                        <input type="number" min="0.01" max="100" step="0.01" name="agent_reward_percent" x-model="pricing.reward_percent" class="{{ $inputClass }}" required>
                        @error('agent_reward_percent')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                        <p class="mt-1 text-xs text-text-muted">Each agent completes one {{ $unitSingular }} per campaign. The reward is locked into each campaign at purchase.</p>
                    </div>
                </div>

                <div class="rounded-lg border border-border-default bg-elevated/60 px-3 py-2 text-xs text-text-secondary" x-show="unitPrice() !== null" x-cloak>
                    <p>
                        Unit price: <span class="font-semibold text-text-primary" x-text="formatMoney(unitPrice())"></span> per {{ $unitSingular }}
                        · Agent earns <span class="font-semibold text-text-primary" x-text="formatMoney(agentReward())"></span> per completed {{ $unitSingular }}
                    </p>
                    <p class="mt-1">
                        Example: <span x-text="exampleUnits().toLocaleString()"></span> {{ $unitPlural }} =
                        <span class="font-semibold text-text-primary" x-text="formatMoney(Math.round(exampleUnits() * unitPrice() * 100) / 100)"></span>
                    </p>
                </div>
            </div>

            <div class="sm:max-w-sm">
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

            <div class="flex flex-wrap gap-2 pt-2">
                <x-dashboard.button type="submit" variant="primary" x-bind:disabled="submitting">Save</x-dashboard.button>
                <x-dashboard.button :href="route('admin.platform-products')" variant="secondary">Cancel</x-dashboard.button>
            </div>
        </form>
    </x-dashboard.card>
</x-layout.page>
@endsection
