@extends('layouts.dashboard-user')

@section('title', $product->title)

@section('content')
@php
    $crumbs = [
        ['Dashboard', route('dashboard')],
        ['Services', route('dashboard.services')],
    ];
    if ($groupSlug && $groupLabel) {
        $crumbs[] = [$groupLabel, route('dashboard.services.browse', $groupSlug)];
    }
    $crumbs[] = [$product->title, null];
    $variants = $product->activeVariants->sortBy('price')->values();
    $defaultVariant = $variants->first();
    $heroUrl = media_url($product->heroMedia, $product->hero_image, 'large')
        ?? media_url($product->heroMedia, $product->hero_image, 'medium');
    $metric = \App\Enums\EngagementMetric::fromProductSlug($product->slug);
    $variantPayload = $variants->map(fn ($v) => $v->storefrontPayload($metric))->values();
    $isDomainProduct = $isDomainProduct ?? false;
@endphp
<x-layout.page
    :title="$product->title"
    width="full"
    :breadcrumb="$crumbs"
>
    <div
        class="space-y-6"
        @if(! $isDomainProduct)
        x-data="{
            variants: @js($variantPayload),
            variantId: {{ (int) ($defaultVariant?->id ?? 0) }},
            units: {{ (int) ($defaultVariant?->isPerUnit() ? $defaultVariant->effectiveMinUnits() : 0) }},
            get selected() {
                return this.variants.find(v => Number(v.id) === Number(this.variantId)) || this.variants[0] || null;
            },
            get isPerUnit() {
                return !!(this.selected && this.selected.per_unit);
            },
            get unitsValid() {
                if (! this.isPerUnit) return true;
                const u = Number(this.units);
                const min = Number(this.selected.min_units || 1);
                const max = Number(this.selected.max_units || 100000);
                return Number.isFinite(u) && u >= min && u <= max;
            },
            get estimatedTotal() {
                if (! this.selected) return 0;
                if (! this.isPerUnit) return Number(this.selected.price) || 0;
                return (Number(this.selected.unit_price) || 0) * (Number(this.units) || 0);
            },
            onVariantChange() {
                if (this.isPerUnit) {
                    this.units = Number(this.selected.min_units || 1);
                }
            },
            checkoutUrl() {
                const base = @js(route('dashboard.services.checkout', $product->slug));
                if (! this.selected) return base;
                let url = base + (base.includes('?') ? '&' : '?') + 'variant=' + this.selected.id;
                if (this.isPerUnit) {
                    url += '&units=' + encodeURIComponent(String(this.units || ''));
                }
                return url;
            }
        }"
        @endif
    >
        @if(session('error'))
            <x-dashboard.alert type="danger">{{ session('error') }}</x-dashboard.alert>
        @endif

        <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
            <x-dashboard.card class="space-y-4 overflow-hidden !p-0">
                @if($heroUrl)
                    <div class="w-full bg-muted">
                        <img
                            src="{{ $heroUrl }}"
                            alt="{{ $product->title }}"
                            class="block w-full h-auto max-h-[28rem] object-contain"
                        >
                    </div>
                @endif
                <div class="space-y-4 p-5 sm:p-6">
                    @if(filled($product->description))
                        <div class="prose prose-sm max-w-none text-text-secondary whitespace-pre-line">{{ $product->description }}</div>
                    @endif
                    <div class="pt-1">
                        @include('partials.catalog.product-demo-actions', [
                            'product' => $product,
                            'modalName' => 'view-demo-dash-'.$product->id,
                        ])
                    </div>
                    @if (filled($product->tutorial_description))
                        <p class="text-sm text-text-secondary whitespace-pre-line">{{ $product->tutorial_description }}</p>
                    @endif
                </div>
            </x-dashboard.card>

            <div class="space-y-6">
                @if($isDomainProduct)
                    @include('dashboard.user.discover._domain-product-search', [
                        'product' => $product,
                        'domainTlds' => $domainTlds ?? [],
                        'domainTldsAdvanced' => $domainTldsAdvanced ?? [],
                    ])
                    @if($groupSlug)
                        <a href="{{ route('dashboard.services.browse', $groupSlug) }}" class="inline-flex text-sm text-text-secondary hover:text-primary">← Back to {{ $groupLabel ?? 'services' }}</a>
                    @endif
                @else
                    <x-dashboard.card class="space-y-4 h-fit">
                        <div>
                            <p class="text-sm font-medium text-text-primary">Choose a plan</p>
                            <p class="mt-1 text-xs text-text-muted">Select a plan. Per-unit plans ask how many units you want before checkout.</p>
                        </div>

                        @if($variants->isNotEmpty())
                            <div class="space-y-2">
                                @foreach($variants as $variant)
                                    <label
                                        class="flex cursor-pointer flex-col gap-1 rounded-xl border px-4 py-3 transition-colors"
                                        :class="Number(variantId) === {{ (int) $variant->id }} ? 'border-primary bg-primary/5' : 'border-border-default hover:border-primary/40'"
                                    >
                                        <span class="flex items-center justify-between gap-3">
                                            <span class="flex items-center gap-3">
                                                <input
                                                    type="radio"
                                                    name="preview_variant_id"
                                                    value="{{ $variant->id }}"
                                                    class="accent-primary"
                                                    x-model.number="variantId"
                                                    @change="onVariantChange()"
                                                    @checked((int) $defaultVariant?->id === (int) $variant->id)
                                                >
                                                <span class="text-sm font-medium text-text-primary">{{ $variant->displayLabel() }}</span>
                                            </span>
                                            <span class="font-semibold text-text-primary text-right">
                                                @if($variant->isPerUnit())
                                                    From ₦{{ number_format($variant->startingFromAmount(), 0) }}
                                                @else
                                                    ₦{{ number_format((float) $variant->price, 0) }}
                                                @endif
                                            </span>
                                        </span>
                                        @if(filled($variant->description))
                                            <span
                                                class="pl-7 text-xs leading-relaxed text-text-secondary"
                                                x-show="Number(variantId) === {{ (int) $variant->id }}"
                                            >{{ $variant->description }}</span>
                                        @endif
                                        @if($variant->isPerUnit())
                                            <span
                                                class="pl-7 text-xs text-text-muted"
                                                x-show="Number(variantId) === {{ (int) $variant->id }}"
                                            >₦{{ number_format((float) $variant->billingUnitPrice(), 2) }} per {{ $variant->resolveUnitLabel($metric) }} · min {{ $variant->effectiveMinUnits() }}</span>
                                        @endif
                                    </label>
                                @endforeach
                            </div>

                            <div class="space-y-2 rounded-xl border border-border-default bg-muted/30 p-4" x-show="isPerUnit" x-cloak>
                                <label class="block text-sm font-medium text-text-secondary">
                                    Enter number of <span x-text="selected ? selected.unit_label_plural : 'units'"></span>
                                </label>
                                <input
                                    type="number"
                                    class="w-full max-w-xs rounded-lg border-border-default bg-elevated text-text-primary text-sm"
                                    x-model.number="units"
                                    :min="selected ? selected.min_units : 1"
                                    :max="selected ? selected.max_units : 100000"
                                >
                                <p class="text-xs text-text-muted">
                                    Min <span x-text="selected ? selected.min_units : ''"></span>
                                    · Max <span x-text="selected ? selected.max_units : ''"></span>
                                    · <span x-text="selected ? ('₦' + Number(selected.unit_price).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 4 }) + ' each') : ''"></span>
                                </p>
                                <p class="text-sm font-semibold text-text-primary" x-show="unitsValid">
                                    Estimated total:
                                    <span x-text="'₦' + Number(estimatedTotal).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>
                                </p>
                                <p class="text-xs text-danger" x-show="!unitsValid" x-cloak>Enter a quantity within the allowed range.</p>
                            </div>
                        @else
                            <p class="text-sm text-text-muted">No plans are available for this product yet.</p>
                        @endif
                    </x-dashboard.card>

                    <x-dashboard.card class="space-y-4 h-fit">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-text-muted">Selected plan</p>
                            <p class="mt-1 text-lg font-semibold text-text-primary" x-text="selected ? selected.label : '—'"></p>
                            <p class="text-3xl font-bold text-primary mt-2">
                                <span x-text="'₦' + Number(estimatedTotal).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>
                            </p>
                            <p class="mt-1 text-xs text-text-muted">From ₦{{ number_format($product->displayPrice(), 0) }}</p>
                        </div>
                        <x-dashboard.button
                            href="#"
                            variant="primary"
                            icon="orders"
                            class="w-full"
                            x-bind:href="checkoutUrl()"
                            x-bind:disabled="!unitsValid"
                            @click="if (!unitsValid) { $event.preventDefault() }"
                        >Continue to checkout</x-dashboard.button>
                        @if($groupSlug)
                            <a href="{{ route('dashboard.services.browse', $groupSlug) }}" class="inline-flex text-sm text-text-secondary hover:text-primary">← Back to {{ $groupLabel ?? 'services' }}</a>
                        @endif
                    </x-dashboard.card>
                @endif
            </div>
        </div>
    </div>
</x-layout.page>
@endsection
