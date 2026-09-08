@php
    $breadcrumbs = $breadcrumbs ?? [];
    $title = $title ?? '';
    $subtitle = $subtitle ?? null;
    $image = $image ?? null;
    $cta = $cta ?? null;
    $defaultHero = asset('assets/images/Image_ro410gro410gro41.png');
    $heroUrl = $defaultHero;
    if (is_string($image) && $image !== '') {
        $trimmed = trim($image);
        if (preg_match('#^(https?:)?//#i', $trimmed)) {
            $heroUrl = $trimmed;
        } else {
            $relative = ltrim(str_replace('\\', '/', $trimmed), '/');
            if (is_file(public_path($relative))) {
                $heroUrl = asset($relative);
            } elseif (str_starts_with($relative, 'storage/')) {
                $heroUrl = asset($relative);
            } else {
                $heroUrl = asset($relative);
            }
        }
    }
@endphp
{{-- Compact page hero. Sticky marketing nav sits above this in document flow. --}}
<header class="relative isolate overflow-hidden border-b border-slate-200 pt-10 sm:pt-12 pb-10 sm:pb-12 mb-8 sm:mb-10">
    {{-- Decorative gradient base first, photo above it — .marketing-page-hero-bg is opaque and must not cover the image --}}
    <div class="pointer-events-none absolute inset-0 z-0 marketing-page-hero-bg" aria-hidden="true"></div>
    <div
        class="pointer-events-none absolute inset-0 z-[1] bg-cover bg-center bg-no-repeat"
        style="background-image: url('{{ $heroUrl }}')"
        aria-hidden="true"
    ></div>
    <div
        class="pointer-events-none absolute inset-0 z-[2]"
        style="background: linear-gradient(180deg, rgba(15, 23, 42, 0.62) 0%, rgba(15, 23, 42, 0.54) 45%, rgba(15, 23, 42, 0.72) 100%);"
        aria-hidden="true"
    ></div>
    <div class="pointer-events-none absolute top-0 right-0 z-[2] w-[420px] h-[420px] bg-primary/20 blur-[120px] rounded-full" aria-hidden="true"></div>
    <div class="pointer-events-none absolute bottom-0 left-0 z-[2] w-[320px] h-[320px] bg-accent/10 blur-[100px] rounded-full" aria-hidden="true"></div>

    <div class="relative z-10 max-w-marketing mx-auto px-5 sm:px-6">
        @if(count($breadcrumbs))
            <nav class="mb-3 text-sm text-slate-300" aria-label="Breadcrumb">
                <ol class="flex flex-wrap items-center gap-1.5">
                    @foreach($breadcrumbs as $i => $crumb)
                        <li class="inline-flex items-center gap-1.5">
                            @if($i > 0)
                                <span class="text-slate-500" aria-hidden="true">/</span>
                            @endif
                            @if(!empty($crumb['href']) && ! $loop->last)
                                <a href="{{ $crumb['href'] }}" class="hover:text-white transition-colors">{{ $crumb['label'] }}</a>
                            @else
                                <span class="{{ $loop->last ? 'text-white/90' : '' }}">{{ $crumb['label'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif

        @if($title !== '' && $title !== null)
            <h1 class="text-xl sm:text-3xl lg:text-4xl font-bold font-display text-white tracking-tight">{{ $title }}</h1>
        @endif
        @if($subtitle)
            <p class="mt-2 text-slate-300 max-w-2xl text-sm sm:text-base leading-relaxed">{{ $subtitle }}</p>
        @endif

        @if(!empty($cta['href']) && !empty($cta['label']))
            <div class="mt-5">
                <a href="{{ $cta['href'] }}"
                   class="inline-flex items-center justify-center rounded-xl bg-white px-5 py-2.5 text-sm font-semibold text-slate-900 hover:bg-primary-hover hover:text-white transition-colors shadow-lg">
                    {{ $cta['label'] }}
                </a>
            </div>
        @endif
    </div>
</header>
