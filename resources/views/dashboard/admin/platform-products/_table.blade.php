@php
    $status = $status ?? 'active';
@endphp

<x-dashboard.table
    :empty="$products->isEmpty()"
    :empty-title="$status === 'deactivated' ? 'No deactivated products' : 'No active products'"
    empty-description="Products are seeded with the platform catalog."
    empty-icon="storefront"
    striped
>
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
        @php
            $thumb = $product->listThumbnailUrl();
            $isActive = $product->status === \App\Enums\PlatformProductStatus::Published;
            $isArchived = $product->status === \App\Enums\PlatformProductStatus::Archived;
        @endphp
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
                        @php($taskIssues = $product->taskSetupIssues())
                        @if ($product->taskProfile()->isMultiTask())
                            <x-dashboard.badge status="info">{{ count($product->taskProfile()->tasks()) }} tasks</x-dashboard.badge>
                        @endif
                        @foreach ($taskIssues as $issue)
                            <x-dashboard.badge status="danger">{{ $issue }}</x-dashboard.badge>
                        @endforeach
                        @if ($taskIssues === [] && ! $product->isPurchasable())
                            <x-dashboard.badge status="danger">Needs reward %</x-dashboard.badge>
                        @endif
                    </div>
                    <p class="mt-0.5 font-mono text-[10px] leading-tight text-text-muted" title="Catalog slug, fixed in code and not changed when you edit the title">
                        {{ $product->slug }}
                    </p>
                </div>
            </x-dashboard.td>
            <x-dashboard.td>{{ $product->serviceCategory?->name ?? ($product->productType?->serviceCategory?->name ?? '-') }}</x-dashboard.td>
            <x-dashboard.td>{{ $product->sort_order }}</x-dashboard.td>
            <x-dashboard.td>
                <x-dashboard.badge :status="$isActive ? 'success' : 'neutral'">
                    {{ $isActive ? 'Active' : ($isArchived ? 'Archived' : 'Deactivated') }}
                </x-dashboard.badge>
            </x-dashboard.td>
            <x-dashboard.td>{{ money($product->displayPrice(), 2) }}</x-dashboard.td>
            <x-dashboard.td>
                <x-dashboard.row-actions>
                    <x-dashboard.menu-item :href="route('admin.platform-products.edit', $product)">Edit</x-dashboard.menu-item>
                    <form method="POST" action="{{ route('admin.platform-products.toggle', $product) }}">
                        @csrf
                        <x-dashboard.menu-item type="submit" :variant="$isActive ? 'danger' : 'success'">
                            {{ $isActive ? 'Deactivate' : 'Activate' }}
                        </x-dashboard.menu-item>
                    </form>
                </x-dashboard.row-actions>
            </x-dashboard.td>
        </tr>
    @endforeach
</x-dashboard.table>

<div class="mt-4">
    <x-dashboard.pagination :paginator="$products" />
</div>
