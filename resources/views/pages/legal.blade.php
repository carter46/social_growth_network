@extends('layouts.marketing')

@section('title', ($document['label'] ?? 'Legal'))

@section('content')
@php
    $documents = config('legal.documents', []);
    $updatedAt = config('legal.updated_at');
    $contact = app(\App\Services\Communications\Contact\PlatformContactRepository::class)->all();
    $legalEmail = config('legal.contact.email')
        ?: ($contact['email_support'] ?? null)
        ?: ($contact['email_info'] ?? null);
    $brandName = $siteName ?? config('app.name', 'Social Growth Network');
    $activeKey = $activeDoc ?? 'terms';
    if (! isset($documents[$activeKey])) {
        $activeKey = array_key_first($documents) ?: 'terms';
    }

    // Prefer the route-prepared document (placeholders already replaced). Fallback: replace here.
    if (! isset($document) || ! is_array($document) || $document === []) {
        $document = $documents[$activeKey] ?? [];
    }
    array_walk_recursive($document, function (&$value) use ($brandName): void {
        if (is_string($value)) {
            $value = str_replace([':site_name', ': site_name'], $brandName, $value);
        }
    });

    $sections = $document['sections'] ?? [];
    $ticketHref = auth()->check()
        ? route('dashboard.support.create')
        : route('login');
@endphp

<section class="relative w-full bg-slate-50 border-b border-slate-100 overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-b from-slate-100/80 via-transparent to-white pointer-events-none" aria-hidden="true"></div>
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-12 relative z-10">
        <div class="flex flex-wrap items-center gap-1.5 text-sm text-slate-500 mb-4">
            <a href="{{ route('home') }}" class="hover:text-primary transition-colors">Home</a>
            <span class="text-slate-300">/</span>
            <span class="text-primary font-semibold">{{ $document['label'] ?? 'Legal' }}</span>
        </div>
        <div class="inline-flex items-center gap-2 px-3 py-1 bg-primary/10 text-primary rounded-full text-[11px] font-bold uppercase tracking-wider mb-3">
            <span class="material-symbols-outlined text-sm" aria-hidden="true">gavel</span>
            {{ $document['eyebrow'] ?? 'Legal' }}
        </div>
        <h1 class="font-display text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight mb-3 leading-tight max-w-3xl">
            {{ $document['label'] ?? 'Legal' }}
        </h1>
        <p class="text-base sm:text-lg text-slate-600 leading-relaxed max-w-2xl">
            {{ $document['intro'] ?? ('Terms of Service and Privacy Policy for '.$brandName.'.') }}
        </p>
        @if($updatedAt)
            <p class="text-sm text-slate-500 mt-4">Last updated {{ \Illuminate\Support\Carbon::parse($updatedAt)->format('F j, Y') }}</p>
        @endif
    </div>
</section>

<section class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14 pb-16 sm:pb-20 bg-white">
    <div class="flex flex-wrap gap-2 mb-8 sm:mb-10" role="tablist" aria-label="Legal documents">
        @foreach($documents as $key => $doc)
            <a
                href="{{ route('legal', ['doc' => $key]) }}"
                role="tab"
                aria-selected="{{ $activeKey === $key ? 'true' : 'false' }}"
                @class([
                    'px-4 sm:px-5 py-2.5 rounded-xl text-sm font-semibold border transition-colors shadow-sm',
                    'bg-primary border-primary text-white' => $activeKey === $key,
                    'bg-white border-slate-200 text-slate-600 hover:text-slate-900 hover:border-slate-300' => $activeKey !== $key,
                ])
            >
                {{ $doc['label'] }}
            </a>
        @endforeach
    </div>

    <style>
        .legal-mobile { display: block; }
        .legal-desktop { display: none; }
        @media (min-width: 1024px) {
            .legal-mobile { display: none; }
            .legal-desktop { display: block; }
            .legal-toc {
                position: sticky;
                top: 7rem;
                align-self: flex-start;
                width: 16rem;
                flex-shrink: 0;
                max-height: calc(100vh - 8.5rem);
            }
            .legal-toc-panel {
                display: flex;
                flex-direction: column;
                min-height: calc(100vh - 8.5rem);
                max-height: calc(100vh - 8.5rem);
            }
            .legal-toc-nav {
                flex: 1 1 auto;
                overflow-y: auto;
                min-height: 0;
                padding-bottom: 0.25rem;
            }
        }
    </style>

    @if(empty($sections))
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-8 text-center">
            <span class="material-symbols-outlined text-3xl text-slate-400 mb-2" aria-hidden="true">info</span>
            <p class="font-display font-semibold text-slate-900">No legal content</p>
            <p class="text-sm text-slate-600 mt-1">Legal documents have not been configured yet.</p>
        </div>
    @else
        <div class="legal-mobile space-y-3 mb-8">
            <div class="rounded-2xl p-5 bg-slate-50 border border-slate-100 border-l-4 border-l-primary mb-4">
                <h2 class="font-display text-base font-bold text-slate-900 mb-1">{{ $document['label'] }} summary</h2>
                <p class="text-sm text-slate-600 leading-relaxed">{{ $document['summary'] ?? '' }}</p>
            </div>
            @foreach($sections as $section)
                <details class="group bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden [&_summary::-webkit-details-marker]:hidden" @if($loop->first) open @endif>
                    <summary class="flex justify-between items-center gap-3 p-4 cursor-pointer hover:bg-slate-50 transition-colors">
                        <span class="font-semibold text-sm text-slate-900 text-left flex items-center gap-2">
                            @if(! empty($section['number']))
                                <span class="text-[10px] font-bold bg-primary/10 text-primary px-1.5 py-0.5 rounded">{{ $section['number'] }}</span>
                            @endif
                            {{ $section['title'] }}
                        </span>
                        <span class="material-symbols-outlined text-slate-400 transition-transform group-open:rotate-180 shrink-0" aria-hidden="true">expand_more</span>
                    </summary>
                    <div class="px-4 pb-4 border-t border-slate-100 pt-3">
                        @include('partials.legal.section-body', ['section' => $section, 'legalEmail' => $legalEmail, 'ticketHref' => $ticketHref])
                    </div>
                </details>
            @endforeach
        </div>

        <div class="legal-desktop">
            <div class="flex gap-8 items-start">
                <aside class="legal-toc">
                    <div class="legal-toc-panel bg-white rounded-2xl p-4 border border-slate-100 shadow-sm">
                        <h3 class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-3 px-2 shrink-0">
                            Document sections
                        </h3>
                        <nav class="legal-toc-nav flex flex-col gap-1" aria-label="Document sections">
                            @foreach($sections as $section)
                                <a
                                    href="#{{ $section['id'] }}"
                                    class="px-3 py-2.5 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-primary transition-colors"
                                >
                                    {{ $section['nav'] }}
                                </a>
                            @endforeach
                        </nav>
                    </div>
                </aside>

                <div class="min-w-0 flex-1 max-w-[800px] space-y-8 pb-8">
                    <div class="rounded-2xl p-6 bg-slate-50 border border-slate-100 border-l-4 border-l-primary">
                        <div class="flex items-start gap-4">
                            <span class="material-symbols-outlined text-primary text-2xl shrink-0 mt-0.5" aria-hidden="true">info</span>
                            <div>
                                <h2 class="font-display text-lg font-bold text-slate-900 mb-2">Legal summary</h2>
                                <p class="text-sm text-slate-600 leading-relaxed">{{ $document['summary'] ?? '' }}</p>
                                @if($updatedAt)
                                    <p class="text-xs text-slate-500 mt-3">Last updated {{ \Illuminate\Support\Carbon::parse($updatedAt)->format('F Y') }}</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    @foreach($sections as $section)
                        <section id="{{ $section['id'] }}" @class(['scroll-mt-28', 'pb-6' => $loop->last])>
                            <h2 class="font-display text-xl sm:text-2xl font-bold text-slate-900 mb-4 flex items-center gap-3 flex-wrap">
                                @if(! empty($section['number']))
                                    <span class="text-xs font-bold bg-primary/10 text-primary px-2 py-1 rounded">{{ $section['number'] }}</span>
                                @endif
                                {{ $section['title'] }}
                            </h2>
                            @include('partials.legal.section-body', ['section' => $section, 'legalEmail' => $legalEmail, 'ticketHref' => $ticketHref])
                        </section>

                        @if(! $loop->last)
                            <hr class="border-slate-100">
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</section>
@endsection
