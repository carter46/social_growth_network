@extends('layouts.dashboard-admin')

@section('title', 'Transactions')

@section('content')
<x-layout.page
    title="Transactions"
    subtitle="View and manage all transactions."
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Transactions', null],
    ]"
>
    <x-dashboard.table
        :empty="$transactions->isEmpty()"
        empty-title="No transactions yet"
        empty-description="Platform transactions will appear here."
        empty-icon="transactions"
        striped
    >
        <x-slot:head>
            <x-dashboard.th>User / Ref / Date</x-dashboard.th>
            <x-dashboard.th>Label</x-dashboard.th>
            <x-dashboard.th>Amount / Type</x-dashboard.th>
            <x-dashboard.th>Status</x-dashboard.th>
        </x-slot:head>

        @foreach ($transactions as $tx)
            <tr class="hover:bg-muted/50">
                <x-dashboard.td class="min-w-[10rem] max-w-[16rem]">
                    <div class="font-medium text-text-primary">{{ \App\Models\User::labelFor($tx->user) }}</div>
                    <div class="mt-0.5 font-mono text-[11px] leading-snug text-text-muted break-all">{{ $tx->reference }}</div>
                    <div class="mt-0.5 text-[11px] text-text-muted">{{ $tx->created_at->format('M j, Y H:i') }}</div>
                </x-dashboard.td>
                <x-dashboard.td>{{ $tx->label }}</x-dashboard.td>
                <x-dashboard.td class="whitespace-nowrap">
                    <div class="font-medium text-text-primary">{{ $tx->currency }} {{ number_format($tx->amount, 2) }}</div>
                    <div class="mt-0.5 text-[11px] text-text-muted">{{ $tx->type }}</div>
                </x-dashboard.td>
                <x-dashboard.td><x-dashboard.badge :status="$tx->status" /></x-dashboard.td>
            </tr>
        @endforeach
    </x-dashboard.table>

    <x-slot:pagination>
        <x-dashboard.pagination :paginator="$transactions" />
    </x-slot:pagination>
</x-layout.page>
@endsection
