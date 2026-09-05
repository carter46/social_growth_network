@extends('layouts.dashboard-user')

@section('title', 'History')

@section('content')
<x-layout.page
    title="Transaction History"
    width="full"
    :breadcrumb="[
        ['Dashboard', route('dashboard')],
        ['History', null],
    ]"
>
    <x-dashboard.table
        :empty="$transactions->isEmpty()"
        empty-title="No transactions yet"
        empty-description="Wallet credits, debits, and service payments will appear here."
        empty-icon="history"
        striped
        :min-height="false"
        class="[&_table]:text-xs sm:[&_table]:text-sm [&_td]:px-3 [&_td]:py-2.5 sm:[&_td]:px-6 sm:[&_td]:py-4 [&_th]:px-3 [&_th]:py-2 sm:[&_th]:px-6 [&_th]:py-3"
    >
        <x-slot:head>
            <x-dashboard.th class="min-w-0">Ref</x-dashboard.th>
            <x-dashboard.th>Type</x-dashboard.th>
            <x-dashboard.th class="min-w-0 max-w-[7rem] sm:max-w-none">Label</x-dashboard.th>
            <x-dashboard.th>Amount</x-dashboard.th>
            <x-dashboard.th>Status</x-dashboard.th>
        </x-slot:head>
        @foreach ($transactions as $tx)
            <tr class="hover:bg-muted/50">
                <x-dashboard.td class="min-w-0 font-medium">
                    <span class="block font-mono text-[10px] leading-snug break-all sm:text-xs">{{ $tx->reference }}</span>
                </x-dashboard.td>
                <x-dashboard.td class="whitespace-nowrap capitalize">{{ str_replace('_', ' ', $tx->type) }}</x-dashboard.td>
                <x-dashboard.td class="min-w-0 break-words text-[11px] leading-snug sm:text-sm">{{ $tx->label }}</x-dashboard.td>
                <x-dashboard.td class="whitespace-nowrap tabular-nums">₦{{ number_format($tx->amount, 2) }}</x-dashboard.td>
                <x-dashboard.td class="whitespace-nowrap"><x-dashboard.badge :status="$tx->status" /></x-dashboard.td>
            </tr>
        @endforeach
    </x-dashboard.table>

    <x-slot:pagination>
        <x-dashboard.pagination :paginator="$transactions" />
    </x-slot:pagination>
</x-layout.page>
@endsection
