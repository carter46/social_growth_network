@extends('layouts.marketing')

@section('title', 'About Us')

@section('content')
@php
    $brandName = $siteName ?? config('app.name', 'Social Growth Network');
    $imgTeam = asset('assets/images/about-hero.jpg');
    $imgStudio = asset('assets/images/creators-hero.jpg');
    $imgCreatorsSide = asset('assets/images/about-creators.jpg');
@endphp

{{-- Hero --}}
<section class="relative w-full overflow-hidden bg-slate-50 pt-12 sm:pt-16 lg:pt-20 pb-14 sm:pb-20">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center">
            <div class="lg:col-span-6 flex flex-col items-start z-10">
                <h1 class="font-display text-3xl sm:text-4xl lg:text-5xl font-extrabold text-slate-900 tracking-tight mb-4 leading-tight">
                    Helping creators get seen on YouTube
                </h1>
                <p class="text-base sm:text-lg text-slate-600 max-w-xl mb-8 leading-relaxed">
                    {{ $brandName }} runs YouTube promotion campaigns that help creators boost the visibility and reach of their videos and channels. Launch, manage and track every campaign from one place.
                </p>
                <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                    <a href="{{ route('dashboard.services') }}" class="inline-flex items-center justify-center px-6 py-3 bg-primary hover:bg-primary-hover text-white font-semibold text-sm rounded-lg shadow-sm transition-colors">
                        Start a campaign
                    </a>
                    <a href="{{ route('home') }}#how-it-works" class="inline-flex items-center justify-center px-6 py-3 bg-white text-slate-900 hover:bg-slate-50 font-semibold text-sm rounded-lg shadow-sm border border-slate-200 transition-colors">
                        How it works
                    </a>
                </div>
            </div>
            <div class="lg:col-span-6 relative">
                <div class="relative rounded-2xl overflow-hidden shadow-xl bg-slate-100 aspect-[16/11]">
                    <img src="{{ $imgTeam }}" alt="Team planning a YouTube promotion campaign" class="w-full h-full object-cover object-center" loading="eager">
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950/40 via-transparent to-transparent"></div>
                    <div class="absolute bottom-3 left-3 right-3 p-3 sm:p-3.5 bg-white/95 backdrop-blur rounded-xl shadow-md flex items-center justify-between gap-3 z-10">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-2xl" style="font-variation-settings: 'FILL' 1;" aria-hidden="true">verified</span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900 leading-snug truncate">No bots, no fake views</p>
                                <p class="text-xs text-slate-500 leading-snug mt-0.5">Progress is checked and payments are secure</p>
                            </div>
                        </div>
                        <div class="hidden sm:flex flex-col items-end shrink-0 pl-2 border-l border-slate-100">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Status</span>
                            <span class="text-sm text-emerald-700 font-semibold flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 shrink-0"></span> Open for campaigns
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
                    <img src="{{ $imgStudio }}" alt="Creator working in a modern video studio" class="w-full h-full object-cover" loading="lazy">
                    <div class="absolute top-4 left-4">
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-white/90 backdrop-blur text-xs font-medium text-slate-900 shadow-sm">
                            <span class="material-symbols-outlined text-base text-primary" aria-hidden="true">dynamic_feed</span>
                            How it works
                        </span>
                    </div>
                </div>
            </div>
            <div class="lg:col-span-6 order-1 lg:order-2 flex flex-col">
                <span class="text-[11px] font-bold uppercase tracking-wider text-primary mb-2">What we do</span>
                <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-slate-900 mb-4">
                    Promotion built around your videos.
                </h2>
                <p class="text-base sm:text-lg text-slate-600 mb-6 leading-relaxed">
                    Creators choose a campaign, add their video or channel link, pay securely and follow progress from their account. Our campaigns are designed to put your content in front of more viewers and grow watch time and engagement. We don't use bots, fake views or fake subscribers.
                </p>
                <div class="flex flex-wrap gap-2.5">
                    <div class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white rounded-lg shadow-sm border border-slate-100 text-slate-900">
                        <span class="material-symbols-outlined text-primary text-xl" aria-hidden="true">inventory_2</span>
                        <span class="text-sm font-semibold">Clear campaign packages</span>
                    </div>
                    <div class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white rounded-lg shadow-sm border border-slate-100 text-slate-900">
                        <span class="material-symbols-outlined text-emerald-600 text-xl" aria-hidden="true">fact_check</span>
                        <span class="text-sm font-semibold">Progress you can follow</span>
                    </div>
                    <div class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white rounded-lg shadow-sm border border-slate-100 text-slate-900">
                        <span class="material-symbols-outlined text-primary text-xl" aria-hidden="true">lock</span>
                        <span class="text-sm font-semibold">Secure payment</span>
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
            <span class="text-[11px] font-bold uppercase tracking-wider text-primary mb-2 block">Why we exist</span>
            <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-slate-900 mb-4">
                Great videos deserve to be seen.
            </h2>
            <p class="text-base sm:text-lg text-slate-600">
                Many creators publish strong content that never reaches the audience it deserves. We make promoting it simpler.
            </p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach([
                ['icon' => 'visibility', 'iconBg' => 'bg-blue-50 text-primary', 'title' => 'Getting discovered', 'body' => 'New and growing channels often struggle to get their videos in front of viewers, even when the content is good.', 'foot' => 'Campaigns that boost reach', 'footClass' => 'text-primary'],
                ['icon' => 'dashboard_customize', 'iconBg' => 'bg-slate-100 text-slate-700', 'title' => 'Managing promotion', 'body' => 'Campaign goals, quantities, links and payments are hard to manage across different tools.', 'foot' => 'Everything in one account', 'footClass' => 'text-slate-600'],
                ['icon' => 'analytics', 'iconBg' => 'bg-emerald-50 text-emerald-700', 'title' => 'Knowing what happened', 'body' => 'Creators need clear visibility into campaign progress instead of relying on assumptions.', 'foot' => 'Progress is checked', 'footClass' => 'text-emerald-700'],
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

{{-- Who it's for --}}
<section class="w-full py-14 sm:py-20 bg-white">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 items-center">
            <div class="lg:col-span-6 flex flex-col items-start">
                <span class="px-3 py-1 rounded-full bg-primary/10 text-primary text-[11px] font-bold uppercase tracking-wider mb-4">Who it's for</span>
                <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-slate-900 mb-4">
                    Creators, brands and businesses on YouTube.
                </h2>
                <p class="text-base sm:text-lg text-slate-600 mb-6 leading-relaxed">
                    Whether you are launching a new channel or promoting your latest upload, choose a campaign, add your details, pay securely and track progress from one place.
                </p>
                <a href="{{ route('register') }}" class="inline-flex items-center gap-1.5 px-6 py-3 bg-primary hover:bg-primary-hover text-white font-semibold text-sm rounded-lg shadow-sm transition-colors">
                    <span>Promote your video</span>
                    <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_forward</span>
                </a>
            </div>
            <div class="lg:col-span-6">
                <div class="relative rounded-xl overflow-hidden aspect-[16/10] shadow-sm bg-slate-100">
                    <img src="{{ $imgCreatorsSide }}" alt="Creator reviewing a live campaign" class="w-full h-full object-cover" loading="lazy">
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950/40 to-transparent"></div>
                    <div class="absolute bottom-3 left-3 text-white">
                        <p class="text-sm font-semibold">Prices shown before you pay</p>
                        <p class="text-xs text-slate-200">Track your campaigns from your account</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
