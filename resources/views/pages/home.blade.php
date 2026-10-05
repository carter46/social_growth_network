@extends('layouts.marketing')

@section('title', $siteHeading ?? 'Digital Campaign Marketplace')

@section('content')
@php
    $brandName = $siteName ?? config('app.name', 'Social Growth Network');
    $categoryCards = collect($categoryCards ?? [])->values();
    $marketplaceCatalog = $marketplaceCatalog ?? ['filters' => [], 'products' => ['all' => []]];
    $filterCategories = collect($marketplaceCatalog['filters'] ?? []);
    $marketplaceCards = $categoryCards
        ->filter(fn ($card) => ! in_array(($card['slug'] ?? ''), ['social-media', 'youtube'], true))
        ->take(5)
        ->values();
    $youtubeCatalog = $youtubeCatalog ?? ['featured' => null, 'others' => []];
    $watchHours = $youtubeCatalog['featured'] ?? null;
    $youtubeOthers = collect($youtubeCatalog['others'] ?? []);
    $watchHoursHref = is_array($watchHours) && filled($watchHours['href'] ?? null)
        ? $watchHours['href']
        : '#youtube-services';
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
    $agentTaskPreview = is_array($agentTaskPreview ?? null) ? $agentTaskPreview : null;
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
            <h1 class="text-[length:clamp(1.625rem,7.8vw,2rem)] sm:text-4xl md:text-5xl lg:text-[52px] font-extrabold text-slate-900 tracking-tight leading-[1.15] text-balance mb-4 sm:mb-5 font-display">
                {!! $ytWord('Pay People to Subscribe, Watch, Like, Follow and Comment on Your Videos and Posts.') !!}
            </h1>
            <p class="text-base sm:text-lg lg:text-xl text-slate-600 font-normal leading-relaxed mb-8 sm:mb-10 max-w-xl mx-auto">
                Buy YouTube Watch Hours, views, likes, and comments for your videos and posts.
            </p>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-3 sm:gap-4">
                <a
                    class="inline-flex items-center justify-center gap-1.5 sm:gap-2 bg-red-600 hover:bg-red-700 text-white font-semibold text-sm sm:text-base px-5 py-3 sm:px-6 sm:py-3.5 rounded-lg shadow-sm hover:shadow transition-all"
                    href="{{ route('register') }}"
                >
                    <span>Buy Watch Hours</span>
                    <span class="material-symbols-outlined text-base sm:text-lg" aria-hidden="true">arrow_forward</span>
                </a>
                <a
                    class="inline-flex items-center justify-center bg-white hover:bg-slate-50 text-slate-800 font-medium text-sm sm:text-base px-5 py-3 sm:px-6 sm:py-3.5 rounded-lg border border-slate-200 transition-all"
                    href="{{ route('agents') }}"
                >
                    Watch &amp; Earn
                </a>
            </div>
        </div>
    </div>
</section>

{{-- 2. YouTube services: featured Watch Hours + supporting products --}}
<section class="py-20 lg:py-28 bg-slate-50 border-b border-slate-100" id="youtube-services">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-12 lg:mb-16 max-w-2xl">
            <span class="text-slate-500 font-bold text-xs sm:text-sm tracking-wider uppercase mb-2 block">YouTube services</span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight font-display">{!! $ytWord('Built around YouTube growth.') !!}</h2>
            <p class="text-slate-600 text-base sm:text-lg mt-2">Order watch hours, views, likes, and comments for your YouTube videos.</p>
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
                                    From ₦{{ number_format((float) $watchHours['from_price'], 0) }}
                                </span>
                            @endif
                            <a
                                class="inline-flex items-center justify-center gap-2 bg-red-600 hover:bg-red-700 text-white font-semibold text-sm sm:text-base px-5 py-3 rounded-lg shadow-sm transition-all w-full sm:w-auto"
                                href="{{ $watchHoursHref }}"
                            >
                                <span>Buy Watch Hours</span>
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
                <p class="text-slate-500 text-sm mt-1">Views, likes, and comments, each with a clear price.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
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
                                    From ₦{{ number_format((float) $product['from_price'], 0) }}
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
                ['n' => '01', 'icon' => 'category', 'title' => 'Choose a service', 'body' => 'Pick YouTube Watch Hours or another service, then choose a package. You’ll see the price before you pay.', 'foot' => 'Step 1 · No haggling'],
                ['n' => '02', 'icon' => 'link', 'title' => 'Provide campaign details', 'body' => 'Add the link to your post or video and any campaign instructions at checkout.', 'foot' => 'Step 2 · Quick setup'],
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

{{-- 4. Other platforms (non-YouTube filter catalog) --}}
<section
    class="py-20 lg:py-28 bg-[#F8FAFC] border-b border-slate-100"
    id="services"
    x-data="{
        filter: 'all',
        catalog: @js($marketplaceCatalog),
        tones: @js($badgeTones),
        icons: @js($badgeIcons),
        get products() {
            return (this.catalog.products && this.catalog.products[this.filter]) ? this.catalog.products[this.filter] : [];
        },
        tone(i) { return this.tones[i % this.tones.length]; },
        icon(i) { return this.icons[i % this.icons.length]; }
    }"
>
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-8 sm:mb-10">
            <span class="text-slate-500 font-bold text-xs sm:text-sm tracking-wider uppercase mb-2 block">Other platforms</span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight font-display">Need other social media services?</h2>
            <p class="text-slate-600 text-base sm:text-lg mt-2 max-w-xl">We also offer services for Facebook, Instagram, TikTok, and X (Twitter).</p>
        </div>

        @if($filterCategories->isNotEmpty())
            <div class="mb-10 w-full min-w-0 overflow-x-hidden">
                <div class="overflow-x-auto scrollbar-hide overscroll-x-contain bg-slate-100 rounded-xl px-1">
                    <div class="flex items-center gap-2 p-1.5 w-max min-w-full sm:min-w-0">
                        <button type="button" @click="filter = 'all'" :class="filter === 'all' ? 'bg-white text-slate-900 shadow-sm font-semibold' : 'text-slate-600 font-medium hover:text-slate-900'" class="px-4 py-2 rounded-lg text-sm transition-all shrink-0">All</button>
                        @foreach($filterCategories as $cat)
                            <button
                                type="button"
                                @click="filter = '{{ $cat['slug'] }}'"
                                :class="filter === '{{ $cat['slug'] }}' ? 'bg-white text-slate-900 shadow-sm font-semibold' : 'text-slate-600 font-medium hover:text-slate-900'"
                                class="px-4 py-2 rounded-lg text-sm transition-all shrink-0"
                            >{{ $cat['label'] ?? $cat['slug'] }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <template x-if="products.length > 0">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                <template x-for="(product, index) in products" :key="filter + '-' + product.id">
                    <div class="group bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between">
                        <div>
                            <div class="relative h-48 w-full overflow-hidden bg-slate-100">
                                <template x-if="product.hero_url">
                                    <img :src="product.hero_url" :alt="product.title" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
                                </template>
                                <template x-if="!product.hero_url">
                                    <div class="w-full h-full bg-gradient-to-br from-primary/25 via-slate-200 to-slate-100"></div>
                                </template>
                                <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent"></div>
                                <span class="absolute top-3 left-3 inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/95 backdrop-blur-md text-xs font-bold shadow-sm" :class="tone(index)">
                                    <span class="material-symbols-outlined text-sm" aria-hidden="true" x-text="icon(index)"></span>
                                    <span x-text="product.category_label"></span>
                                </span>
                            </div>
                            <div class="p-5">
                                <h3 class="text-xl font-bold text-slate-900 mb-2 group-hover:text-primary transition-colors font-display">
                                    <a :href="product.href" x-text="product.title"></a>
                                </h3>
                                <p class="text-slate-600 text-sm leading-relaxed mb-4" x-text="product.short_description"></p>
                            </div>
                        </div>
                        <div class="px-5 pb-5 pt-3 border-t border-slate-100 flex items-center justify-between mt-auto">
                            <span class="text-xs font-medium text-slate-500" x-text="product.from_price ? ('From ₦' + Number(product.from_price).toLocaleString('en-NG')) : 'See packages'"></span>
                            <a class="inline-flex items-center gap-1 text-primary font-semibold text-sm hover:underline group-hover:translate-x-0.5 transition-transform" :href="product.href">View package →</a>
                        </div>
                    </div>
                </template>
            </div>
        </template>

        <div x-show="products.length === 0" x-cloak>
            @if($marketplaceCards->isNotEmpty())
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 lg:gap-8">
                    @foreach($marketplaceCards as $card)
                        @php
                            $tone = $badgeTones[$loop->index % count($badgeTones)];
                            $icon = $badgeIcons[$loop->index % count($badgeIcons)];
                            $image = $card['card_image'] ?? $card['banner_image'] ?? null;
                            $href = $card['href'] ?? route('services');
                        @endphp
                        <div class="group bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between">
                            <div>
                                <div class="relative h-48 w-full overflow-hidden bg-slate-100">
                                    @if($image)
                                        <img src="{{ $image }}" alt="{{ $card['label'] ?? 'Campaign' }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
                                    @else
                                        <div class="w-full h-full bg-gradient-to-br from-primary/25 via-slate-200 to-slate-100"></div>
                                    @endif
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent"></div>
                                    <span class="absolute top-3 left-3 inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/95 backdrop-blur-md {{ $tone }} text-xs font-bold shadow-sm">
                                        <span class="material-symbols-outlined text-sm" aria-hidden="true">{{ $icon }}</span>
                                        {{ $card['label'] ?? 'Campaigns' }}
                                    </span>
                                </div>
                                <div class="p-5">
                                    <h3 class="text-xl font-bold text-slate-900 mb-2 font-display">{{ $card['label'] ?? 'Campaigns' }}</h3>
                                    <p class="text-slate-600 text-sm leading-relaxed mb-4">{{ $card['short_description'] ?? $card['hero_subtitle'] ?? 'Views, likes, and comments with clear prices.' }}</p>
                                </div>
                            </div>
                            <div class="px-5 pb-5 pt-3 border-t border-slate-100 flex items-center justify-between mt-auto">
                                <span class="text-xs font-medium text-slate-500">See packages</span>
                                <a class="inline-flex items-center gap-1 text-primary font-semibold text-sm hover:underline" href="{{ $href }}">Explore Packages →</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-slate-500 text-center py-12">No services are available here right now. Please check back soon.</p>
            @endif
        </div>
    </div>
</section>

{{-- 5. Agent recruitment (final band) --}}
<section class="py-20 lg:py-24 bg-slate-50 border-y border-slate-200/60" id="agents">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 items-center">
            <div class="{{ $agentTaskPreview ? 'lg:col-span-7' : 'lg:col-span-12' }}">
                <span class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">
                    <span class="material-symbols-outlined text-base text-red-600" aria-hidden="true">handshake</span>
                    For Agents
                </span>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight font-display mb-4">
                    Earn by completing available digital tasks
                </h2>
                <p class="text-slate-600 text-base sm:text-lg leading-relaxed mb-4 max-w-xl">
                    Join {{ $brandName }} as an Agent. When campaigns are open, you can take on tasks like watching a YouTube video for a set time or engaging with a social post. Follow each task’s instructions, then submit your proof.
                </p>
                <p class="text-slate-500 text-sm leading-relaxed mb-8 max-w-xl">
                    Approved tasks can earn rewards. Earnings and open tasks are not guaranteed; rewards depend on task requirements and approval.
                </p>
                <div class="flex flex-wrap items-center gap-3 sm:gap-4">
                    <a
                        class="inline-flex items-center justify-center gap-2 bg-red-600 hover:bg-red-700 text-white font-semibold text-sm sm:text-base px-5 py-3 sm:px-6 sm:py-3.5 rounded-lg shadow-sm transition-all"
                        href="{{ route('agents') }}"
                    >
                        <span>Learn more</span>
                        <span class="material-symbols-outlined text-lg" aria-hidden="true">arrow_forward</span>
                    </a>
                    <a
                        class="inline-flex items-center justify-center bg-white hover:bg-slate-50 text-slate-800 font-medium text-sm sm:text-base px-5 py-3 sm:px-6 sm:py-3.5 rounded-lg border border-slate-200 transition-all"
                        href="{{ route('register.agent') }}"
                    >
                        Start earning
                    </a>
                </div>
            </div>
            @if($agentTaskPreview)
                <div class="lg:col-span-5 w-full">
                    <div class="bg-white rounded-2xl p-5 sm:p-6 border border-slate-200 shadow-sm">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-200/80 text-xs font-semibold text-slate-500">
                            <span class="flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                Example open task
                            </span>
                            <span class="bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded border border-emerald-200 font-bold">{{ $agentTaskPreview['badge'] ?? 'Available' }}</span>
                        </div>
                        <div class="py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">{{ $agentTaskPreview['label'] ?? 'Campaign' }}</p>
                            <h3 class="font-bold text-slate-900 text-base mb-1">{{ $agentTaskPreview['title'] }}</h3>
                            <div class="flex items-center justify-between bg-slate-50 px-3 py-2 rounded-lg border border-slate-200/70 mb-3 mt-3">
                                <span class="text-xs text-slate-600 font-medium">Reward</span>
                                <span class="text-sm font-extrabold text-emerald-600">
                                    {{ $agentTaskPreview['reward'] }}
                                    @if(! empty($agentTaskPreview['time']) && $agentTaskPreview['time'] !== 'Flexible')
                                        <span class="font-semibold text-slate-400">· {{ $agentTaskPreview['time'] }}</span>
                                    @endif
                                </span>
                            </div>
                        </div>
                        <a href="{{ route('agents') }}" class="w-full py-2.5 bg-slate-100 hover:bg-slate-900 hover:text-white text-slate-700 font-semibold text-xs rounded-lg transition-colors flex items-center justify-center gap-1">
                            <span>Learn how tasks work</span>
                            <span class="material-symbols-outlined text-sm" aria-hidden="true">arrow_forward</span>
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</section>

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
