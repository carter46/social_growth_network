<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            $siteName = $siteName ?? config('app.name', 'Social Growth Network');
            $defaultDescription = ($siteBranding['meta_description'] ?? null)
                ?: ($siteName.' — social media growth and engagement services with secure checkout.');
            $defaultOgDescription = 'Buy social media growth packs with secure wallet or card checkout.';
            $pageTitle = trim($__env->yieldContent('title') ?: '');
            $resolvedTitle = $pageTitle !== '' ? ($pageTitle.' | '.$siteName) : $siteName;
            $resolvedOgTitle = $__env->hasSection('og_title')
                ? trim($__env->yieldContent('og_title'))
                : $resolvedTitle;
            $resolvedDescription = trim($__env->yieldContent('meta_description') ?: $defaultDescription);
            $resolvedOgDescription = $__env->hasSection('og_description')
                ? trim($__env->yieldContent('og_description'))
                : ($__env->yieldContent('meta_description') ?: $defaultOgDescription);
            $logoUrl = $footer->logoDarkUrl ?? asset('assets/images/white_originla_logo.png');
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
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@600;700&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @include('partials.tracking.head')
    </head>
    <body class="bg-dark text-slate-100 font-sans selection:bg-accent selection:text-white" x-data="mobileNav" @keydown.escape.window="close()">
        @include('partials.tracking.body-start')
        <header class="fixed top-0 w-full z-50 glassmorphism border-b border-white/10">
            <nav class="max-w-marketing mx-auto px-5 sm:px-6 h-20 flex items-center justify-between gap-3">
                <a class="flex items-center gap-2 min-w-0" href="{{ route('home') }}">
                    <img src="{{ $logoUrl }}" alt="{{ $siteName }}" class="h-10 w-auto max-w-[160px] object-contain">
                    <span class="sr-only">{{ $siteName }}</span>
                </a>

                <div class="hidden lg:flex items-center space-x-8 text-sm font-medium text-slate-300">
                    <a class="hover:text-accent transition-colors" href="{{ route('home') }}">Home</a>
                    <a class="hover:text-accent transition-colors" href="{{ route('services') }}">Services</a>
                    <a class="hover:text-accent transition-colors" href="{{ route('about') }}">About</a>
                    <a class="hover:text-accent transition-colors" href="{{ route('help') }}">Help</a>
                </div>

                <div class="flex items-center gap-2 sm:gap-4 shrink-0">
                    @auth
                        <a class="text-slate-300 hover:text-white font-medium text-sm transition-colors" href="{{ route('dashboard') }}">Dashboard</a>
                    @else
                        <a class="text-slate-300 hover:text-white font-medium text-sm transition-colors" href="{{ route('login') }}">Login</a>
                        <a class="hidden lg:inline-flex bg-primary hover:bg-accent px-5 py-2.5 rounded-full text-sm font-bold transition-all shadow-lg hover:scale-105 active:scale-95" href="{{ route('register') }}">Register</a>
                    @endauth

                    <button
                        type="button"
                        class="lg:hidden p-2 rounded-lg text-slate-200 hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
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

        {{-- Centered mobile nav modal (resmenu-style), not a sidebar --}}
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
                class="absolute inset-0 bg-black/60 backdrop-blur-sm"
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
                class="relative w-full max-w-sm overflow-hidden rounded-2xl border border-white/10 bg-elevated shadow-2xl"
                x-show="open"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-[0.92] -translate-y-4"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                x-transition:leave-end="opacity-0 scale-[0.92] -translate-y-4"
                @click.stop
            >
                <div class="flex items-center justify-between px-5 py-4 border-b border-white/10">
                    <a class="flex items-center gap-2" href="{{ route('home') }}" @click="close()">
                        <img src="{{ $logoUrl }}" alt="{{ $siteName }}" class="h-8 w-auto max-w-[140px] object-contain">
                    </a>
                    <button
                        type="button"
                        class="p-2 rounded-xl text-slate-400 hover:bg-white/10 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
                        @click="close()"
                        aria-label="Close menu"
                    >
                        <x-ui.icon name="close" class="w-6 h-6" />
                    </button>
                </div>

                <nav class="flex flex-col p-4 gap-1">
                    <a class="px-4 py-3 rounded-xl text-sm font-medium text-slate-200 hover:bg-primary/15 hover:text-accent transition-colors" href="{{ route('home') }}" @click="close()">Home</a>
                    <a class="px-4 py-3 rounded-xl text-sm font-medium text-slate-200 hover:bg-primary/15 hover:text-accent transition-colors" href="{{ route('services') }}" @click="close()">Services</a>
                    <a class="px-4 py-3 rounded-xl text-sm font-medium text-slate-200 hover:bg-primary/15 hover:text-accent transition-colors" href="{{ route('about') }}" @click="close()">About</a>
                    <a class="px-4 py-3 rounded-xl text-sm font-medium text-slate-200 hover:bg-primary/15 hover:text-accent transition-colors" href="{{ route('help') }}" @click="close()">Help</a>

                    @auth
                        <a class="mt-2 px-4 py-3 rounded-xl text-sm font-bold text-center border border-white/15 text-slate-100 hover:bg-white/5 transition-colors" href="{{ route('dashboard') }}" @click="close()">Dashboard</a>
                    @else
                        <a class="mt-2 px-4 py-3 rounded-xl bg-primary text-white text-sm font-bold text-center hover:bg-accent transition-colors" href="{{ route('register') }}" @click="close()">Register</a>
                    @endauth
                </nav>
            </div>
        </div>

        <main class="pt-20 marketing-main" style="padding-bottom: clamp(5rem, 10vw, 8rem);">
            @yield('content')
        </main>

        <footer class="bg-slate-950 pt-16 sm:pt-20 pb-10 border-t border-white/5 mt-0">
            <div class="max-w-marketing mx-auto px-5 sm:px-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 mb-16">
                    <div>
                        <div class="flex items-center gap-2 mb-6">
                            <img src="{{ $logoUrl }}" alt="{{ $siteName }}" class="h-8 w-auto max-w-[160px] object-contain">
                        </div>
                        <p class="text-slate-500 text-sm leading-relaxed">
                            {{ $footer->tagline ?? ($siteBranding['tagline'] ?? 'Social media growth and engagement services with clear deliverables and secure checkout.') }}
                        </p>
                        <x-ui.social-links :links="$footer->socialLinks" class="flex flex-wrap items-center gap-3 mt-5" />
                    </div>
                    <div>
                        <h4 class="text-white font-bold mb-6 font-display">Platform</h4>
                        <ul class="space-y-4 text-slate-500 text-sm">
                            <li><a class="hover:text-accent transition-colors" href="{{ route('home') }}">Home</a></li>
                            <li><a class="hover:text-accent transition-colors" href="{{ route('services') }}">Services</a></li>
                            <li><a class="hover:text-accent transition-colors" href="{{ route('about') }}">About</a></li>
                            <li><a class="hover:text-accent transition-colors" href="{{ route('contact') }}">Contact</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-white font-bold mb-6 font-display">Support</h4>
                        <ul class="space-y-4 text-slate-500 text-sm">
                            <li><a class="hover:text-accent transition-colors" href="{{ route('help') }}">Help Center</a></li>
                            <li><a class="hover:text-accent transition-colors" href="{{ route('contact') }}">Contact Us</a></li>
                            <li>
                                <a class="hover:text-accent transition-colors" href="{{ auth()->check() ? route('dashboard.support.create') : route('login') }}">
                                    Open a ticket
                                </a>
                            </li>
                            @auth
                                <li><a class="hover:text-accent transition-colors" href="{{ route('dashboard.support.index') }}">My tickets</a></li>
                                <li><a class="hover:text-accent transition-colors" href="{{ route('dashboard') }}">Dashboard</a></li>
                            @else
                                <li><a class="hover:text-accent transition-colors" href="{{ route('login') }}">Login</a></li>
                                <li><a class="hover:text-accent transition-colors" href="{{ route('register') }}">Register</a></li>
                            @endauth
                        </ul>
                        @php
                            $fc = $footer->contact ?? [];
                            $footerPhone = $fc['phone_support'] ?? $fc['phone_general'] ?? '';
                            $footerEmail = $fc['email_support'] ?? $fc['email_info'] ?? '';
                        @endphp
                        @if($footerPhone !== '' || $footerEmail !== '')
                            <ul class="mt-6 space-y-2 text-slate-500 text-sm">
                                @if($footerPhone !== '')
                                    <li><a class="hover:text-accent transition-colors" href="tel:{{ preg_replace('/\s+/', '', $footerPhone) }}">{{ $footerPhone }}</a></li>
                                @endif
                                @if($footerEmail !== '')
                                    <li><a class="hover:text-accent transition-colors break-all" href="mailto:{{ $footerEmail }}">{{ $footerEmail }}</a></li>
                                @endif
                            </ul>
                        @endif
                    </div>
                    <div>
                        <h4 class="text-white font-bold mb-6 font-display">Legal</h4>
                        <ul class="space-y-4 text-slate-500 text-sm">
                            <li><a class="hover:text-accent transition-colors" href="{{ route('legal') }}">Legal hub</a></li>
                            <li><a class="hover:text-accent transition-colors" href="{{ route('legal', ['doc' => 'terms']) }}">Terms of Service</a></li>
                            <li><a class="hover:text-accent transition-colors" href="{{ route('legal', ['doc' => 'privacy']) }}">Privacy Policy</a></li>
                        </ul>
                    </div>
                </div>

                <div class="pt-8 border-t border-white/5 flex flex-col md:flex-row justify-between items-center gap-4 text-slate-600 text-xs font-bold uppercase tracking-wider">
                    <p>© {{ now()->year }} {{ $siteName }}. All rights reserved.</p>
                    <div class="flex flex-wrap justify-center gap-6">
                        <a class="hover:text-white transition-colors" href="{{ route('legal', ['doc' => 'terms']) }}">Terms</a>
                        <a class="hover:text-white transition-colors" href="{{ route('legal', ['doc' => 'privacy']) }}">Privacy</a>
                        <a class="hover:text-white transition-colors" href="{{ route('help') }}">Help</a>
                        <a class="hover:text-white transition-colors" href="{{ route('contact') }}">Contact</a>
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

