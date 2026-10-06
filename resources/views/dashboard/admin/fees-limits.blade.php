@extends('layouts.dashboard-admin')

@section('title', 'Fees & Limits')

@section('content')
<x-layout.page
    title="Fees & Limits"
    subtitle="Amount limits for creator wallet deposits and agent withdrawals."
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Fees & Limits', null],
    ]"
>
    <x-dashboard.card variant="solid">
        <form method="POST" action="{{ route('admin.fees-limits.update') }}" class="w-full space-y-6" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 w-full">
                <div class="w-full">
                    <x-dashboard.input
                        name="deposit_min_amount"
                        type="number"
                        label="Minimum deposit (NGN)"
                        :value="old('deposit_min_amount', $depositMinAmount)"
                        hint="Smallest amount a creator can add to their wallet in one payment."
                        required
                    />
                </div>
                <div class="w-full">
                    <x-dashboard.input
                        name="deposit_max_amount"
                        type="number"
                        label="Maximum deposit (NGN)"
                        :value="old('deposit_max_amount', $depositMaxAmount)"
                        hint="Largest amount a creator can add to their wallet in one payment."
                        required
                    />
                </div>
                <div class="w-full">
                    <x-dashboard.input
                        name="withdrawal_min_amount"
                        type="number"
                        label="Minimum withdrawal (NGN)"
                        :value="old('withdrawal_min_amount', $withdrawalMinAmount)"
                        hint="Smallest amount an agent can withdraw in one request."
                        required
                    />
                </div>
                <div class="w-full">
                    <x-dashboard.input
                        name="withdrawal_max_amount"
                        type="number"
                        label="Maximum withdrawal (NGN)"
                        :value="old('withdrawal_max_amount', $withdrawalMaxAmount)"
                        hint="Largest amount an agent can withdraw in one request."
                        required
                    />
                </div>
            </div>
            <x-dashboard.button type="submit" variant="primary" x-bind:disabled="submitting">Save fees & limits</x-dashboard.button>
        </form>
    </x-dashboard.card>
</x-layout.page>
@endsection
