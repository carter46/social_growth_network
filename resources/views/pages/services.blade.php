@extends('layouts.marketing')

@section('title', 'Services')

@section('content')
@php
    $heroImage = asset('assets/images/services_1.jpg');
    $groupCount = $groups->count();
@endphp

<section class="relative min-h-[28rem] sm:min-h-[32rem] flex items-center justify-center overflow-hidden py-16 sm:py-20 bg-navy-dark">
    <div class="absolute inset-0 z-0" aria-hidden="true">
        <div class="w-full h-full bg-cover bg-center opacity-50" style="background-image: url('{{ $heroImage }}')"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-slate-950/80 via-slate-950/60 to-slate-950/90"></div>
    </div>

    <div class="relative z-10 max-w-site mx-auto px-4 sm:px-6 lg:px-8 text-center w-full">
        <h1 class="font-display text-2xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-white mb-4 leading-tight">
            Campaign services
        </h1>
        <p class="max-w-2xl mx-auto text-base sm:text-lg text-slate-200 mb-8 leading-relaxed">
            Browse categories and packages with upfront pricing and secure checkout.
        </p>

        <form method="GET" action="{{ route('services') }}" class="max-w-xl mx-auto">
            <div class="flex items-center gap-2 bg-white rounded-xl p-2 border border-white/40 shadow-xl">
                <span class="pl-2 text-slate-400 shrink-0" aria-hidden="true">
                    <x-ui.icon name="search" class="w-5 h-5" />
                </span>
                <label for="services-q" class="sr-only">Search services</label>
                <input
                    id="services-q"
                    type="search"
                    name="q"
                    value="{{ $q }}"
                    placeholder="Search services..."
                    class="w-full min-w-0 bg-transparent border-0 focus:ring-0 text-slate-900 placeholder:text-slate-400 px-2 py-2 text-sm sm:text-base"
                />
                <x-ui.button type="submit" variant="primary" size="md" class="shrink-0">
                    Search
                </x-ui.button>
            </div>
        </form>
    </div>
</section>

@if($searchResults !== null)
    <section class="py-12 sm:py-16 bg-white border-t border-slate-100">
        <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-8 border-b border-slate-100 pb-4">
                <h2 class="font-display text-xl sm:text-3xl font-semibold text-slate-900 tracking-tight">Search results</h2>
            </div>
            @if($searchResults->isEmpty())
                <x-ui.empty
                    icon="search"
                    title="No matching services"
                    description="No services match “{{ $q }}”. Try another term or browse a category below."
                />
            @else
                <x-ui.card-grid :count="$searchResults->count()">
                    @foreach($searchResults as $product)
                        @include('partials.catalog.product-card', ['product' => $product])
                    @endforeach
                </x-ui.card-grid>
                <div class="mt-8">{{ $searchResults->links() }}</div>
            @endif
        </div>
    </section>
@endif

<section class="py-16 sm:py-20 bg-surface-muted">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-8 border-b border-slate-200 pb-4">
            <h2 class="font-display text-xl sm:text-3xl font-semibold text-slate-900 tracking-tight">Browse Categories</h2>
            <span class="text-xs font-medium text-slate-600 bg-white px-3 py-1 rounded-full border border-slate-200">
                Showing {{ $groupCount }} {{ \Illuminate\Support\Str::plural('Category', $groupCount) }}
            </span>
        </div>

        <x-ui.card-grid :count="$groupCount">
            @foreach($groups as $card)
                @include('partials.catalog.explore-card', ['card' => $card])
            @endforeach
        </x-ui.card-grid>
    </div>
</section>
@endsection
