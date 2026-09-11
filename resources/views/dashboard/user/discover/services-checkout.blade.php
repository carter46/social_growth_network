@extends('layouts.dashboard-user')

@section('title', 'Checkout — '.$product->title)

@section('content')
@php
    $hasWallet = (bool) ($wallet ?? null);
    $gatewayOn = (bool) ($gatewayEnabled ?? false);
    $manualBankOn = (bool) ($manualBankTransferEnabled ?? false);
    $defaultMethod = $hasWallet ? 'wallet' : ($gatewayOn ? 'gateway' : ($manualBankOn ? 'manual_bank_transfer' : 'wallet'));
    $variantPayload = $variants->map(fn ($v) => [
        'id' => $v->id,
        'price' => (float) $v->price,
        'label' => $v->displayLabel(),
        'description' => (string) ($v->description ?? ''),
        'is_default' => (bool) $v->is_default,
    ])->values();
    $selectedVariant = $variants->firstWhere('id', $defaultVariantId);
    $checkoutOptions = [
        'defaultVariantId' => $defaultVariantId,
        'basePrice' => $basePrice,
        'paymentMethod' => $defaultMethod,
        'showPlanSummary' => (bool) ($showPlanSummary ?? false),
        'requireDomainChoice' => (bool) ($requireDomainChoice ?? false),
        'isWebsitePackage' => (bool) ($isWebsitePackage ?? false),
        'isDomainProduct' => (bool) ($isDomainProduct ?? false),
        'productSlug' => $product->slug,
        'domainMode' => old('domain_mode', ($requireDomainChoice ?? false) ? 'buy' : 'none'),
        'connectFqdn' => old('domain_fqdn', ''),
        'connectAcknowledged' => (bool) old('domain_connect_acknowledged', false),
        'quoteUrl' => route('dashboard.services.domain-quote'),
        'connectScanUrl' => route('dashboard.services.domain-connect-scan'),
        'domainTlds' => $domainTlds ?? [],
        'domainTldsAdvanced' => $domainTldsAdvanced ?? [],
        'quoteToken' => $quoteToken ?? null,
        'quotedFqdn' => $quotedFqdn ?? null,
        'quotedPrice' => $quotedPrice ?? null,
        'csrfToken' => csrf_token(),
        'oldDomainLabel' => old('domain_label', ''),
        'oldDomainTld' => old('domain_tld', ''),
        'registrantDefaults' => [
            'first_name' => old('registrant.first_name', ''),
            'last_name' => old('registrant.last_name', ''),
            'company' => old('registrant.company', ''),
            'email' => old('registrant.email', auth()->user()->email),
            'phone' => old('registrant.phone', ''),
            'address' => old('registrant.address', ''),
            'city' => old('registrant.city', ''),
            'state' => old('registrant.state', ''),
            'zip' => old('registrant.zip', ''),
            'country' => old('registrant.country', 'NG'),
        ],
        'walletBalance' => $hasWallet ? (float) $wallet->balance : 0,
        'hasWallet' => $hasWallet,
        'gatewayEnabled' => $gatewayOn,
        'manualBankTransferEnabled' => $manualBankOn,
    ];
@endphp
<x-layout.page
    title="Checkout"
    width="default"
    :breadcrumb="[
        ['Dashboard', route('dashboard')],
        ['Services', route('dashboard.services')],
        [$product->title, route('dashboard.services.product', $product->slug)],
        ['Checkout', null],
    ]"
>
    <x-dashboard.card class="w-full space-y-5">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-primary mb-1">Platform service</p>
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
                or ask an admin to enable card/transfer or bank transfer checkout.
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
                @if ($renewTool ?? null)
                    <input type="hidden" name="renew_user_tool_id" value="{{ $renewTool->id }}">
                    <x-dashboard.alert type="info">
                        Renewing <strong>{{ $renewTool->resolvedDisplayName() }}</strong>. This extends the same tool — it will not create a second instance.
                    </x-dashboard.alert>
                @endif

                @if($isDomainProduct ?? false)
                    <input type="hidden" name="domain_quote_token" x-bind:value="domainQuoteToken">
                    <input type="hidden" name="domain_fqdn" x-bind:value="domainFqdn">
                    <input type="hidden" name="quantity" value="1">

                    <div class="rounded-xl border border-border-default bg-muted/20 px-4 py-3 space-y-1">
                        <p class="text-xs font-semibold uppercase tracking-wider text-text-muted">Domain</p>
                        <p class="text-lg font-semibold text-text-primary" x-text="domainFqdn || '—'"></p>
                        <p class="text-sm text-text-secondary">
                            Registration · <span x-text="'₦' + retailFormatted"></span>
                            <span x-show="domainPremium" x-cloak class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Premium</span>
                        </p>
                    </div>

                    @include('dashboard.user.discover._domain-registrant-fields')
                @else
                    @if(($showPlanSummary ?? false) && $selectedVariant)
                        <input type="hidden" name="variant_id" value="{{ $selectedVariant->id }}">
                        <div class="rounded-xl border border-border-default bg-muted/20 px-4 py-3 space-y-1">
                            <p class="text-xs font-semibold uppercase tracking-wider text-text-muted">Selected plan</p>
                            <p class="text-lg font-semibold text-text-primary">{{ $selectedVariant->displayLabel() }}</p>
                            <p class="text-2xl font-bold text-primary">₦{{ number_format((float) $selectedVariant->price, 0) }}</p>
                            @if(filled($selectedVariant->description))
                                <p class="text-sm text-text-secondary">{{ $selectedVariant->description }}</p>
                            @endif
                        </div>
                    @elseif($variants->isNotEmpty())
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-2">Plan / variant</label>
                            <div class="space-y-2">
                                @foreach($variants as $variant)
                                    <label
                                        class="flex cursor-pointer flex-col gap-1 rounded-xl border border-border-default px-4 py-3 hover:border-primary/40"
                                        :class="Number(variantId) === {{ (int) $variant->id }} ? 'border-primary bg-primary/5' : ''"
                                    >
                                        <span class="flex items-center justify-between gap-3">
                                            <span class="flex items-center gap-3">
                                                <input
                                                    type="radio"
                                                    name="variant_id"
                                                    value="{{ $variant->id }}"
                                                    @checked((int) $defaultVariantId === (int) $variant->id)
                                                    x-model.number="variantId"
                                                >
                                                <span class="text-sm text-text-primary">{{ $variant->displayLabel() }}</span>
                                            </span>
                                            <span class="font-semibold text-text-primary">₦{{ number_format($variant->price, 2) }}</span>
                                        </span>
                                        @if(filled($variant->description))
                                            <span class="pl-7 text-xs leading-relaxed text-text-secondary">{{ $variant->description }}</span>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($isWebsitePackage ?? false)
                        <input type="hidden" name="quantity" value="1">
                    @else
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-2">Quantity</label>
                            <input type="number" name="quantity" min="1" max="100" x-model.number="qty" class="w-32 rounded-lg border-border-default bg-elevated text-text-primary text-sm">
                        </div>
                    @endif

                    @if($product->is_campaign ?? false)
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-2">Post / video URL <span class="text-danger">*</span></label>
                            <input
                                type="url"
                                name="target_url"
                                value="{{ old('target_url') }}"
                                required
                                placeholder="https://…"
                                class="w-full rounded-lg border-border-default bg-elevated text-text-primary text-sm"
                            >
                            <p class="mt-1 text-xs text-text-muted">Must be a public post or video URL for this platform (not a profile page).</p>
                            @error('target_url')
                                <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    @if($requireDomainChoice ?? false)
                        <div class="space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-text-secondary mb-2">Domain <span class="text-danger">*</span></label>
                                <div class="flex flex-wrap gap-4">
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="radio" name="domain_mode" value="buy" x-model="domainMode" @change="onDomainModeChange()" class="accent-primary">
                                        Buy a new domain
                                    </label>
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="radio" name="domain_mode" value="connect" x-model="domainMode" @change="onDomainModeChange()" class="accent-primary">
                                        Connect existing domain
                                    </label>
                                </div>
                            </div>

                            <div x-show="domainMode === 'buy'" x-cloak class="space-y-3">
                                    <div class="grid gap-3 sm:grid-cols-[1fr_minmax(9rem,14rem)]">
                                        <div>
                                            <label class="mb-1 block text-xs text-text-muted">Domain name</label>
                                            <input
                                                type="text"
                                                name="domain_label"
                                                x-model="domainLabel"
                                                @input="onDomainLabelInput($event)"
                                                placeholder="mysite"
                                                autocomplete="off"
                                                autocapitalize="off"
                                                spellcheck="false"
                                                :class="domainLabelError ? 'border-danger' : 'border-border-default'"
                                                class="w-full rounded-lg bg-elevated text-text-primary text-sm"
                                            >
                                            <p x-show="domainLabelError" x-cloak class="mt-1 text-xs text-danger" x-text="domainLabelError"></p>
                                        </div>
                                        <div>
                                            <input type="hidden" name="domain_tld" x-bind:value="domainTld">
                                            @include('dashboard.user.discover._domain-extension-picker')
                                        </div>
                                    </div>

                                    <div class="space-y-2">
                                        <input type="hidden" name="domain_quote_token" x-bind:value="domainQuoteToken">
                                        <x-dashboard.button
                                            type="button"
                                            variant="primary"
                                            size="sm"
                                            x-on:click="checkDomain()"
                                            x-bind:disabled="domainChecking || !canCheckDomain"
                                        >
                                            <span x-text="domainChecking ? 'Checking…' : 'Check availability'">Check availability</span>
                                        </x-dashboard.button>
                                        @include('dashboard.user.discover._domain-search-results')
                                    </div>

                                    <div x-show="domainAvailable" x-cloak>
                                        @include('dashboard.user.discover._domain-registrant-fields')
                                    </div>
                                </div>

                            <div x-show="domainMode === 'connect'" x-cloak class="space-y-3">
                                    <input type="hidden" name="domain_fqdn" x-bind:value="connectFqdn">
                                    <input type="hidden" name="domain_connect_acknowledged" :value="connectAcknowledged ? '1' : '0'">

                                    <div>
                                        <label class="mb-1 block text-xs text-text-muted">Existing domain</label>
                                        <input
                                            type="text"
                                            x-model="connectFqdnInput"
                                            @input="onConnectFqdnInput()"
                                            placeholder="example.com"
                                            autocomplete="off"
                                            autocapitalize="off"
                                            spellcheck="false"
                                            x-bind:disabled="connectScanning"
                                            :class="connectError && !connectScanned ? 'border-danger' : 'border-border-default'"
                                            class="w-full rounded-lg bg-elevated text-text-primary text-sm"
                                        >
                                    </div>

                                    <x-dashboard.button
                                        type="button"
                                        variant="primary"
                                        size="sm"
                                        x-on:click="scanConnectDomain()"
                                        x-bind:disabled="connectScanning || !(connectFqdnInput || '').trim()"
                                    >
                                        <span x-text="connectScanning ? 'Checking domain…' : 'Check Domain'">Check Domain</span>
                                    </x-dashboard.button>

                                    <p x-show="connectError" x-cloak class="text-sm text-danger" x-text="connectError"></p>

                                    <div x-show="connectScanned" x-cloak class="space-y-3 rounded-xl border border-border-default bg-muted/20 px-4 py-3">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wider text-text-muted">Domain found</p>
                                            <p class="mt-1 text-lg font-semibold text-text-primary" x-text="connectFqdn"></p>
                                        </div>
                                        <div>
                                            <p class="text-xs font-medium text-text-secondary">Current nameservers</p>
                                            <ul class="mt-1 space-y-1 text-sm text-text-primary">
                                                <template x-for="(ns, index) in connectNameservers" :key="'scan-'+index+'-'+ns">
                                                    <li x-text="ns"></li>
                                                </template>
                                            </ul>
                                        </div>
                                        <div>
                                            <p class="text-xs font-medium text-text-secondary">Required nameservers</p>
                                            <ul class="mt-1 space-y-1 text-sm text-primary">
                                                <template x-for="(ns, index) in connectRequiredNameservers" :key="'req-'+index+'-'+ns">
                                                    <li x-text="ns"></li>
                                                </template>
                                            </ul>
                                        </div>
                                        <p class="text-sm text-text-secondary">
                                            To use this domain with your website, change its nameservers at your current registrar to the required values above.
                                            Verification happens after purchase in My Domains — you can continue to payment now.
                                        </p>
                                        <label class="flex items-start gap-2 text-sm text-text-primary">
                                            <input type="checkbox" class="mt-1 accent-primary" x-model="connectAcknowledged">
                                            <span>I understand I must point this domain’s nameservers to the required values above.</span>
                                        </label>
                                    </div>
                                </div>
                        </div>
                    @endif
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
                                    <span class="block text-xs text-text-muted">Available: ₦{{ number_format((float) $wallet->balance, 2) }}</span>
                                </span>
                            </label>
                        @endif
                        @if($gatewayOn)
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-border-default px-4 py-3"
                                   :class="paymentMethod === 'gateway' ? 'border-primary bg-primary/5' : 'hover:border-primary/40'">
                                <input type="radio" name="payment_method" value="gateway" x-model="paymentMethod" class="mt-1 accent-primary" @checked($defaultMethod === 'gateway')>
                                <span>
                                    <span class="block text-sm font-medium text-text-primary">Pay directly</span>
                                    <span class="block text-xs text-text-muted">Card or bank transfer via payment gateway</span>
                                </span>
                            </label>
                        @endif
                        @if($manualBankOn)
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-border-default px-4 py-3"
                                   :class="paymentMethod === 'manual_bank_transfer' ? 'border-primary bg-primary/5' : 'hover:border-primary/40'">
                                <input type="radio" name="payment_method" value="manual_bank_transfer" x-model="paymentMethod" class="mt-1 accent-primary" @checked($defaultMethod === 'manual_bank_transfer')>
                                <span>
                                    <span class="block text-sm font-medium text-text-primary">Bank transfer</span>
                                    <span class="block text-xs text-text-muted">Pay directly to our company account — we confirm manually</span>
                                </span>
                            </label>
                        @endif
                    </div>
                    <p
                        x-show="paymentMethod === 'wallet' && walletShortfall > 0"
                        x-cloak
                        class="mt-2 text-xs text-danger"
                        x-text="'Insufficient balance. You need ₦' + walletShortfallFormatted + ' more to pay from wallet.'"
                    ></p>
                </div>

                <p x-show="submitError" x-cloak class="text-sm text-danger" x-text="submitError"></p>

                <div class="flex items-center justify-between border-t border-border-default pt-4">
                    <span class="text-text-secondary">Total</span>
                    <span class="text-2xl font-bold text-primary" x-text="'₦' + totalFormatted"></span>
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
