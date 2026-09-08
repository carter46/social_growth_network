<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            $siteName = $siteName ?? config('app.name', 'Social Growth Network');
            $defaultDescription = ($siteBranding['meta_description'] ?? null)
                ?: ($siteName.' — digital campaigns and growth packages with secure checkout.');
            $defaultOgDescription = 'Browse predefined campaign packages, pick a plan, and launch in minutes.';
            $pageTitle = trim($__env->yieldContent('title') ?: '');
            $resolvedTitle = $pageTitle !== '' ? ($pageTitle.' | '.$siteName) : $siteName;
            $resolvedOgTitle = $__env->hasSection('og_title')
                ? trim($__env->yieldContent('og_title'))
                : $resolvedTitle;
            $resolvedDescription = trim($__env->yieldContent('meta_description') ?: $defaultDescription);
            $resolvedOgDescription = $__env->hasSection('og_description')
                ? trim($__env->yieldContent('og_description'))
                : ($__env->yieldContent('meta_description') ?: $defaultOgDescription);
            $logoUrl = $footer->logoLightUrl
                ?? $footer->logoDarkUrl
                ?? asset('assets/images/originla_logo.png');
            $isHome = request()->routeIs('home');
            $navServices = $isHome ? '#services' : route('services');
            $navHow = $isHome ? '#how-it-works' : route('home').'#how-it-works';
            $navCreators = $isHome ? '#creators' : route('home').'#creators';
            $navAgents = $isHome ? '#agents' : route('home').'#agents';
            $navFaq = $isHome ? '#faq' : route('help');
        @endphp
        <title>{{ $resolvedTitle }}</title>
        <meta name="description" content="{{ $resolvedDescription }}">
        <link rel="canonical" href="{{ url()->current() }}">
        @include('partials.branding.social-meta', [
            'ogTitleOverride' => $resolvedOgTitle,
            'ogDescriptionOverride' => $resolvedOgDescription,
            'ogImageOverride' => trim($__env->yieldContent('og_image') ?: ''),
        ])
        @include('partials.branding.head-icons')

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @include('partials.tracking.head')
    </head>
    <body class="marketing-site bg-white text-slate-900 font-sans antialiased selection:bg-primary selection:text-white" x-data="mobileNav" @keydown.escape.window="close()">
        @include('partials.tracking.body-start')
        <header class="sticky top-0 z-50 bg-white/95 backdrop-blur-md border-b border-slate-200/80">
            <nav class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between gap-3">
                <div class="flex items-center gap-8 min-w-0">
                    <a class="flex items-center gap-2.5 min-w-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary rounded-lg" href="{{ route('home') }}">
                        <img src="{{ $logoUrl }}" alt="{{ $siteName }}" class="h-10 sm:h-11 w-auto max-w-[200px] sm:max-w-[220px] object-contain">
                        <span class="sr-only">{{ $siteName }}</span>
                    </a>

                    <div class="hidden lg:flex items-center space-x-1 text-[15px] font-medium text-slate-600">
                        <a class="px-3.5 py-2 rounded-md hover:text-primary hover:bg-slate-50 transition-colors" href="{{ $navServices }}">Services</a>
                        <a class="px-3.5 py-2 rounded-md hover:text-primary hover:bg-slate-50 transition-colors" href="{{ $navHow }}">How It Works</a>
                        <a class="px-3.5 py-2 rounded-md hover:text-primary hover:bg-slate-50 transition-colors" href="{{ $navCreators }}">For Creators</a>
                        <a class="px-3.5 py-2 rounded-md hover:text-primary hover:bg-slate-50 transition-colors" href="{{ $navAgents }}">For Agents</a>
                        <a class="px-3.5 py-2 rounded-md hover:text-primary hover:bg-slate-50 transition-colors" href="{{ $navFaq }}">FAQ</a>
                    </div>
                </div>

                <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                    @auth
                        <a class="text-[15px] font-medium text-slate-700 hover:text-slate-950 px-3 py-2 rounded-lg transition-colors" href="{{ route('dashboard') }}">Dashboard</a>
                    @else
                        <a class="hidden sm:inline-flex text-[15px] font-medium text-slate-700 hover:text-slate-950 px-4 py-2 rounded-lg transition-colors" href="{{ route('login') }}">Log In</a>
                        <a class="hidden sm:inline-flex items-center justify-center bg-primary hover:bg-primary-hover text-white text-[15px] font-semibold px-5 py-2.5 rounded-lg shadow-sm hover:shadow transition-all" href="{{ route('register') }}">
                            Get Started
                        </a>
                    @endauth

                    <button
                        type="button"
                        class="lg:hidden p-2 rounded-lg text-slate-700 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                        @click="open = true"
                        :aria-expanded="open.toString()"
                        aria-controls="marketing-mobile-menu"
                        aria-label="Open menu"
                    >
                        <x-ui.icon name="menu" class="w-7 h-7" />
                    </button>
                </div>
            </nav>
        </header>

        <div
            id="marketing-mobile-menu"
            x-show="open"
            x-cloak
            class="fixed inset-0 z-[60] flex items-center justify-center p-4 lg:hidden"
            role="dialog"
            aria-modal="true"
            aria-label="Site menu"
            @keydown.escape.window="close()"
        >
            <div
                class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm"
                x-show="open"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="close()"
            ></div>

            <div
                class="relative w-full max-w-sm overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
                x-show="open"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-[0.92] -translate-y-4"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                x-transition:leave-end="opacity-0 scale-[0.92] -translate-y-4"
                @click.stop
            >
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                    <a class="flex items-center gap-2" href="{{ route('home') }}" @click="close()">
                        <img src="{{ $logoUrl }}" alt="{{ $siteName }}" class="h-10 w-auto max-w-[180px] object-contain">
                    </a>
                    <button
                        type="button"
                        class="p-2 rounded-xl text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                        @click="close()"
                        aria-label="Close menu"
                    >
                        <x-ui.icon name="close" class="w-6 h-6" />
                    </button>
                </div>

                <nav class="flex flex-col p-4 gap-1">
                    <a class="px-4 py-3 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-primary transition-colors" href="{{ $navServices }}" @click="close()">Services</a>
                    <a class="px-4 py-3 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-primary transition-colors" href="{{ $navHow }}" @click="close()">How It Works</a>
                    <a class="px-4 py-3 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-primary transition-colors" href="{{ $navCreators }}" @click="close()">For Creators</a>
                    <a class="px-4 py-3 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-primary transition-colors" href="{{ $navAgents }}" @click="close()">For Agents</a>
                    <a class="px-4 py-3 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-primary transition-colors" href="{{ $navFaq }}" @click="close()">FAQ</a>
                    <a class="px-4 py-3 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-primary transition-colors" href="{{ route('about') }}" @click="close()">About</a>
                    <a class="px-4 py-3 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-primary transition-colors" href="{{ route('help') }}" @click="close()">Help</a>

                    @auth
                        <a class="mt-2 px-4 py-3 rounded-xl text-sm font-bold text-center border border-slate-200 text-slate-800 hover:bg-slate-50 transition-colors" href="{{ route('dashboard') }}" @click="close()">Dashboard</a>
                    @else
                        <a class="mt-2 px-4 py-3 rounded-xl text-sm font-medium text-center text-slate-700 hover:bg-slate-50 transition-colors" href="{{ route('login') }}" @click="close()">Log In</a>
                        <a class="px-4 py-3 rounded-xl bg-primary text-white text-sm font-bold text-center hover:bg-primary-hover transition-colors" href="{{ route('register') }}" @click="close()">Get Started</a>
                    @endauth
                </nav>
            </div>
        </div>

        <main class="marketing-main">
            @yield('content')
        </main>

        <footer class="bg-white border-t border-slate-200">
            <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-20">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-8 lg:gap-12 mb-12">
                    <div class="col-span-2 md:col-span-1">
                        <a class="inline-block mb-4" href="{{ route('home') }}">
                            <img src="{{ $logoUrl }}" alt="{{ $siteName }}" class="h-9 w-auto max-w-[180px] object-contain">
                        </a>
                        <p class="text-slate-500 text-sm leading-relaxed mb-4">
                            {{ $footer->tagline ?? ($siteBranding['tagline'] ?? 'The modern marketplace for structured digital campaigns, verified tasks, and real results.') }}
                        </p>
                        <x-ui.social-links :links="$footer->socialLinks" class="flex flex-wrap items-center gap-3 mb-4" />
                        <p class="text-xs text-slate-400">
                            © {{ now()->year }} {{ $siteName }}. All rights reserved.
                        </p>
                    </div>
                    <div>
                        <h4 class="font-bold text-slate-900 text-sm tracking-wider uppercase mb-4 font-display">Platform</h4>
                        <ul class="space-y-2.5 text-sm text-slate-600">
                            <li><a class="hover:text-primary transition-colors" href="{{ route('services') }}">Services</a></li>
                            <li><a class="hover:text-primary transition-colors" href="{{ route('home') }}#how-it-works">How It Works</a></li>
                            <li><a class="hover:text-primary transition-colors" href="{{ route('home') }}#creators">For Creators</a></li>
                            <li><a class="hover:text-primary transition-colors" href="{{ route('home') }}#agents">For Agents</a></li>
                            <li><a class="hover:text-primary transition-colors" href="{{ route('help') }}">FAQ</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-bold text-slate-900 text-sm tracking-wider uppercase mb-4 font-display">Support</h4>
                        <ul class="space-y-2.5 text-sm text-slate-600">
                            <li><a class="hover:text-primary transition-colors" href="{{ route('help') }}">Help Center</a></li>
                            <li><a class="hover:text-primary transition-colors" href="{{ route('contact') }}">Contact</a></li>
                            <li><a class="hover:text-primary transition-colors" href="{{ route('legal', ['doc' => 'terms']) }}">Terms of Service</a></li>
                            <li><a class="hover:text-primary transition-colors" href="{{ route('legal', ['doc' => 'privacy']) }}">Privacy Policy</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-bold text-slate-900 text-sm tracking-wider uppercase mb-4 font-display">Account</h4>
                        <ul class="space-y-2.5 text-sm text-slate-600">
                            @auth
                                <li><a class="hover:text-primary transition-colors" href="{{ route('dashboard') }}">Dashboard</a></li>
                                <li><a class="hover:text-primary transition-colors" href="{{ route('dashboard.support.create') }}">Open a ticket</a></li>
                            @else
                                <li><a class="hover:text-primary transition-colors" href="{{ route('login') }}">Log In</a></li>
                                <li><a class="hover:text-primary transition-colors" href="{{ route('register') }}">Register</a></li>
                                <li><a class="hover:text-primary transition-colors" href="{{ route('register') }}">Creator Portal</a></li>
                                <li><a class="hover:text-primary transition-colors" href="{{ route('register.agent') }}">Agent Dashboard</a></li>
                            @endauth
                        </ul>
                    </div>
                </div>

                <div class="pt-8 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-400">
                    <p>Verified delivery and fixed predefined campaign packages.</p>
                    <div class="flex flex-wrap items-center justify-center gap-4">
                        <a class="hover:text-slate-600 transition-colors" href="{{ route('legal') }}">Legal</a>
                        <a class="hover:text-slate-600 transition-colors" href="{{ route('help') }}">Help</a>
                        <a class="hover:text-slate-600 transition-colors" href="{{ route('contact') }}">Contact</a>
                    </div>
                </div>
            </div>
        </footer>

        <x-ui.toast />

        @include('partials.marketing.live-chat-widget')

        @guest
            @if (request()->routeIs('home'))
                @include('partials.auth.google-gis', ['mode' => 'one_tap', 'surface' => 'home'])
            @endif
        @endguest

        @include('partials.tracking.body-end')

        @include('partials.pwa.install-modal')

        @RegisterServiceWorkerScript
    </body>
</html>
