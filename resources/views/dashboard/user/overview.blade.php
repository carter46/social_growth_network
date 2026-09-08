@extends('layouts.dashboard-user')

@section('title', 'Dashboard')

@section('content')
<x-layout.page
    title="Welcome back, {{ auth()->user()->name ?? 'Creator' }}"
    width="full"
    :breadcrumb="[
        ['Dashboard', route('dashboard')],
        ['Overview', null],
    ]"
>
    <div class="space-y-4">
        <x-dashboard.stats-card
            label="Total Balance"
            :value="'₦' . number_format($balanceNgn ?? 0, 2)"
            :hint="'Locked: ₦' . number_format($lockedNgn ?? 0, 2)"
            icon="wallet"
            :href="route('dashboard.wallet')"
        />

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-2">
            <x-dashboard.stats-card
                label="My Campaigns"
                :value="(string) ($activeCampaignsCount ?? 0)"
                hint="Active & pending"
                icon="listings"
                :href="route('dashboard.campaigns')"
            />
            <x-dashboard.stats-card
                label="Active Orders"
                :value="(string) ($activeOrdersCount ?? 0)"
                :hint="$ordersAwaitingLabel ?? 'All caught up'"
                icon="shopping-bag"
                :href="route('dashboard.service-orders')"
            />
        </div>
    </div>

    <section class="mt-8 space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-text-primary">Campaign packages</h2>
                <p class="mt-1 text-sm text-text-secondary">Buy packages from the catalog and launch campaigns for agents.</p>
            </div>
            <x-dashboard.button :href="route('dashboard.services')" variant="secondary" size="sm">Browse packages</x-dashboard.button>
        </div>

        @if(($featuredServices ?? collect())->isEmpty())
            <x-dashboard.empty
                icon="listings"
                title="No packages available"
                description="Check back soon for new campaign packages."
                :action="['href' => route('dashboard.services'), 'label' => 'Browse packages']"
            />
        @else
            <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
                @foreach($featuredServices as $product)
                    @include('dashboard.user.partials.service-product-card', ['product' => $product])
                @endforeach
            </div>
        @endif
    </section>
</x-layout.page>
@endsection
