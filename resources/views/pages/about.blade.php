@extends('layouts.marketing')

@section('title', 'About Us')

@section('content')
@php
    $brandName = $siteName ?? config('app.name', 'Social Growth Network');
    $imgTeam = asset('assets/images/about-hero.jpg');
    $imgStudio = asset('assets/images/creators-hero.jpg');
    $imgCreatorsSide = asset('assets/images/about-creators.jpg');
    $imgAgentsSide = asset('assets/images/about-agents.jpg');
    $imgTrust = asset('assets/images/campaign-workspace.jpg');
@endphp

{{-- Hero --}}
<section class="relative w-full overflow-hidden bg-slate-50 pt-12 sm:pt-16 lg:pt-20 pb-14 sm:pb-20">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center">
            <div class="lg:col-span-6 flex flex-col items-start z-10">
                <h1 class="font-display text-3xl sm:text-4xl lg:text-5xl font-extrabold text-slate-900 tracking-tight mb-4 leading-tight">
                    Built to make digital campaigns happen.
                </h1>
                <p class="text-base sm:text-lg text-slate-600 max-w-xl mb-8 leading-relaxed">
                    {{ $brandName }} connects businesses with people who complete real digital campaign tasks — making it easier to launch, manage, and track campaigns from one place.
                </p>
                <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                    <a href="{{ route('services') }}" class="inline-flex items-center justify-center px-6 py-3 bg-primary hover:bg-primary-hover text-white font-semibold text-sm rounded-lg shadow-sm transition-colors">
                        Explore Services
                    </a>
                    <a href="{{ route('register.agent') }}" class="inline-flex items-center justify-center px-6 py-3 bg-white text-slate-900 hover:bg-slate-50 font-semibold text-sm rounded-lg shadow-sm border border-slate-200 transition-colors">
                        Become an Agent
                    </a>
                </div>
            </div>
            <div class="lg:col-span-6 relative">
                <div class="relative rounded-2xl overflow-hidden shadow-xl bg-slate-100 aspect-[16/11]">
                    <img src="{{ $imgTeam }}" alt="Diverse team collaborating on digital campaign strategy" class="w-full h-full object-cover object-center" loading="eager">
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950/40 via-transparent to-transparent"></div>
                    <div class="absolute bottom-3 left-3 right-3 p-3 sm:p-3.5 bg-white/95 backdrop-blur rounded-xl shadow-md flex items-center justify-between gap-3 z-10">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-2xl" style="font-variation-settings: 'FILL' 1;" aria-hidden="true">verified</span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900 leading-snug truncate">Human Proof Marketplace</p>
                                <p class="text-xs text-slate-500 leading-snug mt-0.5">Structured task verification &amp; secure payouts</p>
                            </div>
                        </div>
                        <div class="hidden sm:flex flex-col items-end shrink-0 pl-2 border-l border-slate-100">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Status</span>
                            <span class="text-sm text-emerald-700 font-semibold flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 shrink-0"></span> Active Ecosystem
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- What it is --}}
<section class="w-full py-14 sm:py-20 bg-white">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 items-center">
            <div class="lg:col-span-6 order-2 lg:order-1">
                <div class="relative rounded-2xl overflow-hidden shadow-md bg-slate-100 aspect-[4/3]">
                    <img src="{{ $imgStudio }}" alt="Creator working in modern digital campaign studio" class="w-full h-full object-cover" loading="lazy">
                    <div class="absolute top-4 left-4">
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-white/90 backdrop-blur text-xs font-medium text-slate-900 shadow-sm">
                            <span class="material-symbols-outlined text-base text-primary" aria-hidden="true">dynamic_feed</span>
                            Direct Campaign Execution
                        </span>
                    </div>
                </div>
            </div>
            <div class="lg:col-span-6 order-1 lg:order-2 flex flex-col">
                <span class="text-[11px] font-bold uppercase tracking-wider text-primary mb-2">Our Core Architecture</span>
                <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-slate-900 mb-4">
                    A marketplace built around action.
                </h2>
                <p class="text-base sm:text-lg text-slate-600 mb-6 leading-relaxed">
                    {{ $brandName }} is a digital campaign marketplace designed to make campaign execution simpler. Businesses can choose predefined campaign services, set their requirements, fund their campaigns, and track progress. Agents can discover available tasks, complete the required activities, submit proof, and earn rewards when their work is verified. The platform brings both sides together in one structured workflow.
                </p>
                <div class="flex flex-wrap gap-2.5">
                    <div class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white rounded-lg shadow-sm border border-slate-100 text-slate-900">
                        <span class="material-symbols-outlined text-primary text-xl" aria-hidden="true">inventory_2</span>
                        <span class="text-sm font-semibold">Predefined Campaign Tiers</span>
                    </div>
                    <div class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white rounded-lg shadow-sm border border-slate-100 text-slate-900">
                        <span class="material-symbols-outlined text-emerald-600 text-xl" aria-hidden="true">fact_check</span>
                        <span class="text-sm font-semibold">Verified Human Proof</span>
                    </div>
                    <div class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white rounded-lg shadow-sm border border-slate-100 text-slate-900">
                        <span class="material-symbols-outlined text-primary text-xl" aria-hidden="true">lock</span>
                        <span class="text-sm font-semibold">Verified Payouts</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Problem --}}
<section class="w-full py-14 sm:py-20 bg-slate-50">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl mb-12">
            <span class="text-[11px] font-bold uppercase tracking-wider text-primary mb-2 block">Operational Friction</span>
            <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-slate-900 mb-4">
                Digital campaigns should not be complicated.
            </h2>
            <p class="text-base sm:text-lg text-slate-600">
                Traditional digital execution is broken by fragmented channels, unreliable coordination, and opaque reporting. We fix the core bottlenecks.
            </p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach([
                ['icon' => 'group_search', 'iconBg' => 'bg-blue-50 text-primary', 'title' => 'Finding the right people', 'body' => 'Businesses often need real people to carry out specific digital activities, but finding and coordinating them can be difficult.', 'foot' => 'Vetted participant network', 'footClass' => 'text-primary'],
                ['icon' => 'dashboard_customize', 'iconBg' => 'bg-slate-100 text-slate-700', 'title' => 'Managing campaigns', 'body' => 'Campaign requirements, quantities, submissions, verification, and payments can become difficult to manage across different tools.', 'foot' => 'Unified end-to-end portal', 'footClass' => 'text-slate-600'],
                ['icon' => 'analytics', 'iconBg' => 'bg-emerald-50 text-emerald-700', 'title' => 'Knowing what happened', 'body' => 'Businesses need visibility into campaign progress and completed work instead of relying on assumptions.', 'foot' => 'Audited proof verification', 'footClass' => 'text-emerald-700'],
            ] as $block)
                <div class="bg-white p-6 sm:p-8 rounded-xl shadow-sm border border-slate-100 flex flex-col justify-between hover:shadow-md transition-shadow">
                    <div>
                        <div class="w-12 h-12 rounded-xl {{ $block['iconBg'] }} flex items-center justify-center mb-5">
                            <span class="material-symbols-outlined text-2xl" aria-hidden="true">{{ $block['icon'] }}</span>
                        </div>
                        <h3 class="font-display text-lg font-bold text-slate-900 mb-2">{{ $block['title'] }}</h3>
                        <p class="text-sm text-slate-600 leading-relaxed">{{ $block['body'] }}</p>
                    </div>
                    <div class="mt-5 pt-3 flex items-center gap-2 {{ $block['footClass'] }} text-sm font-semibold">
                        <span class="material-symbols-outlined text-base" aria-hidden="true">check_circle</span>
                        <span>{{ $block['foot'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Built for two sides --}}
<section class="w-full py-14 sm:py-20 bg-slate-50">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-12">
            <span class="text-[11px] font-bold uppercase tracking-wider text-primary mb-2 block">Balanced Ecosystem</span>
            <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-slate-900">Built for Two Sides</h2>
            <p class="text-sm sm:text-base text-slate-600 mt-2">
                Engineered to give organizations scalable output, while delivering fair, verified task rewards to independent contributors.
            </p>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-stretch">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 sm:p-8 lg:p-10 flex flex-col h-full">
                <div class="flex-1 flex flex-col">
                    <div class="flex items-center justify-between gap-3 mb-5">
                        <span class="px-3 py-1 rounded-full bg-primary/10 text-primary text-[11px] font-bold uppercase tracking-wider">For Creators</span>
                        <span class="text-xs text-slate-500">Businesses &amp; Brands</span>
                    </div>
                    <h3 class="font-display text-xl font-bold text-slate-900 mb-2">
                        Businesses, brands, organizations, and individuals who want to run digital campaigns.
                    </h3>
                    <p class="text-sm sm:text-base text-slate-600 mb-6 leading-relaxed">
                        Launch campaigns without managing every task yourself. Choose predefined services, provide your requirements, fund your campaign, and monitor progress from one place.
                    </p>
                    <div class="relative rounded-xl overflow-hidden aspect-[16/10] shadow-sm mb-6 bg-slate-100 mt-auto">
                        <img src="{{ $imgCreatorsSide }}" alt="Founder working on live campaign review" class="w-full h-full object-cover" loading="lazy">
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/40 to-transparent"></div>
                        <div class="absolute bottom-3 left-3 text-white">
                            <p class="text-sm font-semibold">Pre-set budgets &amp; real-time oversight</p>
                            <p class="text-xs text-slate-200">Deploy verified campaigns at institutional scale</p>
                        </div>
                    </div>
                </div>
                <div>
                    <a href="{{ route('register') }}" class="inline-flex items-center gap-1.5 px-6 py-3 bg-primary hover:bg-primary-hover text-white font-semibold text-sm rounded-lg shadow-sm transition-colors">
                        <span>Create a Campaign</span>
                        <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_forward</span>
                    </a>
                </div>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 sm:p-8 lg:p-10 flex flex-col h-full">
                <div class="flex-1 flex flex-col">
                    <div class="flex items-center justify-between gap-3 mb-5">
                        <span class="px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-bold uppercase tracking-wider">For Agents</span>
                        <span class="text-xs text-slate-500">Contributors &amp; Testers</span>
                    </div>
                    <h3 class="font-display text-xl font-bold text-slate-900 mb-2">
                        People who want to discover available digital tasks and earn by completing them.
                    </h3>
                    <p class="text-sm sm:text-base text-slate-600 mb-6 leading-relaxed">
                        Turn available digital tasks into opportunities. Discover eligible campaigns, complete the required activities, submit proof, and earn when your work is approved.
                    </p>
                    <div class="relative rounded-xl overflow-hidden aspect-[16/10] shadow-sm mb-6 bg-slate-100 mt-auto">
                        <img src="{{ $imgAgentsSide }}" alt="UX tester evaluating task application" class="w-full h-full object-cover" loading="lazy">
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/40 to-transparent"></div>
                        <div class="absolute bottom-3 left-3 text-white">
                            <p class="text-sm font-semibold">Verified campaign tasks</p>
                            <p class="text-xs text-slate-200">Get paid reliably on verification</p>
                        </div>
                    </div>
                </div>
                <div>
                    <a href="{{ route('register.agent') }}" class="inline-flex items-center gap-1.5 px-6 py-3 bg-slate-100 text-slate-900 hover:bg-slate-200 font-semibold text-sm rounded-lg shadow-sm transition-colors">
                        <span>Become an Agent</span>
                        <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_forward</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Trust --}}
<section class="w-full py-14 sm:py-20 bg-white">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 items-center">
            <div class="lg:col-span-7 flex flex-col">
                <span class="text-[11px] font-bold uppercase tracking-wider text-primary mb-2">Rigorous Integrity</span>
                <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-slate-900 mb-4">
                    Designed around accountability.
                </h2>
                <p class="text-base sm:text-lg text-slate-600 mb-8 leading-relaxed">
                    {{ $brandName }} operates without guesswork. Every step of execution is documented, submitted through auditable channels, and backed by verifiable rules.
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    @foreach([
                        ['icon' => 'assignment', 'tone' => 'text-primary', 'title' => 'Clear Requirements', 'body' => 'Defined criteria and bounds before any work begins.'],
                        ['icon' => 'price_check', 'tone' => 'text-primary', 'title' => 'Defined Quantities', 'body' => 'Fixed task counts and predictable reward structures.'],
                        ['icon' => 'badge', 'tone' => 'text-primary', 'title' => 'Task Eligibility', 'body' => 'Only matching, qualified Agents accept specific assignments.'],
                        ['icon' => 'upload_file', 'tone' => 'text-primary', 'title' => 'Proof Submission', 'body' => 'Screenshots, activity links, or audit payloads required.'],
                        ['icon' => 'verified_user', 'tone' => 'text-emerald-600', 'title' => 'Multi-point Verification', 'body' => 'System and review-assisted validation on all submissions.'],
                        ['icon' => 'account_balance_wallet', 'tone' => 'text-emerald-600', 'title' => 'Structured Settlement', 'body' => 'Automated payout release directly upon task approval.'],
                    ] as $p)
                        <div class="p-3.5 rounded-xl bg-white shadow-sm border border-slate-100 flex items-start gap-2.5">
                            <span class="material-symbols-outlined {{ $p['tone'] }} text-xl mt-0.5" aria-hidden="true">{{ $p['icon'] }}</span>
                            <div>
                                <h4 class="text-sm font-semibold text-slate-900">{{ $p['title'] }}</h4>
                                <p class="text-sm text-slate-600">{{ $p['body'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="lg:col-span-5">
                <div class="relative rounded-2xl overflow-hidden shadow-md bg-slate-100 aspect-[4/3]">
                    <img src="{{ $imgTrust }}" alt="Analyst reviewing campaign performance charts" class="w-full h-full object-cover" loading="lazy">
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950/40 via-transparent to-transparent"></div>
                    <div class="absolute bottom-4 left-4 right-4 p-3.5 bg-white/90 backdrop-blur rounded-xl">
                        <p class="text-sm font-semibold text-slate-900">Real-Time Progress Tracking</p>
                        <p class="text-sm text-slate-600">Transparent dashboards update with every confirmed milestone.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
