@extends('layouts.dashboard-user')

@section('title', 'Fund wallet')

@section('content')
<x-layout.page
    title="Fund wallet"
    width="full"
    :breadcrumb="[
        ['Dashboard', route('dashboard')],
        ['Deposit', route('dashboard.deposit.index')],
        ['Billing', null],
    ]"
>
    @if (! $gatewayEnabled)
        <x-dashboard.alert type="warning" class="mb-4">
            Online card payment is not configured yet. Please contact support for wallet funding options.
        </x-dashboard.alert>
    @else
        <x-dashboard.card class="mb-6">
            <form method="POST" action="{{ route('dashboard.deposit.store-checkout') }}" class="space-y-4" x-data="{ submitting: false }" @submit="submitting = true">
                @csrf
                <x-dashboard.input
                    type="number"
                    name="amount"
                    :label="'Amount ('.site_currency()->code.')'"
                    :min="$depositMin"
                    :max="$depositMax"
                    step="0.01"
                    :hint="'Min '.money($depositMin, 2).' · Max '.money($depositMax, 2).' per payment'"
                    required
                />
                @include('partials.payments.gateway-choice', ['gateways' => $gateways, 'selected' => old('gateway', $defaultGateway)])
                <x-dashboard.button type="submit" icon="deposit" x-bind:disabled="submitting">Continue to payment</x-dashboard.button>
            </form>
        </x-dashboard.card>
    @endif
</x-layout.page>
@endsection
