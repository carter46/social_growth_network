@extends('layouts.dashboard-user')

@section('title', 'Fund wallet')

@section('content')
<x-layout.page
    title="Fund wallet"
    width="full"
    :breadcrumb="[
        ['Dashboard', route('dashboard')],
        ['Deposit', route('dashboard.deposit.index')],
        ['Checkout', null],
    ]"
>
    @if (! $gatewayEnabled)
        <x-dashboard.alert type="warning" class="mb-4">
            Online checkout is not configured yet. Please contact support for wallet funding options.
        </x-dashboard.alert>
    @else
        <x-dashboard.card class="mb-6">
            <form method="POST" action="{{ route('dashboard.deposit.store-checkout') }}" class="space-y-4" x-data="{ submitting: false }" @submit="submitting = true">
                @csrf
                <x-dashboard.input type="number" name="amount" label="Amount (NGN)" :min="$depositMin" step="0.01" required />
                <x-dashboard.button type="submit" icon="deposit" x-bind:disabled="submitting">Continue to payment</x-dashboard.button>
            </form>
        </x-dashboard.card>
        @if ($reservedAllowed)
            <x-dashboard.card>
                <p class="text-sm text-text-secondary mb-3">Or transfer to your dedicated reserved account.</p>
                <x-dashboard.button :href="route('dashboard.deposit.reserved')" variant="secondary">Show reserved account</x-dashboard.button>
            </x-dashboard.card>
        @endif
    @endif
</x-layout.page>
@endsection
