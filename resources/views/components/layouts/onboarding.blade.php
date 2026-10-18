@props([
    'title' => null,
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.site-currency-meta')

    <title>{{ $title ?? ('Get started | '.config('app.name')) }}</title>
    @include('partials.branding.head-icons')
    @include('partials.branding.social-meta')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.tracking.head')
</head>
@php
    $site = $siteName ?? config('app.name');
    $logo = $footer?->logoLightUrl ?? $footer?->logoDarkUrl ?? null;
@endphp
<body class="marketing-site min-h-screen bg-slate-50 text-slate-900 font-sans antialiased">
    @include('partials.tracking.body-start')

    <div class="mx-auto flex min-h-screen w-full max-w-2xl flex-col px-5 py-6 sm:px-8 sm:py-10">
        <div class="mb-10 flex items-center justify-between gap-4">
            <span class="inline-flex items-center">
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $site }}" class="h-10 w-auto max-w-[180px] object-contain">
                @else
                    <span class="font-display text-lg font-bold">{{ $site }}</span>
                @endif
            </span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm font-medium text-slate-500 hover:text-slate-900 transition-colors">Sign out</button>
            </form>
        </div>

        <main class="flex-1">
            {{ $slot }}
        </main>
    </div>

    <x-ui.toast />
    @include('partials.tracking.body-end')
    @include('partials.dashboard.unregister-service-worker')
</body>
</html>
