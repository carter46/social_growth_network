@extends('layouts.dashboard-user')

@section('title', 'Deposit History')

@section('content')
<x-layout.page
    title="Deposit History"
    width="full"
    :breadcrumb="[
        ['Dashboard', route('dashboard')],
        ['Deposit', null],
    ]"
>
    <x-slot:actions>
        <x-dashboard.button :href="route('dashboard.deposit.create-checkout')" icon="deposit">Fund wallet</x-dashboard.button>
    </x-slot:actions>

    <x-dashboard.table
        :empty="$fundings->isEmpty()"
        empty-title="No deposits yet"
        empty-description="Fund via Monnify Checkout or your reserved account."
        empty-icon="deposit"
        :empty-action="['href' => route('dashboard.deposit.create-checkout'), 'label' => 'Fund wallet']"
        striped
    >
        <x-slot:head>
            <x-dashboard.th>Reference</x-dashboard.th>
            <x-dashboard.th>Method</x-dashboard.th>
            <x-dashboard.th>Amount</x-dashboard.th>
            <x-dashboard.th>Status</x-dashboard.th>
        </x-slot:head>
        @foreach ($fundings as $f)
            <tr class="hover:bg-muted/50">
                <x-dashboard.td class="font-medium">
                    <a href="{{ route('dashboard.deposit.show', $f) }}" class="underline">{{ $f->reference }}</a>
                </x-dashboard.td>
                <x-dashboard.td>{{ $f->method }}</x-dashboard.td>
                <x-dashboard.td>₦{{ number_format($f->amount, 2) }}</x-dashboard.td>
                <x-dashboard.td><x-dashboard.badge :status="$f->status" /></x-dashboard.td>
            </tr>
        @endforeach
    </x-dashboard.table>

    <x-slot:pagination>
        <x-dashboard.pagination :paginator="$fundings" />
    </x-slot:pagination>
</x-layout.page>
@endsection
