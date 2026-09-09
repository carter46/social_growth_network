@extends('layouts.marketing')

@section('title', ($article['title'] ?? 'Help'))

@section('content')
@php
    $sections = $article['sections'] ?? [];
    $sectionCount = count($sections);
    $related = $article['related'] ?? [];
    $actions = $article['platform_actions'] ?? [];
    $heroImage = $article['hero_image'] ?? 'assets/images/helpcenter.jpg';
    $heroUrl = asset(ltrim(str_replace('\\', '/', (string) $heroImage), '/'));
@endphp

{{-- Light article hero --}}
<section class="relative w-full bg-slate-50 border-b border-slate-100 overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-b from-slate-100/80 via-transparent to-white pointer-events-none" aria-hidden="true"></div>
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-12 relative z-10">
        <div class="flex flex-wrap items-center gap-1.5 text-sm text-slate-500 mb-4">
            <a href="{{ route('home') }}" class="hover:text-primary transition-colors">Home</a>
            <span class="text-slate-300">/</span>
            <a href="{{ route('help') }}" class="hover:text-primary transition-colors">Help Center</a>
            <span class="text-slate-300">/</span>
            <span class="text-primary font-semibold">{{ $article['title'] ?? 'Guide' }}</span>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
            <div class="lg:col-span-7">
                <div class="inline-flex items-center gap-2 px-3 py-1 bg-primary/10 text-primary rounded-full text-[11px] font-bold uppercase tracking-wider mb-3">
                    <span class="material-symbols-outlined text-sm" aria-hidden="true">menu_book</span>
                    Help guide
                </div>
                <h1 class="font-display text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight mb-3 leading-tight">
                    {{ $article['title'] ?? 'Help guide' }}
                </h1>
                @if(! empty($article['intro']))
                    <p class="text-base sm:text-lg text-slate-600 leading-relaxed max-w-2xl">{{ $article['intro'] }}</p>
                @endif
                <div class="flex flex-wrap items-center gap-3 mt-5 text-sm text-slate-500">
                    <span>{{ $article['reading_minutes'] ?? 1 }} min read</span>
                    <span class="text-slate-300" aria-hidden="true">·</span>
                    <span>Updated {{ $article['updated_at_display'] ?? ($article['updated_at'] ?? '') }}</span>
                    @if(! empty($article['printable']))
                        <button type="button" onclick="window.print()" class="ml-auto sm:ml-2 inline-flex items-center gap-1.5 text-primary font-semibold hover:underline">
                            <span class="material-symbols-outlined text-base" aria-hidden="true">print</span>
                            Print
                        </button>
                    @endif
                </div>
            </div>
            <div class="lg:col-span-5">
                <div class="relative rounded-2xl overflow-hidden shadow-sm border border-slate-100 aspect-[16/10] bg-slate-100">
                    <img src="{{ $heroUrl }}" alt="" class="w-full h-full object-cover" loading="eager">
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950/35 to-transparent" aria-hidden="true"></div>
                </div>
            </div>
        </div>
    </div>
</section>

<div
    x-data="helpArticleProgress({{ $sectionCount }})"
    x-init="init()"
    class="relative bg-white"
>
    <div class="sticky top-16 z-30 h-1 bg-slate-100" aria-hidden="true">
        <div class="h-full bg-primary transition-all duration-150" :style="'width:' + percent + '%'"></div>
    </div>

    <section class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14 pb-16 sm:pb-20">
        <div class="flex flex-wrap items-center gap-2 text-xs sm:text-sm text-slate-500 mb-8">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-50 border border-slate-100 font-medium text-slate-700">
                <span class="material-symbols-outlined text-sm text-primary" aria-hidden="true">route</span>
                <span x-text="'Step ' + currentStep + ' of ' + totalSteps"></span>
            </span>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-50 border border-slate-100 font-medium text-slate-700">
                <span x-text="percent + '% read'"></span>
            </span>
            <span class="hidden" data-help-pdf-slot aria-hidden="true"></span>
        </div>

        <style>
            .help-toc { display: none; }
            @media (min-width: 1024px) {
                .help-toc {
                    display: block;
                    position: sticky;
                    top: 7.5rem;
                    align-self: flex-start;
                    width: 16rem;
                    flex-shrink: 0;
                    max-height: calc(100vh - 9rem);
                }
                .help-toc-panel {
                    display: flex;
                    flex-direction: column;
                    min-height: calc(100vh - 9rem);
                    max-height: calc(100vh - 9rem);
                }
                .help-toc-nav {
                    flex: 1 1 auto;
                    overflow-y: auto;
                    min-height: 0;
                }
                .help-accordion-summary { display: none !important; }
                .help-section-panel {
                    display: block !important;
                    border-top: 0 !important;
                    padding-top: 0 !important;
                }
                .help-section-card {
                    background: transparent;
                    border: 0;
                    border-radius: 0;
                    overflow: visible;
                    box-shadow: none;
                }
                .help-section-card + .help-section-rule { display: block; }
            }
            @media (max-width: 1023px) {
                .help-section-rule { display: none; }
                .help-desktop-heading { display: none !important; }
            }
            @media print {
                .help-toc, .sticky, [onclick="window.print()"] { display: none !important; }
                .help-accordion-summary { display: none !important; }
                .help-section-panel { display: block !important; }
            }
        </style>

        @if(empty($sections))
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-8 text-center">
                <span class="material-symbols-outlined text-3xl text-slate-400 mb-2" aria-hidden="true">info</span>
                <p class="font-display font-semibold text-slate-900">Empty guide</p>
                <p class="text-sm text-slate-600 mt-1">This article has no sections yet.</p>
            </div>
        @else
            <div class="flex gap-8 items-start">
                <aside class="help-toc">
                    <div class="help-toc-panel bg-white rounded-2xl p-4 border border-slate-100 shadow-sm">
                        <h3 class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2 px-2 shrink-0">
                            In this guide
                        </h3>
                        <p class="text-xs text-slate-500 px-2 mb-3 shrink-0" x-text="'Step ' + currentStep + ' of ' + totalSteps + ' · ' + percent + '%'"></p>
                        <nav class="help-toc-nav flex flex-col gap-1" aria-label="Guide sections">
                            @foreach($sections as $i => $section)
                                <a
                                    href="#{{ $section['id'] }}"
                                    class="px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
                                    :class="currentStep === {{ $i + 1 }} ? 'bg-primary/10 text-primary' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                                >
                                    {{ $section['nav'] ?? $section['title'] }}
                                </a>
                            @endforeach
                        </nav>
                    </div>
                </aside>

                <div class="min-w-0 flex-1 max-w-[800px] space-y-3 lg:space-y-8 pb-8">
                    <div class="rounded-2xl p-5 lg:p-6 bg-slate-50 border border-slate-100 border-l-4 border-l-primary">
                        <h2 class="help-desktop-heading font-display text-lg font-bold text-slate-900 mb-2">Overview</h2>
                        <p class="text-sm text-slate-600 leading-relaxed">{{ $article['summary'] ?? '' }}</p>
                    </div>

                    @foreach($sections as $section)
                        <details
                            id="{{ $section['id'] }}"
                            data-help-section
                            class="help-section-card group bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden lg:overflow-visible scroll-mt-28 [&_summary::-webkit-details-marker]:hidden"
                            @if($loop->first) open @endif
                        >
                            <summary class="help-accordion-summary flex justify-between items-center gap-3 p-4 sm:p-5 cursor-pointer hover:bg-slate-50 transition-colors">
                                <span class="font-display font-semibold text-sm sm:text-base text-slate-900 text-left">{{ $section['title'] }}</span>
                                <span class="material-symbols-outlined text-slate-400 transition-transform group-open:rotate-180 shrink-0" aria-hidden="true">expand_more</span>
                            </summary>
                            <div class="help-section-panel px-4 sm:px-5 pb-5 lg:px-0 lg:pb-0 border-t border-slate-100 pt-4 lg:border-0">
                                <h2 class="help-desktop-heading font-display text-xl sm:text-2xl font-bold text-slate-900 mb-4">
                                    {{ $section['title'] }}
                                </h2>
                                @include('partials.help.section-body', ['section' => $section])
                            </div>
                        </details>
                        @if(! $loop->last)
                            <hr class="help-section-rule border-slate-100 hidden lg:block">
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        @if(count($actions))
            <div class="mt-12 flex flex-wrap gap-3">
                @foreach($actions as $action)
                    @php
                        $routeName = $action['route'] ?? null;
                        $needsAuth = ! empty($action['auth']);
                        $href = ($needsAuth && ! auth()->check())
                            ? route('login')
                            : (\Illuminate\Support\Facades\Route::has($routeName) ? route($routeName) : route('help'));
                    @endphp
                    <a
                        href="{{ $href }}"
                        @class([
                            'inline-flex items-center justify-center px-5 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm',
                            'bg-primary hover:bg-primary-hover text-white' => $loop->first,
                            'bg-white hover:bg-slate-50 text-slate-900 border border-slate-200' => ! $loop->first,
                        ])
                    >
                        {{ $action['label'] ?? 'Open' }}
                    </a>
                @endforeach
            </div>
        @endif

        @if(count($related))
            <div class="mt-10 pt-8 border-t border-slate-100">
                <h3 class="font-display text-lg font-bold text-slate-900 mb-4">Related guides</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($related as $relatedSlug)
                        @php $relatedArticle = \App\Support\HelpContent::find($relatedSlug); @endphp
                        @if($relatedArticle)
                            <a
                                href="{{ route('help.article', $relatedSlug) }}"
                                class="px-4 py-2 rounded-xl text-sm font-medium bg-white border border-slate-200 text-slate-700 hover:text-primary hover:border-primary/40 transition-colors"
                            >
                                {{ $relatedArticle['title'] }}
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        <div class="mt-12 rounded-2xl bg-slate-50 border border-slate-100 p-6 sm:p-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <p class="font-display font-bold text-slate-900">Still need help?</p>
                <p class="text-sm text-slate-600 mt-1">Browse more guides or contact support for wallet, proof, and campaign questions.</p>
            </div>
            <div class="flex flex-wrap gap-2 shrink-0">
                <a href="{{ route('help') }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg bg-white border border-slate-200 text-sm font-semibold text-slate-900 hover:bg-slate-50 transition-colors">
                    Help Center
                </a>
                <a href="{{ route('contact') }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg bg-primary hover:bg-primary-hover text-white text-sm font-semibold transition-colors">
                    Contact Support
                </a>
            </div>
        </div>
    </section>
</div>

<script>
function helpArticleProgress(total) {
    return {
        totalSteps: Math.max(1, total || 1),
        currentStep: 1,
        percent: 0,
        init() {
            const sections = Array.from(document.querySelectorAll('[data-help-section]'));
            const desktopMq = window.matchMedia('(min-width: 1024px)');

            const syncDesktopOpen = () => {
                if (!desktopMq.matches) return;
                sections.forEach((el) => {
                    if (el.tagName === 'DETAILS') el.open = true;
                });
            };
            syncDesktopOpen();
            desktopMq.addEventListener('change', syncDesktopOpen);
            sections.forEach((el) => {
                if (el.tagName !== 'DETAILS') return;
                el.addEventListener('toggle', () => {
                    if (desktopMq.matches && !el.open) el.open = true;
                });
            });

            const updateScroll = () => {
                const doc = document.documentElement;
                const scrollable = doc.scrollHeight - window.innerHeight;
                this.percent = scrollable > 0
                    ? Math.min(100, Math.round((window.scrollY / scrollable) * 100))
                    : 0;
            };
            window.addEventListener('scroll', updateScroll, { passive: true });
            updateScroll();

            if (sections.length && 'IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (!entry.isIntersecting) return;
                        const idx = sections.indexOf(entry.target);
                        if (idx >= 0) this.currentStep = idx + 1;
                    });
                }, { rootMargin: '-40% 0px -45% 0px', threshold: 0 });
                sections.forEach((el) => observer.observe(el));
            }

            const openHash = () => {
                const id = decodeURIComponent((window.location.hash || '').replace(/^#/, ''));
                if (!id) return;
                const el = document.getElementById(id);
                if (!el) return;
                if (el.tagName === 'DETAILS') {
                    el.open = true;
                    if (!desktopMq.matches) {
                        sections.forEach((s) => {
                            if (s !== el && s.tagName === 'DETAILS') s.open = false;
                        });
                    }
                }
                const idx = sections.indexOf(el);
                if (idx >= 0) this.currentStep = idx + 1;
                requestAnimationFrame(() => el.scrollIntoView({ behavior: 'smooth', block: 'start' }));
            };
            openHash();
            window.addEventListener('hashchange', openHash);
        },
    };
}
</script>
@endsection
