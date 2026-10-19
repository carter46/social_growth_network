@extends('layouts.dashboard-user')

@section('title', 'Campaign packages')

@section('content')
<x-layout.page
    title="Campaign packages"
    subtitle="Pick a package and launch a campaign to promote your videos."
    width="full"
    :breadcrumb="[
        ['Dashboard', route('dashboard')],
        ['Campaign packages', null],
    ]"
>
    <div class="space-y-8">
        <section>
            @if(($products ?? null) && $products->isNotEmpty())
                <x-dashboard.card-grid :count="$products->count()">
                    @foreach($products as $product)
                        @include('dashboard.user.partials.service-product-card', ['product' => $product])
                    @endforeach
                </x-dashboard.card-grid>
                <div class="mt-4">
                    <x-dashboard.pagination :paginator="$products" />
                </div>
            @else
                <x-dashboard.empty
                    icon="listings"
                    title="{{ $q !== '' ? 'No packages matched' : 'No packages available' }}"
                    description="{{ $q !== '' ? 'Try a different search.' : 'Check back soon for new campaign packages.' }}"
                />
            @endif
        </section>
    </div>
</x-layout.page>
@endsection
