@props([
    'title' => null,
    'panelImage' => null,
    'panelHeadline' => null,
    'panelCopy' => null,
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? (config('app.name').' — Sign in') }}</title>
    @include('partials.branding.head-icons')
    @include('partials.branding.social-meta')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.tracking.head')
    {{ $head ?? '' }}
</head>
@php
    $site = $siteName ?? config('app.name');
    $tagline = $siteTagline ?? 'Grow social profiles with clear deliverables and secure payment.';
    $resolvedPanelImage = $panelImage ?: asset('assets/images/creators-hero.jpg');
    $resolvedHeadline = $panelHeadline ?: 'Grow with clarity.';
    $resolvedCopy = $panelCopy ?: $tagline;
    $logoLight = $footer?->logoLightUrl ?? $footer?->logoDarkUrl ?? null;
    $logoDark = $footer?->logoDarkUrl ?? $footer?->logoLightUrl ?? null;
@endphp
<body class="marketing-site bg-white text-slate-900 font-sans antialiased min-h-screen lg:h-screen overflow-x-hidden lg:overflow-hidden selection:bg-blue-500 selection:text-white">
    @include('partials.tracking.body-start')

    <div class="flex min-h-screen lg:h-screen flex-col lg:flex-row">
        {{-- Desktop image panel (left) --}}
        <aside class="relative hidden lg:flex lg:w-1/2 overflow-hidden bg-slate-900">
            <img
                src="{{ $resolvedPanelImage }}"
                alt=""
                class="absolute inset-0 h-full w-full object-cover object-center"
            >
            <div class="absolute inset-0 z-10 bg-gradient-to-t from-slate-950/90 via-slate-950/45 to-slate-950/25"></div>
            <div class="relative z-20 flex h-full w-full flex-col justify-between p-10 xl:p-12 text-white">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-3 hover:opacity-90 transition-opacity">
                    @if($logoDark || $logoLight)
                        <img
                            src="{{ $logoDark ?? $logoLight }}"
                            alt="{{ $site }}"
                            class="h-11 w-auto max-w-[200px] object-contain rounded-lg bg-white/95 p-1.5"
                        >
                    @else
                        <span class="text-2xl font-bold tracking-tight font-display">{{ $site }}</span>
                    @endif
                </a>

                <div class="max-w-md space-y-4">
                    <h1 class="font-display text-4xl xl:text-5xl font-extrabold leading-tight tracking-tight">
                        {{ $resolvedHeadline }}
                    </h1>
                    <p class="text-lg text-slate-200 leading-relaxed">{{ $resolvedCopy }}</p>
                </div>

                <p class="text-sm text-slate-300">© {{ date('Y') }} {{ $site }}. All rights reserved.</p>
            </div>
        </aside>

        {{-- Form panel (right desktop / full mobile) — white, no image --}}
        <div class="flex flex-1 flex-col justify-center lg:justify-start px-5 py-8 sm:px-8 sm:py-10 lg:px-14 lg:py-10 xl:px-20 bg-white lg:overflow-y-auto">
            <div class="mx-auto w-full max-w-md">
                <div class="mb-6 flex items-center justify-between gap-3 lg:hidden">
                    <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 hover:text-primary transition-colors">
                        <span aria-hidden="true">←</span>
                        Back to Home
                    </a>
                    <a href="{{ route('home') }}" class="inline-flex items-center justify-end shrink-0">
                        @if($logoLight || $logoDark)
                            <img
                                src="{{ $logoLight ?? $logoDark }}"
                                alt="{{ $site }}"
                                class="h-9 w-auto max-h-10 object-contain"
                            >
                        @else
                            <span class="text-base font-bold font-display text-slate-900">{{ $site }}</span>
                        @endif
                    </a>
                </div>

                {{ $slot }}
            </div>
        </div>
    </div>

    <x-ui.toast />
    @include('partials.tracking.body-end')
    @include('partials.dashboard.unregister-service-worker')
    {{ $scripts ?? '' }}
</body>
</html>
