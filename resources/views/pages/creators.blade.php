@extends('layouts.marketing')

@section('title', 'For Creators')

@section('content')
@php
    $brandName = $siteName ?? config('app.name', 'Social Growth Network');
    $imgStudio = asset('assets/images/creators-hero.jpg');
    $imgCreator = asset('assets/images/creators-operational.jpg');
    $imgSocial = asset('assets/images/Social_Media.jpg');
    $youtubeCatalog = $youtubeCatalog ?? ['featured' => null, 'others' => []];
    $watchHours = $youtubeCatalog['featured'] ?? null;
    $youtubeOthers = collect($youtubeCatalog['others'] ?? []);
    $watchHoursHref = is_array($watchHours) && filled($watchHours['href'] ?? null)
        ? $watchHours['href']
        : route('services.segment', 'youtube');
    $categoryCards = collect($categoryCards ?? [])->values()
        ->sortBy(fn ($card) => ($card['slug'] ?? '') === 'youtube' ? 0 : 1)
        ->values()
        ->take(5);
    $categoryIcons = [
        'youtube' => 'smart_display',
        'facebook' => 'public',
        'instagram' => 'photo_camera',
        'tiktok' => 'music_note',
        'twitter' => 'chat',
        'social-media' => 'share',
    ];
    $categoryBadgeTones = [
        'youtube' => 'text-red-600',
        'facebook' => 'text-blue-600',
        'instagram' => 'text-pink-600',
        'tiktok' => 'text-slate-900',
        'twitter' => 'text-sky-600',
        'social-media' => 'text-violet-600',
    ];
    $ytWord = static function (string $text, string $tone = 'red'): string {
        $class = $tone === 'white' ? 'text-white' : 'text-red-600';

        return preg_replace(
            '/\bYouTube\b/u',
            '<span class="'.$class.'">YouTube</span>',
            e($text)
        ) ?? e($text);
    };
@endphp

{{-- Hero --}}
<section class="w-full relative overflow-hidden bg-white border-b border-slate-100">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16 lg:py-20">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 items-center">
            <div class="lg:col-span-5 relative order-1 lg:order-2 pt-4 pb-8 sm:pt-5 sm:pb-10">
                <div class="relative rounded-2xl overflow-hidden shadow-xl bg-slate-100 aspect-[4/3] lg:aspect-[5/4]">
                    <img src="{{ $imgSocial }}" alt="YouTube growth campaigns for creators" class="w-full h-full object-cover object-center" loading="eager">
                    <div class="absolute inset-0 bg-gradient-to-t from-red-950/45 via-transparent to-transparent" aria-hidden="true"></div>
                </div>

                <div class="absolute top-0 left-0 sm:-left-2 max-w-[16rem] sm:max-w-[18rem] bg-white/95 backdrop-blur-md p-3 sm:p-3.5 rounded-xl shadow-lg border border-slate-100 z-20">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center text-red-600 shrink-0">
                            <span class="material-symbols-outlined text-[18px] leading-none" aria-hidden="true">smart_display</span>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-slate-900 leading-snug truncate">Watch Hours packages</div>
                            <div class="text-xs text-emerald-700 flex items-center gap-1.5 mt-0.5 leading-snug">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 shrink-0"></span>
                                <span class="truncate">Upfront pricing · Track in-account</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-7 flex flex-col items-start order-2 lg:order-1">
                <p class="text-primary font-bold text-xs sm:text-sm tracking-wider uppercase mb-3">{{ $brandName }}</p>
                <h1 class="font-display text-2xl sm:text-4xl lg:text-5xl font-extrabold text-slate-900 tracking-tight leading-tight mb-4">
                    {!! $ytWord('Grow YouTube Watch Hours — then expand to views, likes, and comments.') !!}
                </h1>
                <p class="text-base sm:text-lg text-slate-600 max-w-2xl mb-8 leading-relaxed">
                    Start with Watch Hours campaign packages, add engagement services when you need them, and track verified task progress from your account.
                </p>
                <div class="flex flex-wrap items-center gap-3 mb-8 w-full sm:w-auto">
                    <a href="{{ route('register') }}" class="inline-flex items-center justify-center font-semibold text-sm bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 shadow-sm transition-colors">
                        Pay For Watch Hours
                        <span class="material-symbols-outlined ml-1.5 text-[18px]" aria-hidden="true">arrow_forward</span>
                    </a>
                    <a href="{{ route('agents') }}" class="inline-flex items-center justify-center font-semibold text-sm bg-white text-slate-900 hover:bg-slate-50 px-6 py-3 rounded-lg border border-slate-200 shadow-sm transition-colors">
                        Watch &amp; Earn
                    </a>
                </div>
                <div class="pt-2 flex flex-wrap items-center gap-x-6 gap-y-2 text-slate-500 text-sm">
                    <div class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px] text-emerald-600" aria-hidden="true">check_circle</span>
                        <span>Watch Hours first</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px] text-emerald-600" aria-hidden="true">check_circle</span>
                        <span>Views, Likes &amp; Comments available</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px] text-emerald-600" aria-hidden="true">check_circle</span>
                        <span>Fixed upfront packages</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Featured Watch Hours + other YouTube --}}
<section class="w-full bg-slate-50 py-14 sm:py-20 border-b border-slate-100">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-10 max-w-2xl">
            <span class="text-slate-500 font-bold text-xs tracking-wider uppercase mb-2 block">YouTube services</span>
            <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                {!! $ytWord('Built around YouTube Watch Hours.') !!}
            </h2>
            <p class="text-slate-600 text-base sm:text-lg mt-2">Lead with Watch Hours, then use Views, Likes, and Comments as supporting packages.</p>
        </div>

        @if(is_array($watchHours))
            <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-sm mb-10">
                <div class="grid grid-cols-1 lg:grid-cols-12">
                    <div class="lg:col-span-6 p-8 sm:p-10 flex flex-col justify-center order-2 lg:order-1">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Featured</span>
                        <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-display mb-4">
                            {!! $ytWord($watchHours['title'] ?? 'YouTube Watch Hours') !!}
                        </h3>
                        <p class="text-slate-600 mb-6 max-w-lg">{{ $watchHours['short_description'] }}</p>
                        @if(! empty($watchHours['from_price']))
                            <p class="text-sm font-semibold text-slate-700 mb-4">From ₦{{ number_format((float) $watchHours['from_price'], 0) }}</p>
                        @endif
                        <div class="flex flex-wrap gap-3">
                            <a href="{{ route('register') }}" class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white font-semibold text-sm px-5 py-3 rounded-lg transition-colors">
                                Pay For Watch Hours
                                <span class="material-symbols-outlined text-lg" aria-hidden="true">arrow_forward</span>
                            </a>
                            <a href="{{ $watchHoursHref }}" class="inline-flex items-center gap-1 text-primary font-semibold text-sm hover:underline">
                                View packages →
                            </a>
                        </div>
                    </div>
                    <div class="lg:col-span-6 relative min-h-[220px] sm:min-h-[280px] order-1 lg:order-2 bg-slate-100">
                        <img
                            src="{{ ! empty($watchHours['hero_url']) ? $watchHours['hero_url'] : $imgSocial }}"
                            alt="{{ $watchHours['title'] ?? 'YouTube Watch Hours' }}"
                            class="absolute inset-0 w-full h-full object-cover"
                            loading="lazy"
                        >
                    </div>
                </div>
            </div>
        @endif

        @if($youtubeOthers->isNotEmpty())
            <h3 class="text-lg font-bold text-slate-900 font-display mb-4">More YouTube packages</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                @foreach($youtubeOthers as $product)
                    <a href="{{ $product['href'] }}" class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm hover:shadow-md transition-shadow">
                        <h4 class="font-display text-base font-bold text-slate-900 mb-1">{{ $product['title'] }}</h4>
                        <p class="text-sm text-slate-600 mb-3 line-clamp-2">{{ $product['short_description'] }}</p>
                        <span class="text-sm font-semibold text-primary">
                            @if(! empty($product['from_price']))
                                From ₦{{ number_format((float) $product['from_price'], 0) }}
                            @else
                                View package →
                            @endif
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</section>

{{-- How it works --}}
<section class="w-full bg-white py-14 sm:py-20">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col items-center text-center max-w-3xl mx-auto mb-12">
            <span class="text-[11px] font-bold uppercase tracking-widest text-primary mb-2">Simple Workflow</span>
            <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight mb-3">
                Launch Watch Hours in three steps.
            </h2>
            <p class="text-base sm:text-lg text-slate-600">
                The same flow works for Views, Likes, Comments, and other platforms.
            </p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach([
                ['n' => '01', 'icon' => 'category', 'title' => 'Choose a service', 'body' => 'Start with YouTube Watch Hours or pick another package with upfront fixed pricing.'],
                ['n' => '02', 'icon' => 'link', 'title' => 'Provide campaign details', 'body' => 'Add your public video URL and any instructions in the checkout form.'],
                ['n' => '03', 'icon' => 'rocket_launch', 'title' => 'Pay and track', 'body' => 'Pay securely, then follow verified task completion in your account.'],
            ] as $step)
                <div class="bg-slate-50 p-6 sm:p-8 rounded-2xl flex flex-col border border-slate-200/80">
                    <div class="flex items-center justify-between mb-6">
                        <span class="font-display text-3xl font-bold text-primary/40">{{ $step['n'] }}</span>
                        <span class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-primary shadow-sm">
                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">{{ $step['icon'] }}</span>
                        </span>
                    </div>
                    <h3 class="font-display text-xl font-bold text-slate-900 mb-2">{{ $step['title'] }}</h3>
                    <p class="text-sm text-slate-600 leading-relaxed">{{ $step['body'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Other platforms --}}
<section class="w-full bg-slate-50 py-14 sm:py-20">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-12 gap-4">
            <div>
                <span class="text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-2 block">Other platforms</span>
                <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                    Also grow on Facebook, Instagram, TikTok, and Twitter.
                </h2>
            </div>
            <p class="text-sm text-slate-600 max-w-md">
                YouTube is covered above. Use these categories for additional campaigns.
            </p>
        </div>

        @if($categoryCards->isEmpty())
            <div class="rounded-xl border border-slate-200 bg-white p-8 text-center">
                <p class="text-slate-600 mb-4">Campaign categories will appear here once published in the catalog.</p>
                <a href="{{ route('services') }}" class="inline-flex text-sm font-semibold text-primary hover:underline">Browse services</a>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-10">
                @foreach($categoryCards->filter(fn ($c) => ($c['slug'] ?? '') !== 'youtube')->take(4) as $card)
                    @php
                        $slug = $card['slug'] ?? '';
                        $label = $card['label'] ?? $slug;
                        $href = $card['href'] ?? route('services', array_filter(['category' => $slug ?: null]));
                        $body = $card['short_description'] ?? $card['hero_subtitle'] ?? 'Predefined packages with upfront pricing and secure payment.';
                        $image = $card['card_image'] ?? $card['banner_image'] ?? $card['image'] ?? null;
                        $icon = $categoryIcons[$slug] ?? ($card['icon'] ?? 'category');
                        $badgeClass = $categoryBadgeTones[$slug] ?? 'text-primary';
                        $count = (int) ($card['count'] ?? 0);
                        $ctaLabel = $count > 0
                            ? 'View '.$count.' '.\Illuminate\Support\Str::plural('package', $count)
                            : 'View packages';
                    @endphp
                    <a href="{{ $href }}" class="bg-white rounded-2xl overflow-hidden border border-slate-200 shadow-sm hover:shadow-md transition-shadow flex flex-col group">
                        <div class="h-44 w-full relative overflow-hidden bg-slate-100">
                            @if($image)
                                <img src="{{ $image }}" alt="{{ $label }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
                            @else
                                <div class="w-full h-full bg-gradient-to-br from-primary/20 via-slate-200 to-slate-100" aria-hidden="true"></div>
                            @endif
                            <div class="absolute top-3 left-3 bg-white/90 backdrop-blur-sm {{ $badgeClass }} px-2.5 py-1 rounded-lg text-[11px] font-bold uppercase tracking-wider flex items-center gap-1 max-w-[calc(100%-1.5rem)]">
                                <span class="material-symbols-outlined text-[14px] shrink-0" aria-hidden="true">{{ $icon }}</span>
                                <span class="truncate">{{ $label }}</span>
                            </div>
                        </div>
                        <div class="p-5 flex flex-col flex-grow justify-between">
                            <div>
                                <h3 class="font-display text-lg font-bold text-slate-900 mb-1.5">{{ $label }}</h3>
                                <p class="text-sm text-slate-600 mb-4 leading-relaxed line-clamp-3">{{ $body }}</p>
                            </div>
                            <div class="pt-2 flex items-center justify-between text-sm font-semibold text-primary gap-2">
                                <span>{{ $ctaLabel }}</span>
                                <span class="material-symbols-outlined text-[16px] group-hover:translate-x-1 transition-transform shrink-0" aria-hidden="true">arrow_forward</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

        <div class="flex justify-center">
            <a href="{{ route('services') }}" class="inline-flex items-center gap-1.5 font-semibold text-sm text-primary hover:text-primary-hover bg-white hover:bg-slate-100 px-6 py-3 rounded-lg border border-slate-200 transition-colors">
                <span>Explore all services</span>
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_forward</span>
            </a>
        </div>
    </div>
</section>

{{-- Control --}}
<section class="w-full bg-white py-14 sm:py-20">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 items-center">
            <div class="lg:col-span-6 relative pb-8 sm:pb-10">
                <div class="rounded-2xl overflow-hidden shadow-lg bg-white border border-slate-100">
                    <img src="{{ $imgCreator }}" alt="Creator managing campaign progress" class="w-full h-auto object-cover aspect-[16/10]" loading="lazy">
                </div>
            </div>
            <div class="lg:col-span-6 flex flex-col items-start lg:pl-4">
                <span class="text-[11px] font-bold uppercase tracking-widest text-primary mb-2">Stay in control</span>
                <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight mb-4">
                    {!! $ytWord('From Watch Hours setup to verified completion.') !!}
                </h2>
                <p class="text-base sm:text-lg text-slate-600 mb-6 leading-relaxed">
                    You choose the package and provide the video URL. {{ $brandName }} handles verified task activity and progress you can review in your account.
                </p>
                <div class="flex flex-col gap-3.5 w-full mb-8">
                    @foreach([
                        'Upfront Watch Hours packages with clear pricing',
                        'Public video URL at checkout',
                        'Secure payment and verified task activity',
                        'Live progress tracking in your account',
                    ] as $item)
                        <div class="flex items-start gap-2.5">
                            <div class="w-6 h-6 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600 shrink-0 mt-0.5">
                                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">check</span>
                            </div>
                            <span class="text-sm text-slate-800">{{ $item }}</span>
                        </div>
                    @endforeach
                </div>
                <a href="{{ route('register') }}" class="inline-flex items-center justify-center font-semibold text-sm bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 shadow-sm transition-colors">
                    Pay For Watch Hours
                </a>
            </div>
        </div>
    </div>
</section>

{{-- Final CTA --}}
<section class="w-full bg-white py-14 sm:py-20">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="rounded-3xl p-8 sm:p-12 lg:p-16 shadow-xl relative overflow-hidden text-center flex flex-col items-center text-white">
            <div class="absolute inset-0 bg-gradient-to-br from-red-950 via-red-900 to-slate-950" aria-hidden="true"></div>
            <div class="absolute -right-20 -top-20 w-96 h-96 rounded-full bg-red-500/25 blur-3xl pointer-events-none" aria-hidden="true"></div>
            <div class="relative z-10 max-w-3xl flex flex-col items-center">
                <span class="text-[11px] font-bold uppercase tracking-widest text-red-100 mb-3">
                    Launch in minutes
                </span>
                <h2 class="font-display text-2xl sm:text-3xl lg:text-4xl font-extrabold tracking-tight mb-4 text-white">
                    {!! $ytWord('Ready to grow YouTube Watch Hours?', 'white') !!}
                </h2>
                <p class="text-base sm:text-lg text-red-50/90 max-w-2xl mb-8 leading-relaxed">
                    Create an account, choose a Watch Hours package, add your video URL, and track progress in your account.
                </p>
                <div class="flex flex-wrap items-center justify-center gap-3 w-full sm:w-auto">
                    <a href="{{ route('register') }}" class="inline-flex items-center justify-center font-semibold text-sm bg-red-600 text-white hover:bg-red-500 px-6 py-3 rounded-lg shadow transition-colors">
                        Pay For Watch Hours
                        <span class="material-symbols-outlined ml-1.5 text-[18px]" aria-hidden="true">arrow_forward</span>
                    </a>
                    <a href="{{ route('agents') }}" class="inline-flex items-center justify-center font-semibold text-sm bg-white/15 text-white hover:bg-white/25 border border-white/30 px-6 py-3 rounded-lg transition-colors">
                        Watch &amp; Earn
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
