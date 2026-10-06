@extends('layouts.dashboard-admin')

@section('title', 'Reconciliation')

@section('content')
<x-layout.page
    title="Reconciliation"
    subtitle="Compare Monnify, webhooks, and ledger status."
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Reconciliation', null],
    ]"
>
    @if (! $monnifyEnabled)
        <x-dashboard.alert type="warning" class="mb-4">Monnify is not enabled in Settings.</x-dashboard.alert>
    @endif

    <x-dashboard.table :empty="$rows->isEmpty()" empty-title="Nothing to reconcile" empty-description="Stuck or mismatched payments will appear here." empty-icon="audit" striped>
        <x-slot:head>
            <x-dashboard.th>User</x-dashboard.th>
            <x-dashboard.th>Amount</x-dashboard.th>
            <x-dashboard.th>Monnify</x-dashboard.th>
            <x-dashboard.th>Ledger</x-dashboard.th>
            <x-dashboard.th>Webhook</x-dashboard.th>
            <x-dashboard.th>Difference</x-dashboard.th>
            <x-dashboard.th>Action</x-dashboard.th>
        </x-slot:head>
        @foreach ($rows as $row)
            <tr>
                <x-dashboard.td class="text-sm">
                    <span class="font-medium text-text-primary">{{ $row['user'] }}</span>
                    <span class="mt-0.5 block text-xs text-text-muted">{{ $row['reference'] }}</span>
                </x-dashboard.td>
                <x-dashboard.td>₦{{ number_format($row['amount'], 2) }}</x-dashboard.td>
                <x-dashboard.td>{{ $row['monnify_status'] ?: '—' }}</x-dashboard.td>
                <x-dashboard.td>{{ $row['ledger_status'] }}</x-dashboard.td>
                <x-dashboard.td>{{ $row['webhook'] ?: '—' }}</x-dashboard.td>
                <x-dashboard.td class="text-sm">{{ $row['difference'] }}</x-dashboard.td>
                <x-dashboard.td>
                    @if ($row['type'] === 'funding' && $row['difference'] !== 'OK')
                        <form method="POST" action="{{ route('admin.reconciliation.fix-funding', $row['model']) }}">
                            @csrf
                            <x-dashboard.button type="submit" size="sm" variant="secondary">Fix</x-dashboard.button>
                        </form>
                    @elseif ($row['type'] === 'withdrawal' && $row['difference'] !== 'OK')
                        <form method="POST" action="{{ route('admin.reconciliation.sync-withdrawal', $row['model']) }}">
                            @csrf
                            <x-dashboard.button type="submit" size="sm" variant="secondary">Sync</x-dashboard.button>
                        </form>
                    @else
                        —
                    @endif
                </x-dashboard.td>
            </tr>
        @endforeach
    </x-dashboard.table>
</x-layout.page>
@endsection
