@extends('layouts.marketing')

@section('title', 'About Us')

@section('content')
    @php $brandName = $siteName ?? config('app.name', 'Social Growth Network'); @endphp

    <section class="relative min-h-[22rem] sm:min-h-[28rem] flex items-center justify-center overflow-hidden py-20 bg-navy-dark">
        <div class="absolute inset-0 z-0 bg-gradient-to-br from-primary/30 via-slate-950 to-slate-900"></div>
        <div class="relative z-10 text-center px-6 max-w-3xl">
            <p class="text-blue-300 font-bold tracking-[0.2em] uppercase text-xs mb-6">About {{ $brandName }}</p>
            <h1 class="text-4xl md:text-5xl font-display font-extrabold mb-6 text-white leading-tight">
                Digital campaigns, built for creators and brands
            </h1>
            <p class="text-lg text-slate-300 leading-relaxed">
                {{ $brandName }} helps you launch predefined growth and engagement packages with clear pricing and secure checkout.
            </p>
        </div>
    </section>

    <section class="py-20 max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-16 items-start">
            <div>
                <h2 class="text-primary font-bold text-xs uppercase tracking-widest mb-4">Our mission</h2>
                <h3 class="text-3xl font-display font-bold mb-6 text-slate-900">Make campaign growth simple and transparent</h3>
                <p class="text-slate-600 leading-relaxed mb-6">
                    {{ $brandName }} is a digital campaign marketplace. You browse packages, pick a plan, and check out with wallet, card, or bank transfer when those options are enabled.
                </p>
                <p class="text-slate-600 leading-relaxed">
                    Purchased campaigns appear in your dashboard, with support available when you need help. Agents can earn by completing verified digital tasks.
                </p>
            </div>
            <div class="space-y-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h4 class="font-bold text-slate-900 mb-2">Clear deliverables</h4>
                    <p class="text-sm text-slate-600">Every pack lists what you get before you pay — fixed packages, no haggling.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h4 class="font-bold text-slate-900 mb-2">Secure checkout</h4>
                    <p class="text-sm text-slate-600">Pay with your Naira wallet, gateway, or manual bank transfer when enabled.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h4 class="font-bold text-slate-900 mb-2">Real support</h4>
                    <p class="text-sm text-slate-600">Open a ticket from your dashboard or use Contact — we help with orders, wallet, and account questions.</p>
                </div>
            </div>
        </div>
    </section>
@endsection
