@extends($layout ?? 'layouts.dashboard-agent')

@section('title', 'Connect bank account')

@section('content')
@php $prefix = $prefix ?? 'agent'; @endphp
<x-layout.page
    title="Connect bank account"
    subtitle="For {{ $countryName }}, identity verification opens in our payout partner’s secure form. After that you’ll add your bank here."
    width="lg"
    :breadcrumb="[
        ['Agent', route('agent')],
        ['Wallet', route('agent.wallet')],
        ['Connect bank', null],
    ]"
>
    @if (session('error'))
        <x-dashboard.alert type="danger" class="mb-4">{{ session('error') }}</x-dashboard.alert>
    @endif

    <x-dashboard.card class="space-y-4">
        <p class="text-sm text-text-secondary">
            Your profile country is <span class="font-semibold text-text-primary">{{ $countryName }}</span>.
            That payout route needs a short verification step with our payment partner, then you return here to finish.
        </p>
        <form method="POST" action="{{ route('agent.payouts.connect.hosted') }}" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <x-dashboard.button type="submit" icon="withdraw" x-bind:disabled="submitting">
                Continue setup
            </x-dashboard.button>
            <x-dashboard.button :href="route('agent.wallet')" variant="secondary" class="ml-2">Cancel</x-dashboard.button>
        </form>
    </x-dashboard.card>
</x-layout.page>
@endsection
