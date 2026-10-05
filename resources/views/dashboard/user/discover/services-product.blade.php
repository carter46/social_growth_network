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
    $pricingVariant = $product->pricingVariant();
    $heroUrl = media_url($product->heroMedia, $product->hero_image, 'large')
        ?? media_url($product->heroMedia, $product->hero_image, 'medium');
    $metric = \App\Enums\EngagementMetric::fromProductSlug($product->slug);
    $pricing = $product->isPurchasable() ? $pricingVariant->storefrontPayload($metric) : null;
    $isDomainProduct = $isDomainProduct ?? false;
    $needsTargetUrl = ! $isDomainProduct && $product->requiresTargetUrl();
    $initialTargetUrl = (string) old('target_url', request()->query('target_url', ''));
    $initialUnits = (int) request()->query('units', $pricing['min_units'] ?? 0);
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
            pricing: @js($pricing),
            units: {{ $initialUnits }},
            needsTargetUrl: @js($needsTargetUrl),
            targetUrl: @js($initialTargetUrl),
            get targetUrlValid() {
                if (! this.needsTargetUrl) return true;
                return /^https?:\/\/[^\s.]+\.[^\s]+$/i.test(String(this.targetUrl || '').trim());
            },
            get canContinue() {
                return !!this.pricing && this.unitsValid && this.targetUrlValid;
            },
            get unitsValid() {
                if (! this.pricing) return false;
                const u = Number(this.units);
                return Number.isInteger(u) && u >= Number(this.pricing.min_units) && u <= Number(this.pricing.max_units);
            },
            get estimatedTotal() {
                if (! this.pricing) return 0;
                return Math.round((Number(this.pricing.unit_price) || 0) * (Number(this.units) || 0) * 100) / 100;
            },
            checkoutUrl() {
                const base = @js(route('dashboard.services.checkout', $product->slug));
                if (! this.pricing) return base;
                let url = base + (base.includes('?') ? '&' : '?') + 'units=' + encodeURIComponent(String(this.units || ''));
                if (this.needsTargetUrl) {
                    url += '&target_url=' + encodeURIComponent(String(this.targetUrl || '').trim());
                }
                return url;
            }
        }"
        @target-url-changed="targetUrl = $event.detail"
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
                        @if($pricing)
                            <div>
                                <p class="text-lg font-semibold text-text-primary">{{ $pricing['pricing_label'] }}</p>
                                <p class="mt-1 text-xs text-text-muted">
                                    Minimum: {{ number_format($pricing['min_units']) }}
                                    · Maximum: {{ number_format($pricing['max_units']) }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <label for="units-input" class="block text-sm font-medium text-text-secondary">
                                    How many {{ $pricing['unit_label_plural'] }} do you need?
                                </label>
                                <input
                                    id="units-input"
                                    type="number"
                                    step="1"
                                    class="w-full max-w-xs rounded-lg border-border-default bg-elevated text-text-primary text-sm"
                                    x-model.number="units"
                                    min="{{ $pricing['min_units'] }}"
                                    max="{{ $pricing['max_units'] }}"
                                >
                                <p class="text-xs text-danger" x-show="!unitsValid" x-cloak>
                                    Enter a whole number from {{ number_format($pricing['min_units']) }} to {{ number_format($pricing['max_units']) }}.
                                </p>
                            </div>
                        @else
                            <p class="text-sm text-text-muted">Pricing for this product is not available yet.</p>
                        @endif
                    </x-dashboard.card>

                    @if($needsTargetUrl)
                        <x-dashboard.card class="space-y-2 h-fit">
                            @include('dashboard.user.discover._campaign-link-field', [
                                'product' => $product,
                                'engagementMetric' => $metric,
                                'initialTargetUrl' => $initialTargetUrl,
                            ])
                            @error('target_url')
                                <p class="text-xs text-danger">{{ $message }}</p>
                            @enderror
                        </x-dashboard.card>
                    @endif

                    <x-dashboard.card class="space-y-4 h-fit">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-text-muted">Total</p>
                            @if($pricing)
                                <p class="mt-1 text-sm text-text-secondary" x-show="unitsValid">
                                    <span x-text="Number(units).toLocaleString()"></span> {{ $pricing['unit_label_plural'] }}
                                </p>
                            @endif
                            <p class="text-3xl font-bold text-primary mt-2">
                                <span x-text="'₦' + Number(unitsValid ? estimatedTotal : 0).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>
                            </p>
                        </div>
                        <x-dashboard.button
                            href="#"
                            variant="primary"
                            icon="orders"
                            class="w-full"
                            x-bind:href="checkoutUrl()"
                            x-bind:disabled="!canContinue"
                            @click="if (!canContinue) { $event.preventDefault() }"
                        >Continue to checkout</x-dashboard.button>
                        @if($needsTargetUrl)
                            <p class="text-xs text-text-muted" x-show="!targetUrlValid" x-cloak>Enter your link above to continue.</p>
                        @endif
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
