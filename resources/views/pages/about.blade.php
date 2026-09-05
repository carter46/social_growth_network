@extends('layouts.marketing')

@section('title', 'About Us')

@section('content')
    @php $brandName = $siteName ?? config('app.name', 'Social Growth Network'); @endphp

    <section class="relative min-h-[28rem] flex items-center justify-center overflow-hidden py-20">
        <div class="absolute inset-0 z-0 bg-gradient-to-b from-primary/20 via-surface to-surface"></div>
        <div class="relative z-10 text-center px-6 max-w-3xl">
            <p class="text-accent font-bold tracking-[0.2em] uppercase text-xs mb-6">About {{ $brandName }}</p>
            <h1 class="text-4xl md:text-5xl font-display font-extrabold mb-6 text-white leading-tight">
                Social media growth, built for creators and brands
            </h1>
            <p class="text-lg text-slate-400 leading-relaxed">
                We offer clear Instagram, TikTok, YouTube, Twitter/X, and Facebook growth packs with secure checkout — so you can focus on building your presence.
            </p>
        </div>
    </section>

    <section class="py-20 max-w-marketing mx-auto px-5 sm:px-6">
        <div class="grid lg:grid-cols-2 gap-16 items-start">
            <div>
                <h2 class="text-accent font-bold text-xs uppercase tracking-widest mb-4">Our mission</h2>
                <h3 class="text-3xl font-display font-bold mb-6 text-white">Make social growth simple and transparent</h3>
                <p class="text-slate-400 leading-relaxed mb-6">
                    {{ $brandName }} sells platform-operated social media services. You browse packs, pick a plan, and check out with wallet, card, or bank transfer when those options are enabled.
                </p>
                <p class="text-slate-400 leading-relaxed">
                    Purchased services appear in your dashboard under My Tools and My Orders, with support available when you need help.
                </p>
            </div>
            <div class="space-y-4">
                <div class="rounded-2xl border border-white/10 bg-slate-900/40 p-6">
                    <h4 class="font-bold text-white mb-2">Clear deliverables</h4>
                    <p class="text-sm text-slate-400">Every pack lists what you get before you pay — no marketplace listings or peer escrow.</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-slate-900/40 p-6">
                    <h4 class="font-bold text-white mb-2">Secure checkout</h4>
                    <p class="text-sm text-slate-400">Pay with your Naira wallet, Monnify gateway, or manual bank transfer when enabled.</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-slate-900/40 p-6">
                    <h4 class="font-bold text-white mb-2">Real support</h4>
                    <p class="text-sm text-slate-400">Open a ticket from your dashboard or use Contact — we help with orders, wallet, and account questions.</p>
                </div>
            </div>
        </div>
    </section>
@endsection
