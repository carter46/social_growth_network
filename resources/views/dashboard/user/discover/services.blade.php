@extends('layouts.dashboard-user')

@section('title', 'Services')

@section('content')
<x-layout.page
    title="Services"
    width="full"
    :breadcrumb="[
        ['Dashboard', route('dashboard')],
        ['Services', null],
    ]"
>
    <div class="space-y-8">
        @if($searchResults)
            <section>
                <h2 class="text-sm font-semibold text-text-secondary uppercase tracking-wide mb-3">Search results</h2>
                <x-dashboard.card-grid :count="$searchResults->count()">
                    @forelse($searchResults as $product)
                        @include('dashboard.user.partials.service-product-card', ['product' => $product])
                    @empty
                        <p class="col-span-full text-text-muted">No services matched.</p>
                    @endforelse
                </x-dashboard.card-grid>
                <div class="mt-4">
                    <x-dashboard.pagination :paginator="$searchResults" />
                </div>
            </section>
        @else
            <section>
                <h2 class="text-sm font-semibold text-text-secondary uppercase tracking-wide mb-3">Categories</h2>
                <x-dashboard.card-grid :count="count($groups)">
                    @foreach($groups as $group)
                        @php
                            $image = $group['card_image'] ?? null;
                            $imageSrc = null;
                            if (is_string($image) && $image !== '') {
                                $trimmed = trim($image);
                                if (preg_match('#^(https?:)?//#i', $trimmed) || str_starts_with($trimmed, '/')) {
                                    $imageSrc = $trimmed;
                                } else {
                                    $imageSrc = asset(ltrim(str_replace('\\', '/', $trimmed), '/'));
                                }
                            }
                            $label = $group['label'] ?? $group['name'] ?? 'Category';
                        @endphp
                        @include('dashboard.user.partials.catalog-grid-card', [
                            'href' => $group['href'] ?? '#',
                            'label' => $label,
                            'description' => $group['short_description'] ?? null,
                            'imageSrc' => $imageSrc,
                            'ctaLabel' => $group['cta'] ?? 'Browse',
                        ])
                    @endforeach
                </x-dashboard.card-grid>
            </section>
        @endif
    </div>
</x-layout.page>
@endsection
