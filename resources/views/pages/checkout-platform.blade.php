@extends('layouts.marketing')

@section('title', 'Complete payment | '.$product->title)

@section('content')
@php
    $variantPayload = $variants->map(fn ($v) => [
        'id' => $v->id,
        'price' => (float) $v->price,
        'label' => $v->displayLabel(),
        'is_default' => (bool) $v->is_default,
    ])->values();
@endphp
<section class="py-14 sm:py-20">
    <div class="max-w-form mx-auto px-5 sm:px-6">
        <h1 class="text-3xl font-bold font-display mb-2">Complete payment</h1>
        <p class="text-slate-400 mb-8">{{ $product->title }}</p>

        <form
            method="POST"
            action="{{ route('checkout.platform.store', $product->slug) }}"
            class="glassmorphism rounded-2xl p-6 space-y-6"
            x-data="platformCheckout(@js($variantPayload), @js(['defaultVariantId' => $defaultVariantId, 'basePrice' => $basePrice]))"
        >
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

            @auth
                @if($variants->isNotEmpty())
                    <div>
                        <label class="block text-sm font-semibold mb-3">Plan / variant</label>
                        <div class="space-y-2">
                            @foreach($variants as $variant)
                                <label class="flex items-center justify-between gap-3 rounded-xl border border-white/10 px-4 py-3 cursor-pointer hover:border-accent/40">
                                    <span class="flex items-center gap-3">
                                        <input
                                            type="radio"
                                            name="variant_id"
                                            value="{{ $variant->id }}"
                                            @checked((int) $defaultVariantId === (int) $variant->id)
                                            x-model.number="variantId"
                                        >
                                        <span>{{ $variant->displayLabel() }}</span>
                                    </span>
                                    <span class="font-bold">₦{{ number_format($variant->price, 2) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div>
                    <label class="block text-sm font-semibold mb-2">Quantity</label>
                    <input type="number" name="quantity" min="1" max="100" x-model.number="qty" class="w-32 rounded-xl bg-slate-900/60 border-white/10">
                </div>

                @if($showDomainOptions)
                    <div>
                        <label class="block text-sm font-semibold mb-2">Domain (optional)</label>
                        <select name="domain_mode" x-model="domainMode" class="w-full rounded-xl bg-slate-900/60 border-white/10 mb-3">
                            <option value="none">No domain needed</option>
                            <option value="buy">Buy a domain</option>
                            <option value="connect">Connect existing domain</option>
                        </select>
                        <input type="text" name="domain_name" x-show="domainMode !== 'none'" placeholder="example.com" class="w-full rounded-xl bg-slate-900/60 border-white/10">
                        <p class="text-xs text-slate-500 mt-2">Search availability and live pricing are provided during payment.</p>
                    </div>
                @else
                    <input type="hidden" name="domain_mode" value="none">
                @endif

                @if($walletBalance !== null)
                    <p class="text-sm text-slate-400">Wallet balance: <span class="text-white font-semibold">₦{{ number_format($walletBalance, 2) }}</span></p>
                @endif

                <div class="flex items-center justify-between border-t border-white/10 pt-4">
                    <span class="text-slate-400">Total</span>
                    <span class="text-2xl font-bold" x-text="'₦' + totalFormatted"></span>
                </div>

                <button type="submit" class="w-full py-3 rounded-xl bg-primary hover:bg-primary-hover font-bold">Pay from wallet</button>
            @else
                <p class="text-slate-400">Please <a href="{{ route('login') }}" class="text-accent underline">log in</a> to pay.</p>
            @endauth
        </form>
    </div>
</section>
@endsection
