@extends('layouts.dashboard-user')

@section('title', 'Billing | '.$product->title)

@section('content')
@php
    $hasWallet = (bool) ($wallet ?? null);
    $gatewayOn = (bool) ($gatewayEnabled ?? false);
    $manualBankOn = (bool) ($manualBankTransferEnabled ?? false);
    $defaultMethod = $hasWallet ? 'wallet' : ($gatewayOn ? 'gateway' : ($manualBankOn ? 'manual_bank_transfer' : 'wallet'));
    $taskProfile = $taskProfile ?? $product->taskProfile();
    $campaignBrief = $campaignBrief ?? [];
    $variantPayload = $variants->map(function ($v) use ($taskProfile) {
        return $v->storefrontPayload($taskProfile->legacyMetric()) + ['is_default' => (bool) $v->is_default];
    })->values();
    $selectedVariant = $variants->firstWhere('id', $defaultVariantId);
    $selectedUnits = (int) ($selectedUnits ?? 1);
    $engagementMetric = $engagementMetric ?? $taskProfile->legacyMetric();
    $destinationLabel = match (true) {
        $taskProfile->targetIsChannel() => 'Channel',
        $engagementMetric === null => 'Campaign link',
        default => 'Video',
    };
    $packageQuote = $packageQuote ?? null;
    $companionQty = $packageQuote
        ? array_diff_key($packageQuote['task_quantities'], array_flip(array_filter([$packageQuote['main_task']])))
        : [];
    $companionInputs = [];
    foreach ($packageQuote['task_limits'] ?? [] as $task => $range) {
        $companionInputs[$task] = (int) ($companionQty[$task] ?? 0);
    }
    $changeLinkQuery = array_filter([
        'slug' => $product->slug,
        'units' => (int) $selectedUnits,
        'qty' => $companionInputs ?: null,
        'target_url' => $targetUrl ?? null,
        'channel_url' => $campaignBrief['channel_url'] ?? null,
        'comment_instructions' => $campaignBrief['comment_instructions'] ?? null,
    ]);
    $checkoutOptions = [
        'defaultVariantId' => $defaultVariantId,
        'selectedUnits' => $selectedUnits,
        'packageTotal' => $packageQuote['total'] ?? null,
        'basePrice' => $basePrice,
        'paymentMethod' => $defaultMethod,
        'showPlanSummary' => (bool) ($showPlanSummary ?? false),
        'productSlug' => $product->slug,
        'urlPreviewUrl' => route('dashboard.services.url-preview'),
        'isCampaign' => $product->isCampaignProduct(),
        'initialTargetUrl' => '',
        'csrfToken' => csrf_token(),
        'walletBalance' => $hasWallet ? (float) $wallet->balance : 0,
        'hasWallet' => $hasWallet,
        'gatewayEnabled' => $gatewayOn,
        'manualBankTransferEnabled' => $manualBankOn,
        'productPageUrl' => route('dashboard.services.product', $product->slug),
    ];
@endphp
<x-layout.page
    title="Billing"
    width="default"
    :breadcrumb="[
        ['Dashboard', route('dashboard')],
        ['Services', route('dashboard.services')],
        [$product->title, route('dashboard.services.product', $product->slug)],
        ['Billing', null],
    ]"
>
    <x-dashboard.card class="w-full space-y-5">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-primary mb-1">Service</p>
            <h2 class="text-xl font-semibold text-text-primary">{{ $product->title }}</h2>
            @if(filled($product->description))
                <p class="text-sm text-text-secondary mt-1">{{ \Illuminate\Support\Str::limit(strip_tags((string) $product->description), 200) }}</p>
            @endif
        </div>

        @if(session('error'))
            <x-dashboard.alert type="danger">{{ session('error') }}</x-dashboard.alert>
        @endif

        @if(! auth()->user()->hasVerifiedEmail())
            <x-dashboard.alert type="warning">
                <a href="{{ route('verification.notice') }}" class="underline font-medium">Verify your email</a> before purchasing.
            </x-dashboard.alert>
        @elseif(! $hasWallet && ! $gatewayOn && ! $manualBankOn)
            <x-dashboard.alert type="warning">
                No payment method is available. <a href="{{ route('dashboard.wallet') }}" class="underline font-medium">Create a wallet</a>
                to pay, or contact support for help.
            </x-dashboard.alert>
        @else
            <form
                method="POST"
                action="{{ route('dashboard.services.purchase', $product->slug) }}"
                class="space-y-5"
                data-no-page-loader
                x-data="platformCheckout(@js($variantPayload), @js($checkoutOptions))"
                @submit.prevent="handleCheckoutSubmit($event)"
            >
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

                    @if(($showPlanSummary ?? false) && $selectedVariant)
                        <input type="hidden" name="variant_id" value="{{ $selectedVariant->id }}">
                        <input type="hidden" name="quantity" value="{{ (int) $selectedUnits }}">
                        @foreach($companionInputs as $task => $qty)
                            <input type="hidden" name="qty[{{ $task }}]" value="{{ $qty }}">
                        @endforeach
                        <div class="rounded-xl border border-border-default bg-muted/20 px-4 py-3 space-y-1">
                            <p class="text-xs font-semibold uppercase tracking-wider text-text-muted">Your order</p>
                            @if($taskProfile->isMultiTask() && $packageQuote)
                                @foreach($taskProfile->tasks() as $task)
                                    @if(($packageQuote['task_quantities'][$task->value] ?? 0) > 0)
                                        <p class="text-sm font-semibold text-text-primary">
                                            {{ number_format($packageQuote['task_quantities'][$task->value]) }} {{ strtolower($taskProfile->quantityLabel($task)) }}
                                        </p>
                                    @endif
                                @endforeach
                                @if($packageQuote['extra_units'] > 0)
                                    <p class="text-xs text-text-muted">Includes {{ number_format($packageQuote['extra_units']) }} extra units above the package minimums.</p>
                                @endif
                            @else
                                <p class="text-lg font-semibold text-text-primary">
                                    {{ number_format((int) $selectedUnits) }} {{ $selectedVariant->resolveUnitLabelPlural($engagementMetric) }}
                                </p>
                            @endif
                            <p class="text-sm text-text-secondary">{{ $selectedVariant->pricingLabel($engagementMetric) }}</p>
                            <p class="text-2xl font-bold text-primary">
                                {{ money((float) ($packageQuote['total'] ?? bcmul($selectedVariant->billingUnitPrice(), (string) (int) $selectedUnits, 2)), 2) }}
                            </p>
                            <p class="pt-1 text-xs text-text-muted">
                                <a href="{{ route('dashboard.services.product', $changeLinkQuery) }}" class="underline hover:text-primary">Change quantity</a>
                            </p>
                        </div>
                    @else
                        <input type="hidden" name="quantity" value="1">
                    @endif

                    @if(filled($targetUrl ?? null))
                        <input type="hidden" name="target_url" value="{{ $targetUrl }}">
                        <div class="rounded-xl border border-border-default bg-muted/20 px-4 py-3 space-y-2">
                            <p class="text-xs font-semibold uppercase tracking-wider text-text-muted">{{ $destinationLabel }}</p>
                            @include('dashboard.user.discover._link-preview-card', [
                                'url' => $targetUrl,
                                'video' => $linkPreview['video'] ?? null,
                                'channel' => $linkPreview['target_channel'] ?? null,
                            ])
                            @error('target_url')
                                <p class="text-xs text-danger">{{ $message }}</p>
                            @enderror
                            <p class="pt-1 text-xs text-text-muted">
                                <a href="{{ route('dashboard.services.product', $changeLinkQuery) }}" class="underline hover:text-primary">Change link</a>
                            </p>
                        </div>
                    @endif

                    @if($taskProfile->isMultiTask() && filled($targetUrl ?? null))
                        <div class="rounded-xl border border-border-default bg-muted/20 px-4 py-3 space-y-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-text-muted">Campaign steps</p>
                                <p class="text-sm text-text-primary">{{ $taskProfile->sequenceLabel() }}</p>
                                <p class="text-xs text-text-muted">Steps run in this order. Each step stops once its target is reached.</p>
                            </div>
                            @if(filled($campaignBrief['channel_url'] ?? null))
                                <input type="hidden" name="channel_url" value="{{ $campaignBrief['channel_url'] }}">
                                <div>
                                    <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-text-muted">Channel</p>
                                    @include('dashboard.user.discover._link-preview-card', [
                                        'url' => $campaignBrief['channel_url'],
                                        'channel' => $linkPreview['channel'] ?? null,
                                    ])
                                    @error('channel_url')
                                        <p class="text-xs text-danger">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endif
                            @if(filled($campaignBrief['comment_instructions'] ?? null))
                                <input type="hidden" name="comment_instructions" value="{{ $campaignBrief['comment_instructions'] }}">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wider text-text-muted">Comment instructions</p>
                                    <p class="text-sm text-text-primary whitespace-pre-line break-words">{{ $campaignBrief['comment_instructions'] }}</p>
                                    @error('comment_instructions')
                                        <p class="text-xs text-danger">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endif
                            @if(! ($campaignBrief['match_verified'] ?? false) && filled($campaignBrief['channel_url'] ?? null))
                                <p class="text-xs text-text-muted">We couldn't confirm the video is on this channel right now. Please double-check both links before you pay.</p>
                            @endif
                        </div>
                    @endif

                <div>
                    <label class="block text-sm font-medium text-text-secondary mb-2">Payment method</label>
                    <div class="space-y-2">
                        @if($hasWallet)
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-border-default px-4 py-3"
                                   :class="paymentMethod === 'wallet' ? 'border-primary bg-primary/5' : 'hover:border-primary/40'">
                                <input type="radio" name="payment_method" value="wallet" x-model="paymentMethod" class="mt-1 accent-primary" @checked($defaultMethod === 'wallet')>
                                <span>
                                    <span class="block text-sm font-medium text-text-primary">Wallet balance</span>
                                    <span class="block text-xs text-text-muted">Available: {{ money((float) $wallet->balance, 2) }}</span>
                                </span>
                            </label>
                        @endif
                        @if($gatewayOn)
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-border-default px-4 py-3"
                                   :class="paymentMethod === 'gateway' ? 'border-primary bg-primary/5' : 'hover:border-primary/40'">
                                <input type="radio" name="payment_method" value="gateway" x-model="paymentMethod" class="mt-1 accent-primary" @checked($defaultMethod === 'gateway')>
                                <span>
                                    <span class="block text-sm font-medium text-text-primary">Pay directly</span>
                                    <span class="block text-xs text-text-muted">Card, bank, mobile money and other methods on a secure payment page</span>
                                </span>
                            </label>
                            <div x-show="paymentMethod === 'gateway'" x-cloak class="ml-7">
                                @include('partials.payments.gateway-choice', ['gateways' => $gateways ?? [], 'selected' => old('gateway', $defaultGateway ?? null)])
                            </div>
                        @endif
                        @if($manualBankOn)
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-border-default px-4 py-3"
                                   :class="paymentMethod === 'manual_bank_transfer' ? 'border-primary bg-primary/5' : 'hover:border-primary/40'">
                                <input type="radio" name="payment_method" value="manual_bank_transfer" x-model="paymentMethod" class="mt-1 accent-primary" @checked($defaultMethod === 'manual_bank_transfer')>
                                <span>
                                    <span class="block text-sm font-medium text-text-primary">Bank transfer</span>
                                    <span class="block text-xs text-text-muted">Pay directly to our company account. We confirm it manually.</span>
                                </span>
                            </label>
                        @endif
                    </div>
                    <p
                        x-show="paymentMethod === 'wallet' && walletShortfall > 0"
                        x-cloak
                        class="mt-2 text-xs text-danger"
                        x-text="'Insufficient balance. You need ' + walletShortfallFormatted + ' more to pay from wallet.'"
                    ></p>
                </div>

                <p x-show="submitError" x-cloak class="text-sm text-danger" x-text="submitError"></p>

                <div class="flex items-center justify-between border-t border-border-default pt-4">
                    <span class="text-text-secondary">Total</span>
                    <span class="text-2xl font-bold text-primary" x-text="totalFormatted"></span>
                </div>

                <x-dashboard.button type="submit" icon="orders" class="w-full" x-bind:disabled="!canSubmit">
                    <span x-text="paymentMethod === 'wallet' ? 'Pay from wallet' : 'Continue to payment'">Continue to payment</span>
                </x-dashboard.button>
            </form>
        @endif

        <a href="{{ route('dashboard.services.product', $product->slug) }}" class="inline-flex text-sm text-text-secondary hover:text-primary">
            ← Back to product
        </a>
    </x-dashboard.card>
</x-layout.page>
@endsection
