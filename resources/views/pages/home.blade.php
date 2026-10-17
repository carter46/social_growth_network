@extends('layouts.marketing')

@section('title', $siteHeading ?? 'Digital Campaign Marketplace')

@section('content')
@php
    $brandName = $siteName ?? config('app.name', 'Social Growth Network');
    $youtubeCatalog = $youtubeCatalog ?? ['featured' => null, 'others' => []];
    $watchHours = $youtubeCatalog['featured'] ?? null;
    $youtubeOthers = collect($youtubeCatalog['others'] ?? []);
    $watchHoursHref = is_array($watchHours) && filled($watchHours['href'] ?? null)
        ? $watchHours['href']
        : '#youtube-services';
    $campaignHref = route('services');
    $promoteHref = is_array($watchHours) && filled($watchHours['href'] ?? null)
        ? $watchHours['href']
        : $campaignHref;
    $imgCreator = asset('assets/images/undraw_social-expert_wfam.png');
    $youtubeFeatureImage = (is_array($watchHours) && filled($watchHours['hero_url'] ?? null))
        ? $watchHours['hero_url']
        : null;
    $badgeTones = [
        'text-red-600',
        'text-purple-600',
        'text-teal-600',
        'text-emerald-600',
        'text-sky-600',
    ];
    $badgeIcons = ['smart_display', 'share', 'public', 'task_alt', 'campaign'];
    /** Accent “YouTube” in large titles (red on light, white on red surfaces). */
    $ytWord = static function (string $text, string $tone = 'red'): string {
        $class = $tone === 'white' ? 'text-white' : 'text-red-600';

        return preg_replace(
            '/\bYouTube\b/u',
            '<span class="'.$class.'">YouTube</span>',
            e($text)
        ) ?? e($text);
    };
@endphp

{{-- 1. White YouTube Watch Hours hero --}}
<section class="home-hero relative flex items-center overflow-hidden bg-white border-b border-slate-100">
    <div
        class="home-hero-white-motif absolute inset-0 z-0 pointer-events-none"
        style="background-image: url('{{ asset('assets/images/home_whitepng.png') }}');"
        aria-hidden="true"
    ></div>
    <div class="absolute inset-0 z-0 pointer-events-none bg-gradient-to-b from-white/55 via-white/40 to-white/65" aria-hidden="true"></div>

    <div class="relative z-10 max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14 lg:py-16 w-full">
        <div class="max-w-5xl mx-auto w-full text-center">
            <p class="text-primary font-bold text-xs sm:text-sm tracking-wider uppercase mb-3">
                {{ $brandName }}
            </p>
            <h1 class="home-hero-title sm:text-4xl md:text-5xl lg:text-[52px] font-extrabold text-slate-900 tracking-tight leading-[1.15] text-balance mb-4 sm:mb-5 font-display">
                {!! $ytWord('Get your YouTube videos seen by more viewers.') !!}
            </h1>
            <p class="text-base sm:text-lg lg:text-xl text-slate-600 font-normal leading-relaxed mb-8 sm:mb-10 max-w-xl mx-auto">
                Promotion campaigns that help creators boost visibility, reach, watch time and engagement on YouTube.
            </p>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-3 sm:gap-4">
                <a
                    class="inline-flex items-center justify-center gap-1.5 sm:gap-2 bg-red-600 hover:bg-red-700 text-white font-semibold text-sm sm:text-base px-5 py-3 sm:px-6 sm:py-3.5 rounded-lg shadow-sm hover:shadow transition-all"
                    href="{{ $campaignHref }}"
                >
                    <span>Start a campaign</span>
                    <span class="material-symbols-outlined text-base sm:text-lg" aria-hidden="true">arrow_forward</span>
                </a>
                <a
                    class="inline-flex items-center justify-center bg-white hover:bg-slate-50 text-slate-800 font-medium text-sm sm:text-base px-5 py-3 sm:px-6 sm:py-3.5 rounded-lg border border-slate-200 transition-all"
                    href="#how-it-works"
                >
                    How it works
                </a>
            </div>

            <p class="mt-6 inline-flex items-center justify-center gap-1.5 text-sm text-slate-500">
                <span class="material-symbols-outlined text-base text-emerald-600" aria-hidden="true">verified</span>
                We don't use bots, fake views or fake subscribers.
            </p>
        </div>
    </div>
</section>

{{-- 2. YouTube services: featured Watch Hours + supporting products --}}
<section class="py-20 lg:py-28 bg-slate-50 border-b border-slate-100" id="youtube-services">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-12 lg:mb-16 max-w-2xl">
            <span class="text-slate-500 font-bold text-xs sm:text-sm tracking-wider uppercase mb-2 block">YouTube services</span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight font-display">{!! $ytWord('Built around YouTube growth.') !!}</h2>
            <p class="text-slate-600 text-base sm:text-lg mt-2">Campaigns that help your videos gain more views, watch time and engagement.</p>
        </div>

        @if(is_array($watchHours))
            <div class="home-yt-featured mb-12 lg:mb-16 rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-sm">
                <div class="grid grid-cols-1 lg:grid-cols-12">
                    <div class="lg:col-span-6 p-8 sm:p-10 lg:p-12 flex flex-col justify-center order-2 lg:order-1">
                        <span class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">
                            <span class="material-symbols-outlined text-base text-red-600" aria-hidden="true">smart_display</span>
                            Featured
                        </span>
                        <h3 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-slate-900 tracking-tight font-display mb-4">
                            {!! $ytWord($watchHours['title'] ?? 'YouTube Watch Hours') !!}
                        </h3>
                        <p class="text-slate-600 text-base sm:text-lg leading-relaxed mb-6 max-w-lg">
                            {{ $watchHours['short_description'] }}
                        </p>
                        <p class="text-sm text-slate-500 mb-6">
                            At checkout, you’ll add the link to a public YouTube video.
                        </p>
                        <div class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-3 sm:gap-4">
                            @if(! empty($watchHours['from_price']))
                                <span class="text-sm font-semibold text-slate-700 sm:mr-1">
                                    From {{ money((float) $watchHours['from_price'], 2) }}
                                </span>
                            @endif
                            <a
                                class="inline-flex items-center justify-center gap-2 bg-red-600 hover:bg-red-700 text-white font-semibold text-sm sm:text-base px-5 py-3 rounded-lg shadow-sm transition-all w-full sm:w-auto"
                                href="{{ $watchHoursHref }}"
                            >
                                <span>Promote your video</span>
                                <span class="material-symbols-outlined text-lg" aria-hidden="true">arrow_forward</span>
                            </a>
                        </div>
                    </div>
                    <div class="lg:col-span-6 relative min-h-[240px] sm:min-h-[320px] lg:min-h-full order-1 lg:order-2 bg-slate-100">
                        @if($youtubeFeatureImage)
                            <img
                                src="{{ $youtubeFeatureImage }}"
                                alt="{{ $watchHours['title'] ?? 'YouTube Watch Hours' }}"
                                class="absolute inset-0 w-full h-full object-cover"
                                loading="lazy"
                            >
                            <div class="absolute inset-0 bg-gradient-to-t from-red-950/35 via-transparent to-transparent" aria-hidden="true"></div>
                        @else
                            <div class="absolute inset-0 bg-gradient-to-br from-primary/25 via-slate-200 to-slate-100" aria-hidden="true"></div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        @if($youtubeOthers->isNotEmpty())
            <div class="mb-6">
                <h3 class="text-lg sm:text-xl font-bold text-slate-900 font-display">More YouTube packages</h3>
                <p class="text-slate-500 text-sm mt-1">Campaigns for views, engagement and channel growth, each with a clear price.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 {{ $youtubeOthers->count() >= 4 ? 'lg:grid-cols-4' : 'lg:grid-cols-3' }} gap-6 lg:gap-8">
                @foreach($youtubeOthers as $index => $product)
                    @php
                        $tone = $badgeTones[$index % count($badgeTones)];
                        $icon = $badgeIcons[$index % count($badgeIcons)];
                    @endphp
                    <div class="group bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between">
                        <div>
                            <div class="relative h-48 w-full overflow-hidden bg-slate-100">
                                @if(! empty($product['hero_url']))
                                    <img src="{{ $product['hero_url'] }}" alt="{{ $product['title'] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent"></div>
                                @else
                                    <div class="w-full h-full bg-gradient-to-br from-primary/25 via-slate-200 to-slate-100"></div>
                                @endif
                                <span class="absolute top-3 left-3 inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/95 backdrop-blur-md {{ $tone }} text-xs font-bold shadow-sm">
                                    <span class="material-symbols-outlined text-sm" aria-hidden="true">{{ $icon }}</span>
                                    {{ $product['category_label'] ?? 'YouTube' }}
                                </span>
                            </div>
                            <div class="p-5">
                                <h3 class="text-xl font-bold text-slate-900 mb-2 group-hover:text-primary transition-colors font-display">
                                    <a href="{{ $product['href'] }}">{{ $product['title'] }}</a>
                                </h3>
                                <p class="text-slate-600 text-sm leading-relaxed mb-4">{{ $product['short_description'] }}</p>
                            </div>
                        </div>
                        <div class="px-5 pb-5 pt-3 border-t border-slate-100 flex items-center justify-between mt-auto">
                            <span class="text-xs font-medium text-slate-500">
                                @if(! empty($product['from_price']))
                                    From {{ money((float) $product['from_price'], 2) }}
                                @else
                                    See packages
                                @endif
                            </span>
                            <a class="inline-flex items-center gap-1 text-primary font-semibold text-sm hover:underline group-hover:translate-x-0.5 transition-transform" href="{{ $product['href'] }}">View package →</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @elseif(! is_array($watchHours))
            <p class="text-slate-500 text-center py-12">No YouTube services are available right now. Please check back soon.</p>
        @endif
    </div>
</section>

{{-- 3. How it works --}}
<section class="py-20 lg:py-24 bg-white border-b border-slate-100" id="how-it-works">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-16">
            <span class="text-primary font-bold text-xs sm:text-sm tracking-wider uppercase mb-2 block">How {{ $brandName }} works</span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight font-display">Launch in three simple steps.</h2>
            <p class="text-slate-600 text-base sm:text-lg mt-2">It works the same way for every service.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 relative">
            @foreach([
                ['n' => '01', 'icon' => 'category', 'title' => 'Choose a service', 'body' => 'Pick a YouTube campaign, then choose a package. You’ll see the price before you pay.', 'foot' => 'Step 1 · No haggling'],
                ['n' => '02', 'icon' => 'link', 'title' => 'Provide campaign details', 'body' => 'Add your YouTube video or channel link and any campaign instructions at checkout.', 'foot' => 'Step 2 · Quick setup'],
                ['n' => '03', 'icon' => 'rocket_launch', 'title' => 'Pay and track', 'body' => 'Pay securely, then follow your campaign’s progress from your account.', 'foot' => 'Step 3 · Secure payment'],
            ] as $step)
                <div
                    class="reveal-fade-left bg-slate-50 p-8 rounded-xl border border-slate-200/80 flex flex-col justify-between"
                    data-reveal="fade-left"
                    style="--reveal-delay: {{ $loop->index * 500 }}ms"
                >
                    <div>
                        <div class="flex items-center justify-between mb-6">
                            <span class="text-3xl font-black text-primary font-display">{{ $step['n'] }}</span>
                            <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center text-primary">
                                <span class="material-symbols-outlined text-2xl" aria-hidden="true">{{ $step['icon'] }}</span>
                            </div>
                        </div>
                        <h3 class="text-xl font-bold text-slate-900 mb-2.5 font-display">{{ $step['title'] }}</h3>
                        <p class="text-slate-600 text-sm leading-relaxed">{{ $step['body'] }}</p>
                    </div>
                    <div class="mt-6 pt-4 border-t border-slate-200/80 flex items-center text-xs font-semibold text-slate-400 uppercase tracking-wider">
                        <span>{{ $step['foot'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- 4. Stay in control --}}
<section class="py-20 lg:py-24 bg-white">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 items-center">
            <div class="lg:col-span-6 relative">
                <div class="rounded-2xl overflow-hidden shadow-lg bg-white border border-slate-100 p-6 sm:p-8">
                    <img src="{{ $imgCreator }}" alt="Creator managing a YouTube channel" width="1757" height="1362" class="w-full h-auto object-contain aspect-[16/10]" loading="lazy">
                </div>
            </div>
            <div class="lg:col-span-6 flex flex-col items-start lg:pl-4">
                <span class="text-primary font-bold text-xs sm:text-sm tracking-wider uppercase mb-2 block">Stay in control</span>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight font-display mb-4">
                    {!! $ytWord('From campaign setup to results you can follow.') !!}
                </h2>
                <p class="text-base sm:text-lg text-slate-600 mb-6 leading-relaxed">
                    You choose the package and provide the video URL. {{ $brandName }} runs the promotion, and you can review progress in your account.
                </p>
                <div class="flex flex-col gap-3.5 w-full mb-8">
                    @foreach([
                        'Upfront campaign packages with clear pricing',
                        'Public video URL at checkout',
                        'Secure payment, with progress checked before it counts',
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
                <a href="{{ $campaignHref }}" class="inline-flex items-center justify-center font-semibold text-sm bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 shadow-sm transition-colors">
                    Start a campaign
                </a>
            </div>
        </div>
    </div>
</section>

{{-- 5. Final CTA --}}
<x-marketing.cta-card eyebrow="Launch in minutes" class="pt-0 sm:pt-0">
    <x-slot:title>{!! $ytWord('Ready to get your YouTube videos seen?', 'white') !!}</x-slot:title>
    <x-slot:description>Create an account, choose a campaign package, add your video URL, and track progress in your account.</x-slot:description>
    <x-slot:actions>
        <a href="{{ $promoteHref }}" class="inline-flex items-center justify-center gap-1.5 font-semibold text-sm bg-white text-red-700 hover:bg-red-50 px-6 py-3 rounded-lg shadow-md transition-colors">
            <span>Promote your video</span>
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_forward</span>
        </a>
        @guest
            <a href="{{ route('register') }}" class="inline-flex items-center justify-center font-semibold text-sm bg-white/10 text-white hover:bg-white/20 border border-white/30 px-6 py-3 rounded-lg transition-colors">
                Create an account
            </a>
        @endguest
    </x-slot:actions>
    <x-slot:note>Fixed-price packages · Pay securely · Track progress in your account</x-slot:note>
</x-marketing.cta-card>

{{-- Newsletter + FAQ strip --}}
<section class="py-14 sm:py-16 bg-white scroll-mt-24" id="faq">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div id="newsletter" class="scroll-mt-24 rounded-2xl border border-slate-200 bg-slate-50 p-6 sm:p-8 lg:p-10">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-10 items-center">
                <div class="lg:col-span-5">
                    <span class="text-slate-500 font-bold text-xs tracking-wider uppercase mb-2 block">Newsletter</span>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight font-display">Get updates in your inbox</h2>
                    <p class="text-slate-600 text-sm sm:text-base mt-2 leading-relaxed">New services, offers, and tips for growing your channel. No spam, and you can unsubscribe at any time.</p>
                </div>
                <div class="lg:col-span-7">
                    @if (session('newsletter_status'))
                        <div class="flex items-start gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">check_circle</span>
                            <span>{{ session('newsletter_status') }}</span>
                        </div>
                    @else
                        <form method="POST" action="{{ route('newsletter.subscribe') }}" class="flex flex-col sm:flex-row gap-3" novalidate>
                            @csrf
                            <input type="hidden" name="source" value="home">
                            <div class="hidden" aria-hidden="true">
                                <label for="newsletter-website">Website</label>
                                <input type="text" id="newsletter-website" name="website" tabindex="-1" autocomplete="off">
                            </div>
                            <label for="newsletter-email" class="sr-only">Email address</label>
                            <input
                                type="email"
                                id="newsletter-email"
                                name="email"
                                value="{{ old('email') }}"
                                required
                                autocomplete="email"
                                placeholder="Enter your email address"
                                @class([
                                    'flex-1 min-w-0 rounded-lg border bg-white px-4 py-3 text-sm sm:text-base text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary',
                                    'border-red-400' => $errors->newsletter->has('email'),
                                    'border-slate-300' => ! $errors->newsletter->has('email'),
                                ])
                            >
                            <button type="submit" class="inline-flex items-center justify-center gap-1.5 bg-red-600 hover:bg-red-700 text-white font-semibold text-sm sm:text-base px-6 py-3 rounded-lg shadow-sm transition-colors">
                                Subscribe
                            </button>
                        </form>
                        @if ($errors->newsletter->has('email'))
                            <p class="mt-2 text-sm text-red-600">{{ $errors->newsletter->first('email') }}</p>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        <p class="mt-6 text-center text-sm text-slate-500">
            Have questions? Visit our
            <a class="text-primary font-semibold hover:underline" href="{{ route('help') }}">FAQ guide</a>
            or
            <a class="text-primary font-semibold hover:underline" href="{{ route('contact') }}">contact our support team</a>.
        </p>
    </div>
</section>
@endsection
