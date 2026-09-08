@extends('layouts.dashboard-admin')

@section('title', 'Platform Products')

@section('content')
<x-layout.page
    title="Products"
    subtitle="Fixed platform products. Edit title, description, price, image, sort, and status — you cannot add or delete."
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Products', null],
    ]"
>
    <x-dashboard.table
        :empty="$products->isEmpty()"
        empty-title="No platform products"
        empty-description="Products are seeded with the platform catalog."
        empty-icon="storefront"
        striped
    >
        <x-slot:filters>
            <x-dashboard.filter-bar>
                <form method="GET" class="contents">
                    <div class="min-w-[10rem] flex-1">
                        <x-dashboard.input name="q" type="text" :value="$filters['q'] ?? ''" placeholder="Search products..." />
                    </div>
                    <div class="min-w-[8rem]">
                        <x-dashboard.select name="status">
                            <option value="">All statuses</option>
                            @foreach(['draft','published','archived'] as $status)
                                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </x-dashboard.select>
                    </div>
                    <div class="min-w-[10rem]">
                        <x-dashboard.select name="category">
                            <option value="">All categories</option>
                            @foreach($serviceCategories as $category)
                                <option value="{{ $category->id }}" @selected((string) ($filters['category'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </x-dashboard.select>
                    </div>
                    <div class="min-w-[10rem]">
                        <x-dashboard.select name="service">
                            <option value="">All services</option>
                            @foreach($services as $service)
                                <option value="{{ $service->id }}" @selected((string) ($filters['service'] ?? '') === (string) $service->id)>{{ $service->name }}</option>
                            @endforeach
                        </x-dashboard.select>
                    </div>
                    <div class="min-w-[8rem]">
                        <x-dashboard.select name="featured">
                            <option value="">Featured: any</option>
                            <option value="1" @selected(($filters['featured'] ?? '') === '1')>Featured</option>
                            <option value="0" @selected(($filters['featured'] ?? '') === '0')>Not featured</option>
                        </x-dashboard.select>
                    </div>
                    <x-dashboard.button type="submit" variant="secondary" size="md">Filter</x-dashboard.button>
                </form>
            </x-dashboard.filter-bar>
        </x-slot:filters>

        <x-slot:head>
            <x-dashboard.th class="w-24"> </x-dashboard.th>
            <x-dashboard.th>Title</x-dashboard.th>
            <x-dashboard.th>Category</x-dashboard.th>
            <x-dashboard.th>Sort</x-dashboard.th>
            <x-dashboard.th>Status</x-dashboard.th>
            <x-dashboard.th>Price</x-dashboard.th>
            <x-dashboard.th>Actions</x-dashboard.th>
        </x-slot:head>
        @foreach ($products as $product)
            @php $thumb = $product->listThumbnailUrl(); @endphp
            <tr>
                <x-dashboard.td>
                    @if ($thumb)
                        <img
                            src="{{ $thumb }}"
                            alt=""
                            class="h-12 w-20 rounded-lg object-cover bg-muted"
                        >
                    @else
                        <span class="inline-flex h-12 w-20 items-center justify-center rounded-lg bg-muted text-text-muted" aria-hidden="true">
                            <x-dashboard.icon name="storefront" class="h-4 w-4" />
                        </span>
                    @endif
                </x-dashboard.td>
                <x-dashboard.td>
                    <div class="min-w-0">
                        <div class="font-medium text-text-primary">
                            {{ $product->title }}
                            @if ($product->is_featured)
                                <x-dashboard.badge status="warning">Featured</x-dashboard.badge>
                            @endif
                        </div>
                        <p class="mt-0.5 font-mono text-[10px] leading-tight text-text-muted" title="Catalog slug — fixed in code, not changed when you edit the title">
                            {{ $product->slug }}
                        </p>
                    </div>
                </x-dashboard.td>
                <x-dashboard.td>{{ $product->serviceCategory?->name ?? ($product->productType?->serviceCategory?->name ?? '—') }}</x-dashboard.td>
                <x-dashboard.td>{{ $product->sort_order }}</x-dashboard.td>
                <x-dashboard.td>
                    <x-dashboard.badge :status="$product->status->value === 'published' ? 'success' : 'neutral'">
                        {{ $product->status->value }}
                    </x-dashboard.badge>
                </x-dashboard.td>
                <x-dashboard.td>₦{{ number_format($product->displayPrice(), 2) }}</x-dashboard.td>
                <x-dashboard.td>
                    <x-dashboard.row-actions>
                        <x-dashboard.menu-item :href="route('admin.platform-products.edit', $product)">Edit</x-dashboard.menu-item>
                        <form method="POST" action="{{ route('admin.platform-products.toggle', $product) }}">
                            @csrf
                            <x-dashboard.menu-item type="submit" :variant="$product->status->value === 'published' ? 'danger' : 'success'">
                                {{ $product->status->value === 'published' ? 'Deactivate' : 'Activate' }}
                            </x-dashboard.menu-item>
                        </form>
                    </x-dashboard.row-actions>
                </x-dashboard.td>
            </tr>
        @endforeach
    </x-dashboard.table>

    <x-slot:pagination>
        <x-dashboard.pagination :paginator="$products" />
    </x-slot:pagination>
</x-layout.page>
@endsection
