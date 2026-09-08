@extends('layouts.marketing')

@section('title', 'For Creators')

@section('content')
@php
    $brandName = $siteName ?? config('app.name', 'Social Growth Network');
    $imgStudio = asset('assets/images/homeslider1.jpg');
    $imgCreator = asset('assets/images/homeslider2.jpg');
    $imgAnalytics = asset('assets/images/homeslider3.jpg');
    $imgServices = asset('assets/images/services_1.jpg');
@endphp

{{-- Hero --}}
<section class="w-full relative overflow-hidden bg-white">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16 lg:py-20">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 items-center">
            <div class="lg:col-span-7 flex flex-col items-start">
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-slate-50 text-primary mb-4">
                    <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                    <span class="text-[11px] font-bold uppercase tracking-wider">Built for creators &amp; founders</span>
                </div>
                <h1 class="font-display text-3xl sm:text-4xl lg:text-5xl font-extrabold text-slate-900 tracking-tight leading-tight mb-4">
                    Turn your digital goals into <span class="text-primary">real campaigns</span>.
                </h1>
                <p class="text-base sm:text-lg text-slate-600 max-w-2xl mb-8 leading-relaxed">
                    Choose a campaign service, select a predefined package, set your requirements, and let {{ $brandName }} handle verified execution across our distributed agent network.
                </p>
                <div class="flex flex-wrap items-center gap-3 mb-8 w-full sm:w-auto">
                    <a href="{{ route('register') }}" class="inline-flex items-center justify-center font-semibold text-sm bg-primary text-white px-6 py-3 rounded-lg hover:bg-primary-hover shadow-sm transition-colors">
                        Create a Campaign
                        <span class="material-symbols-outlined ml-1.5 text-[18px]" aria-hidden="true">arrow_forward</span>
                    </a>
                    <a href="{{ route('services') }}" class="inline-flex items-center justify-center font-semibold text-sm bg-white text-slate-900 hover:bg-slate-50 px-6 py-3 rounded-lg border border-slate-200 shadow-sm transition-colors">
                        Explore Services
                    </a>
                </div>
                <div class="pt-2 flex flex-wrap items-center gap-x-6 gap-y-2 text-slate-500 text-sm">
                    <div class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px] text-emerald-600" aria-hidden="true">check_circle</span>
                        <span>Zero bidding wars</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px] text-emerald-600" aria-hidden="true">check_circle</span>
                        <span>100% verified human submissions</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px] text-emerald-600" aria-hidden="true">check_circle</span>
                        <span>Fixed upfront packages</span>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-5 relative">
                <div class="relative rounded-2xl overflow-hidden shadow-xl bg-slate-100 aspect-[4/3] lg:aspect-[5/4]">
                    <img src="{{ $imgStudio }}" alt="Creative creator filming in production setup" class="w-full h-full object-cover object-center transform hover:scale-105 transition-transform duration-700" loading="eager">
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950/40 via-transparent to-transparent"></div>
                    <div class="absolute bottom-4 left-4 right-4 bg-white/95 backdrop-blur-md p-3.5 rounded-xl shadow-lg flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-lg bg-slate-50 flex items-center justify-center text-primary shrink-0">
                                <span class="material-symbols-outlined text-[22px]" aria-hidden="true">videocam</span>
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-slate-900 truncate">YouTube Watch Hours Pack</div>
                                <div class="text-xs text-emerald-700 flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                                    Milestone 3 of 4 Active • 92% Verified
                                </div>
                            </div>
                        </div>
                        <div class="hidden sm:block text-right shrink-0">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 block">Escrow State</span>
                            <span class="text-sm font-semibold text-primary">Secured</span>
                        </div>
                    </div>
                </div>
                <div class="hidden md:flex absolute -top-3 -right-3 lg:-top-4 lg:-right-4 bg-white p-3.5 rounded-xl shadow-md items-center gap-2.5 z-10" aria-hidden="true">
                    <span class="material-symbols-outlined text-[22px] text-emerald-600">verified_user</span>
                    <div>
                        <div class="text-xs font-semibold text-slate-900">{{ $brandName }} Guard</div>
                        <div class="text-[11px] text-slate-500">Real Human Audit Active</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Why creators --}}
<section class="w-full bg-slate-50 py-14 sm:py-20">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col items-center text-center max-w-3xl mx-auto mb-12">
            <span class="text-[11px] font-bold uppercase tracking-widest text-primary mb-2">Predictable Execution</span>
            <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight mb-3">
                Everything you need to launch a campaign.
            </h2>
            <p class="text-base sm:text-lg text-slate-600">
                Predictable, transparent execution designed to save hours of manual coordination and freelancer guesswork.
            </p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
            @foreach([
                ['icon' => 'inventory_2', 'title' => 'Predefined Packages', 'body' => 'Choose from clear campaign tiers with fixed pricing instead of haggling with freelancers or guessing rewards.', 'foot' => 'Fixed scope tiers', 'tone' => 'text-primary'],
                ['icon' => 'bolt', 'title' => 'Streamlined Setup', 'body' => 'Pick your service, select the target quantity, and provide your destination URL or submission criteria in 2 minutes.', 'foot' => 'Instant deployment', 'tone' => 'text-primary'],
                ['icon' => 'verified', 'title' => 'Verified Human Activity', 'body' => 'Tasks are executed by real community agents and submitted through multi-point verification before counting toward delivery.', 'foot' => 'Human-verified proof', 'tone' => 'text-emerald-700'],
                ['icon' => 'monitoring', 'title' => 'Transparent Progress', 'body' => 'Watch milestones complete in real time with automated activity logs and proof records accessible anytime.', 'foot' => 'Live telemetry', 'tone' => 'text-primary'],
            ] as $card)
                <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow flex flex-col justify-between">
                    <div>
                        <div class="w-12 h-12 rounded-xl bg-slate-50 flex items-center justify-center {{ $card['tone'] }} mb-5">
                            <span class="material-symbols-outlined text-[26px]" aria-hidden="true">{{ $card['icon'] }}</span>
                        </div>
                        <h3 class="font-display text-lg font-bold text-slate-900 mb-2">{{ $card['title'] }}</h3>
                        <p class="text-sm text-slate-600 leading-relaxed">{{ $card['body'] }}</p>
                    </div>
                    <div class="mt-5 pt-3 {{ $card['tone'] }} text-sm font-semibold">
                        <span>{{ $card['foot'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- How campaigns work --}}
<section class="w-full bg-white py-14 sm:py-20">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col items-center text-center max-w-3xl mx-auto mb-12">
            <span class="text-[11px] font-bold uppercase tracking-widest text-primary mb-2">Simple Workflow</span>
            <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight mb-3">
                Launch your campaign in three simple steps.
            </h2>
            <p class="text-base sm:text-lg text-slate-600">
                A predictable, automated framework built for zero friction and complete delivery assurance.
            </p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach([
                ['n' => '01', 'icon' => 'category', 'title' => 'Choose a Service', 'body' => 'Browse catalog options (YouTube Views, Watch Time, Social Engagement, Web Traffic) and choose your target scale.', 'note' => 'Popular picks:', 'noteBody' => 'YouTube High-Retention, Targeted Instagram Visits, Quality Pageviews.', 'tone' => 'text-primary'],
                ['n' => '02', 'icon' => 'tune', 'title' => 'Set Up Your Campaign', 'body' => 'Enter your target URL or channel, choose your predefined tier, and set any custom submission instructions.', 'note' => 'Precision criteria:', 'noteBody' => 'Duration targets, geo-focus, and optional validation questions.', 'tone' => 'text-primary'],
                ['n' => '03', 'icon' => 'rocket_launch', 'title' => 'Launch & Track', 'body' => 'Fund your campaign securely in escrow. Our verified agent network executes the tasks while you monitor progress.', 'note' => 'Protection:', 'noteBody' => 'Escrow payouts release automatically as proofs are validated.', 'tone' => 'text-emerald-700'],
            ] as $step)
                <div class="bg-slate-50 p-6 sm:p-8 rounded-2xl flex flex-col">
                    <div class="flex items-center justify-between mb-8">
                        <span class="font-display text-3xl font-bold text-primary/40">{{ $step['n'] }}</span>
                        <span class="w-10 h-10 rounded-full bg-white flex items-center justify-center {{ $step['tone'] }} shadow-sm">
                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">{{ $step['icon'] }}</span>
                        </span>
                    </div>
                    <h3 class="font-display text-xl font-bold text-slate-900 mb-2">{{ $step['title'] }}</h3>
                    <p class="text-sm text-slate-600 leading-relaxed">{{ $step['body'] }}</p>
                    <div class="mt-8 bg-white p-3.5 rounded-xl text-sm text-slate-600 border border-slate-100">
                        <span class="font-semibold text-slate-900">{{ $step['note'] }}</span> {{ $step['noteBody'] }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Campaign control --}}
<section class="w-full bg-slate-50 py-14 sm:py-20">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 items-center">
            <div class="lg:col-span-6 relative">
                <div class="rounded-2xl overflow-hidden shadow-lg bg-white border border-slate-100">
                    <img src="{{ $imgCreator }}" alt="Creator working with precision digital tools" class="w-full h-auto object-cover aspect-[16/10]" loading="lazy">
                </div>
                <div class="hidden sm:block absolute -bottom-6 right-6 bg-white p-5 rounded-xl shadow-lg max-w-xs border border-slate-100">
                    <div class="flex items-center gap-2 mb-1.5">
                        <span class="material-symbols-outlined text-primary text-[20px]" aria-hidden="true">lock_clock</span>
                        <span class="text-sm font-semibold text-slate-900">Escrow Multi-Vault</span>
                    </div>
                    <p class="text-sm text-slate-600">
                        Agent payouts remain locked in platform escrow until manual or algorithmic proof check passes.
                    </p>
                </div>
            </div>
            <div class="lg:col-span-6 flex flex-col items-start lg:pl-4">
                <span class="text-[11px] font-bold uppercase tracking-widest text-primary mb-2">Operational Rigor</span>
                <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight mb-4">
                    Stay in control from campaign setup to completion.
                </h2>
                <p class="text-base sm:text-lg text-slate-600 mb-6 leading-relaxed">
                    You set the requirements, target milestones, and package size. {{ $brandName }} manages the entire agent marketplace, proof verification, and reward settlement — giving you predictable results without contractor headaches.
                </p>
                <div class="flex flex-col gap-3.5 w-full mb-8">
                    @foreach([
                        'Choose your predefined package with clear upfront pricing',
                        'Specify custom completion instructions and target links',
                        'Automatic escrow protection until milestones pass validation',
                        'Live progress telemetry and downloadable submission logs',
                        'Instant pause, resume, or scale controls',
                    ] as $item)
                        <div class="flex items-start gap-2.5">
                            <div class="w-6 h-6 rounded-full bg-blue-50 flex items-center justify-center text-primary shrink-0 mt-0.5">
                                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">check</span>
                            </div>
                            <span class="text-sm text-slate-800">{{ $item }}</span>
                        </div>
                    @endforeach
                </div>
                <a href="{{ route('register') }}" class="inline-flex items-center justify-center font-semibold text-sm bg-primary text-white px-6 py-3 rounded-lg hover:bg-primary-hover shadow-sm transition-colors">
                    Start a Managed Campaign
                </a>
            </div>
        </div>
    </div>
</section>

{{-- Categories --}}
<section class="w-full bg-white py-14 sm:py-20">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-12 gap-4">
            <div>
                <span class="text-[11px] font-bold uppercase tracking-widest text-primary mb-2 block">Service Catalog</span>
                <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                    Campaign categories ready for launch.
                </h2>
            </div>
            <p class="text-sm text-slate-600 max-w-md">
                Explore popular predefined campaign models tailored for creators, startups, and expanding brands.
            </p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-10">
            @foreach([
                ['img' => $imgStudio, 'badge' => 'YOUTUBE', 'icon' => 'play_circle', 'badgeClass' => 'text-red-600', 'title' => 'YouTube Campaigns', 'body' => 'Views, watch time hours, authentic subscriber engagement, and high-retention video sessions.', 'packages' => 'View packages', 'href' => route('services', ['category' => 'youtube'])],
                ['img' => $imgCreator, 'badge' => 'SOCIAL', 'icon' => 'share', 'badgeClass' => 'text-primary', 'title' => 'Social Engagement', 'body' => 'Shares, authentic context-driven comments, profile saves, and cross-platform visibility boosts.', 'packages' => 'View packages', 'href' => route('services', ['category' => 'instagram'])],
                ['img' => $imgAnalytics, 'badge' => 'TRAFFIC', 'icon' => 'language', 'badgeClass' => 'text-emerald-700', 'title' => 'Website Traffic', 'body' => 'Real human pageviews, extended dwell time, organic blog readership, and product launch visits.', 'packages' => 'View packages', 'href' => route('services')],
                ['img' => $imgServices, 'badge' => 'MICRO-TASKS', 'icon' => 'checklist', 'badgeClass' => 'text-emerald-600', 'title' => 'Digital Micro-Tasks', 'body' => 'Structured survey feedback, verified app usability testing, onboarding reviews, and test conversions.', 'packages' => 'View packages', 'href' => route('services')],
            ] as $cat)
                <a href="{{ $cat['href'] }}" class="bg-white rounded-2xl overflow-hidden border border-slate-200 shadow-sm hover:shadow-md transition-shadow flex flex-col group">
                    <div class="h-44 w-full relative overflow-hidden bg-slate-100">
                        <img src="{{ $cat['img'] }}" alt="{{ $cat['title'] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
                        <div class="absolute top-3 left-3 bg-white/90 backdrop-blur-sm {{ $cat['badgeClass'] }} px-2.5 py-1 rounded-lg text-[11px] font-bold uppercase tracking-wider flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]" aria-hidden="true">{{ $cat['icon'] }}</span>
                            {{ $cat['badge'] }}
                        </div>
                    </div>
                    <div class="p-5 flex flex-col flex-grow justify-between">
                        <div>
                            <h3 class="font-display text-lg font-bold text-slate-900 mb-1.5">{{ $cat['title'] }}</h3>
                            <p class="text-sm text-slate-600 mb-4 leading-relaxed">{{ $cat['body'] }}</p>
                        </div>
                        <div class="pt-2 flex items-center justify-between text-sm font-semibold text-primary">
                            <span>{{ $cat['packages'] }}</span>
                            <span class="material-symbols-outlined text-[16px] group-hover:translate-x-1 transition-transform" aria-hidden="true">arrow_forward</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="flex justify-center">
            <a href="{{ route('services') }}" class="inline-flex items-center gap-1.5 font-semibold text-sm text-primary hover:text-primary-hover bg-slate-50 hover:bg-slate-100 px-6 py-3 rounded-xl transition-colors">
                <span>Explore All Services &amp; Packages</span>
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_forward</span>
            </a>
        </div>
    </div>
</section>

{{-- Creator experience --}}
<section class="w-full bg-slate-50 py-14 sm:py-20">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-3xl p-6 sm:p-8 lg:p-12 border border-slate-200 shadow-sm">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 items-center">
                <div class="lg:col-span-5 flex flex-col items-start">
                    <span class="text-[11px] font-bold uppercase tracking-widest text-emerald-700 mb-2">Autonomous Oversight</span>
                    <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight mb-3">
                        Built for people with something to grow.
                    </h2>
                    <p class="text-sm sm:text-base text-slate-600 mb-6 leading-relaxed">
                        Whether you are launching your first YouTube series, scaling your digital course, or building app traction, {{ $brandName }} coordinates verified human distribution so you can focus on making great content.
                    </p>
                    <div class="bg-slate-50 p-3.5 rounded-xl w-full flex items-center gap-3.5">
                        <img src="{{ $imgCreator }}" alt="Creator portrait" class="w-12 h-12 rounded-full object-cover shrink-0">
                        <div>
                            <p class="text-sm italic text-slate-800 leading-relaxed">
                                “{{ $brandName }} eliminated the messy back-and-forth. I booked a watch-time tier, paid into escrow, and verified proofs rolled in predictably.”
                            </p>
                            <span class="text-xs text-slate-500 font-semibold mt-1 block">
                                Elena Vance • Tech &amp; Design Creator
                            </span>
                        </div>
                    </div>
                </div>
                <div class="lg:col-span-7 flex flex-col justify-center">
                    <h3 class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-5">
                        Autonomous Campaign Lifecycle
                    </h3>
                    <div class="flex flex-col gap-0">
                        @foreach([
                            ['icon' => 'check', 'bg' => 'bg-primary', 'title' => '1. Campaign Launched', 'meta' => 'Immediate', 'metaClass' => 'text-emerald-700', 'body' => 'Tier selected, target criteria assigned, funds secured in escrow lock.'],
                            ['icon' => 'hub', 'bg' => 'bg-primary', 'title' => '2. Distributed to Verified Agents', 'meta' => 'Auto-Dispatched', 'metaClass' => 'text-primary', 'body' => 'Matched instantly with high-reputation human agents based on platform tiering.'],
                            ['icon' => 'fact_check', 'bg' => 'bg-emerald-600', 'title' => '3. Proof Submitted & Inspected', 'meta' => 'Real-Time Validation', 'metaClass' => 'text-emerald-700', 'body' => 'Submissions checked via automated screenshot hashing and telemetry audits.'],
                            ['icon' => 'verified', 'bg' => 'bg-emerald-700', 'title' => '4. Milestone 100% Completed', 'meta' => 'Escrow Released', 'metaClass' => 'text-emerald-700', 'body' => 'Clean telemetry exports ready, escrow released cleanly to executing agents.'],
                        ] as $i => $node)
                            @if($i > 0)
                                <div class="w-0.5 h-3 bg-primary/30 ml-7 -my-0.5"></div>
                            @endif
                            <div class="flex items-center gap-3.5 p-3.5 rounded-xl bg-slate-50 hover:bg-slate-100 transition-colors">
                                <div class="w-8 h-8 rounded-full {{ $node['bg'] }} text-white flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">{{ $node['icon'] }}</span>
                                </div>
                                <div class="flex-grow min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-sm font-semibold text-slate-900">{{ $node['title'] }}</span>
                                        <span class="text-[11px] font-semibold {{ $node['metaClass'] }} shrink-0">{{ $node['meta'] }}</span>
                                    </div>
                                    <p class="text-sm text-slate-600">{{ $node['body'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Final CTA --}}
<section class="w-full bg-white py-14 sm:py-20">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-primary text-white rounded-3xl p-8 sm:p-12 lg:p-16 shadow-xl relative overflow-hidden text-center flex flex-col items-center">
            <div class="absolute -right-20 -top-20 w-96 h-96 rounded-full bg-primary-hover/40 blur-3xl pointer-events-none"></div>
            <div class="absolute -left-20 -bottom-20 w-96 h-96 rounded-full bg-emerald-500/20 blur-3xl pointer-events-none"></div>
            <div class="relative z-10 max-w-3xl flex flex-col items-center">
                <span class="text-[11px] font-bold uppercase tracking-widest text-blue-100 mb-3">
                    Launch In 2 Minutes
                </span>
                <h2 class="font-display text-2xl sm:text-3xl lg:text-4xl font-extrabold tracking-tight mb-4 text-white">
                    Ready to launch your next campaign?
                </h2>
                <p class="text-base sm:text-lg text-blue-100 max-w-2xl mb-8 leading-relaxed">
                    Choose your campaign service, pick a predefined package, and start reaching real audiences in minutes with zero guesswork.
                </p>
                <div class="flex flex-wrap items-center justify-center gap-3 w-full sm:w-auto">
                    <a href="{{ route('register') }}" class="inline-flex items-center justify-center font-semibold text-sm bg-white text-primary hover:bg-slate-50 px-6 py-3 rounded-lg shadow transition-colors">
                        Create a Campaign
                        <span class="material-symbols-outlined ml-1.5 text-[18px]" aria-hidden="true">arrow_forward</span>
                    </a>
                    <a href="{{ route('services') }}" class="inline-flex items-center justify-center font-semibold text-sm bg-white/15 text-white hover:bg-white/25 border border-white/30 px-6 py-3 rounded-lg transition-colors">
                        Explore Services
                    </a>
                </div>
                <div class="mt-8 pt-6 flex flex-wrap items-center justify-center gap-4 sm:gap-6 text-sm text-blue-100">
                    <span>• No subscriptions required</span>
                    <span>• Transparent upfront pricing</span>
                    <span>• Verified human community</span>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
