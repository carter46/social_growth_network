@extends('layouts.dashboard-admin')

@section('title', 'Platform Products')

@section('content')
@php
    $status = $status ?? 'active';
    $tabQuery = array_filter([
        'q' => $filters['q'] ?? null,
        'category' => $filters['category'] ?? null,
        'service' => $filters['service'] ?? null,
        'featured' => $filters['featured'] ?? null,
    ], fn ($value) => $value !== null && $value !== '');
@endphp
<x-layout.page
    title="Products"
    subtitle="Fixed platform products. Edit title, description, price, image, sort, and status. You cannot add or delete."
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Products', null],
    ]"
>
    @if (session('status'))
        <x-dashboard.alert type="success" class="mb-4">{{ session('status') }}</x-dashboard.alert>
    @endif
    @if (session('error'))
        <x-dashboard.alert type="danger" class="mb-4">{{ session('error') }}</x-dashboard.alert>
    @endif
    @error('status')
        <x-dashboard.alert type="danger" class="mb-4">{{ $message }}</x-dashboard.alert>
    @enderror

    <x-dashboard.card class="mb-4">
        <form
            method="GET"
            action="{{ route('admin.platform-products') }}"
            class="flex flex-wrap gap-3 items-end"
            x-data="{ status: @js($status) }"
            @dashboard-tab-navigated.window="status = $event.detail?.id || status"
        >
            <input type="hidden" name="status" :value="status" value="{{ $status }}">
            <div class="min-w-[16rem] flex-1">
                <x-dashboard.input name="q" label="Search" :value="$filters['q'] ?? ''" placeholder="Title, slug, description..." />
            </div>
            <div class="min-w-[10rem]">
                <x-dashboard.select name="category" label="Category">
                    <option value="">All categories</option>
                    @foreach($serviceCategories as $category)
                        <option value="{{ $category->id }}" @selected((string) ($filters['category'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </x-dashboard.select>
            </div>
            <div class="min-w-[10rem]">
                <x-dashboard.select name="service" label="Service">
                    <option value="">All services</option>
                    @foreach($services as $service)
                        <option value="{{ $service->id }}" @selected((string) ($filters['service'] ?? '') === (string) $service->id)>{{ $service->name }}</option>
                    @endforeach
                </x-dashboard.select>
            </div>
            <div class="min-w-[8rem]">
                <x-dashboard.select name="featured" label="Featured">
                    <option value="">Any</option>
                    <option value="1" @selected(($filters['featured'] ?? '') === '1')>Featured</option>
                    <option value="0" @selected(($filters['featured'] ?? '') === '0')>Not featured</option>
                </x-dashboard.select>
            </div>
            <x-dashboard.button type="submit" variant="secondary">Search</x-dashboard.button>
        </form>
    </x-dashboard.card>

    <x-dashboard.ajax-tabs
        :active="$status"
        :tabs="[
            ['id' => 'active', 'label' => 'Active', 'href' => route('admin.platform-products', ['status' => 'active'] + $tabQuery), 'count' => $activeCount ?? null],
            ['id' => 'deactivated', 'label' => 'Deactivated', 'href' => route('admin.platform-products', ['status' => 'deactivated'] + $tabQuery), 'count' => $deactivatedCount ?? null],
        ]"
        class="mb-4"
    />

    <div id="dashboard-tab-panel">
        @include('dashboard.admin.platform-products._table')
    </div>
</x-layout.page>
@endsection
