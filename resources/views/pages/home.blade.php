@extends('layouts.marketing')

@section('title', $siteHeading ?? 'Grow your social presence')

@section('content')
    @php
        $ecosystemItems = $ecosystemItems ?? [];
        $brandName = $siteName ?? config('app.name', 'Social Growth Network');
        $faqs = [
            [
                'q' => 'What is '.$brandName.'?',
                'a' => $brandName.' helps creators and brands grow on social platforms with ready-to-buy Instagram, TikTok, YouTube, Twitter/X, and Facebook growth packs.',
            ],
            [
                'q' => 'Which platforms do you support?',
                'a' => 'We currently offer packs for Instagram, TikTok, YouTube, Twitter/X, and Facebook — spanning growth and engagement outcomes.',
            ],
            [
                'q' => 'How do I buy a social media service?',
                'a' => 'Open Services, pick a pack, choose a plan, then check out with your wallet, card/transfer (Monnify), or manual bank transfer when enabled.',
            ],
            [
                'q' => 'Where do my purchased services appear?',
                'a' => 'After payment, your pack shows under My Tools and My Orders in your dashboard so you can track setup and delivery.',
            ],
            [
                'q' => 'How do I get help with an order?',
                'a' => 'Use Help or Contact, or open a support ticket from your dashboard. Include your order or service name so we can assist faster.',
            ],
        ];

        $heroSlides = [
            asset('assets/images/homeslider1.jpg'),
            asset('assets/images/homeslider2.jpg'),
            asset('assets/images/homeslider3.jpg'),
        ];
    @endphp

    <section
        class="relative isolate overflow-hidden flex items-center pt-24 pb-12 sm:pt-32 sm:pb-24 lg:pt-36 lg:pb-36"
        style="min-height: calc(100dvh - 5rem);"
        x-data="{
            current: 0,
            timer: null,
            init() {
                this.timer = setInterval(() => this.current = (this.current + 1) % {{ count($heroSlides) }}, 6000);
            },
            destroy() {
                if (this.timer) clearInterval(this.timer);
            }
        }"
    >
        @foreach($heroSlides as $index => $slide)
            <div
                x-show="current === {{ $index }}"
                x-transition:enter="transition-opacity duration-1000 ease-out"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity duration-1000 ease-in"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="pointer-events-none absolute inset-0 z-0 bg-cover bg-center bg-no-repeat"
                style="background-image: url('{{ $slide }}'); @if($index > 0) display: none; @endif"
                aria-hidden="true"
            ></div>
        @endforeach
        <div
            class="pointer-events-none absolute inset-0 z-[1]"
            style="background: linear-gradient(180deg, rgba(15, 23, 42, 0.78) 0%, rgba(15, 23, 42, 0.72) 50%, rgba(15, 23, 42, 0.82) 100%);"
            aria-hidden="true"
        ></div>
        <div class="pointer-events-none absolute top-0 right-0 z-[1] w-[600px] h-[600px] bg-primary/15 blur-[140px] rounded-full" aria-hidden="true"></div>
        <div class="pointer-events-none absolute bottom-0 left-0 z-[1] w-[500px] h-[500px] bg-accent/10 blur-[120px] rounded-full" aria-hidden="true"></div>
        <div class="relative z-10 w-full max-w-marketing mx-auto px-5 sm:px-6">
            <div class="mx-auto max-w-3xl text-center">
                <p class="mb-4 text-sm font-semibold uppercase tracking-[0.2em] text-primary-light">{{ $brandName }}</p>
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold mb-5 sm:mb-7 tracking-tight text-white leading-[1.15] font-display">
                    {{ $siteHeading ?? 'Grow your social presence' }}
                </h1>
                <p class="mx-auto max-w-xl text-slate-400 text-sm sm:text-base lg:text-lg mb-8 sm:mb-10 leading-relaxed">
                    {{ $siteTagline ?? 'Instagram, TikTok, YouTube, Twitter/X, and Facebook growth packs — clear deliverables, secure checkout.' }}
                </p>
                <div class="mx-auto flex max-w-md flex-col gap-3 sm:flex-row sm:justify-center">
                    <a class="px-6 py-3 text-center text-sm sm:text-base bg-primary hover:bg-accent text-white font-bold rounded-xl shadow-xl transition-all hover:scale-[1.02] animate-glow" href="{{ route('services') }}">
                        Browse social services
                    </a>
                    <a class="px-6 py-3 text-center text-sm sm:text-base glassmorphism hover:bg-white/10 text-white font-bold rounded-xl border border-white/20 transition-all" href="{{ route('register') }}">
                        Create account
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="py-16 sm:py-20 lg:py-24 bg-slate-900/30">
        <div class="max-w-marketing mx-auto px-5 sm:px-6">
            <div class="text-center mb-10 sm:mb-14">
                <h2 class="text-3xl sm:text-4xl font-bold mb-3 font-display">Social media services</h2>
                <p class="text-slate-400 text-base sm:text-lg max-w-xl mx-auto">Only our social growth and engagement packs — pick a platform and check out when you are ready.</p>
            </div>

            <div x-data="ecosystemSlider" class="relative">
                @if(count($ecosystemItems) > 0)
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($ecosystemItems as $item)
                            <a href="{{ $item['href'] ?? route('services') }}" class="group block rounded-2xl border border-white/10 bg-slate-900/50 p-6 transition hover:border-primary/40 hover:bg-slate-900/80">
                                <h3 class="text-lg font-bold text-white group-hover:text-primary-light">{{ $item['title'] ?? $item['label'] ?? 'Service' }}</h3>
                                @if(!empty($item['subtitle']) || !empty($item['short_description']))
                                    <p class="mt-2 text-sm text-slate-400">{{ $item['subtitle'] ?? $item['short_description'] }}</p>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach([
                            ['title' => 'Instagram Growth Pack', 'blurb' => 'Audience growth for Instagram creators and brands.'],
                            ['title' => 'TikTok Engagement Boost', 'blurb' => 'Boost interaction and reach on TikTok.'],
                            ['title' => 'YouTube Views Lite', 'blurb' => 'Starter views pack for YouTube channels.'],
                            ['title' => 'Twitter Audience Pack', 'blurb' => 'Grow your Twitter/X following with clear deliverables.'],
                            ['title' => 'Facebook Growth Pack', 'blurb' => 'Facebook audience growth for pages and profiles.'],
                        ] as $pack)
                            <a href="{{ route('services') }}" class="group block rounded-2xl border border-white/10 bg-slate-900/50 p-6 transition hover:border-primary/40 hover:bg-slate-900/80">
                                <h3 class="text-lg font-bold text-white group-hover:text-primary-light">{{ $pack['title'] }}</h3>
                                <p class="mt-2 text-sm text-slate-400">{{ $pack['blurb'] }}</p>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>

    <section class="py-16 sm:py-20">
        <div class="max-w-marketing mx-auto px-5 sm:px-6">
            <div class="text-center mb-10">
                <h2 class="text-3xl font-bold font-display mb-3">Frequently asked questions</h2>
                <p class="text-slate-400">Quick answers about buying social media services on {{ $brandName }}.</p>
            </div>
            <div class="mx-auto max-w-3xl space-y-4">
                @foreach($faqs as $faq)
                    <details class="group rounded-xl border border-white/10 bg-slate-900/40 px-5 py-4">
                        <summary class="cursor-pointer list-none font-semibold text-white">{{ $faq['q'] }}</summary>
                        <p class="mt-3 text-sm text-slate-400 leading-relaxed">{{ $faq['a'] }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    <section class="py-16 sm:py-20 bg-slate-900/40">
        <div class="max-w-marketing mx-auto px-5 sm:px-6 text-center">
            <h2 class="text-3xl font-bold font-display mb-4">Ready to grow?</h2>
            <p class="text-slate-400 mb-8 max-w-xl mx-auto">Browse social packs and check out securely from your dashboard.</p>
            <a href="{{ route('services') }}" class="inline-flex px-8 py-3 bg-primary hover:bg-accent text-white font-bold rounded-xl transition">View services</a>
        </div>
    </section>
@endsection
