@php
    $youtubeCatalog = $youtubeCatalog ?? ['featured' => null, 'others' => collect()];
    $watchHours = $youtubeCatalog['featured'] ?? null;
    $youtubeOthers = collect($youtubeCatalog['others'] ?? []);
    $watchHoursHref = is_array($watchHours) && filled($watchHours['href'] ?? null)
        ? $watchHours['href']
        : route('register');
    $youtubeFeatureImage = (is_array($watchHours) && filled($watchHours['hero_url'] ?? null))
        ? $watchHours['hero_url']
        : null;
    $ytWord = static function (string $text): string {
        return preg_replace(
            '/\bYouTube\b/u',
            '<span class="text-red-600">YouTube</span>',
            e($text)
        ) ?? e($text);
    };
@endphp

@if(is_array($watchHours))
    <div class="services-yt-featured rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-sm" id="youtube-watch-hours">
        <div class="grid grid-cols-1 xl:grid-cols-12">
            <div class="xl:col-span-6 p-8 sm:p-10 xl:p-12 flex flex-col justify-center order-2 xl:order-1">
                <span class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">
                    <span class="material-symbols-outlined text-base text-red-600" aria-hidden="true">smart_display</span>
                    Featured YouTube service
                </span>
                <h2 class="text-2xl sm:text-3xl xl:text-4xl font-extrabold text-slate-900 tracking-tight font-display mb-4">
                    {!! $ytWord($watchHours['title'] ?? 'YouTube Watch Hours') !!}
                </h2>
                <p class="text-slate-600 text-base sm:text-lg leading-relaxed mb-6 max-w-lg">
                    {{ $watchHours['short_description'] }}
                </p>
                <div class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-3 sm:gap-4">
                    @if(! empty($watchHours['from_price']))
                        <span class="text-sm font-semibold text-slate-700">
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
            <div class="xl:col-span-6 relative min-h-[220px] sm:min-h-[300px] xl:min-h-full order-1 xl:order-2 bg-slate-100">
                @if($youtubeFeatureImage)
                    <img
                        src="{{ $youtubeFeatureImage }}"
                        alt="{{ $watchHours['title'] ?? 'YouTube Watch Hours' }}"
                        class="absolute inset-0 w-full h-full object-cover"
                        loading="lazy"
                    >
                    <div class="absolute inset-0 bg-gradient-to-t from-red-950/30 via-transparent to-transparent" aria-hidden="true"></div>
                @else
                    <div class="absolute inset-0 bg-gradient-to-br from-primary/20 via-slate-200 to-slate-100" aria-hidden="true"></div>
                @endif
            </div>
        </div>
    </div>
@endif

@if($youtubeOthers->isNotEmpty())
    <div id="other-youtube-services">
        <div class="mb-4">
            <h3 class="text-lg sm:text-xl font-bold text-slate-900 font-display">Other YouTube services</h3>
            <p class="text-slate-500 text-sm mt-1">Views, likes, and comments for your YouTube videos.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-5">
            @foreach($youtubeOthers as $product)
                @include('partials.catalog.marketplace-product-card', ['product' => $product])
            @endforeach
        </div>
    </div>
@endif
