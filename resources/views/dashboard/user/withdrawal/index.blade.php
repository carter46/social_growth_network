@extends($layout ?? 'layouts.dashboard-user')

@section('title', 'Withdrawals')

@section('content')
@php $prefix = $prefix ?? 'dashboard'; @endphp
<x-layout.page
    title="Withdrawal History"
    width="full"
    :breadcrumb="[
        [$prefix === 'agent' ? 'Agent' : 'Dashboard', route($prefix === 'agent' ? 'agent' : 'dashboard')],
        ['Withdraw', null],
    ]"
>
    <x-slot:actions>
        @if (! ($hasOpen ?? false))
            <x-dashboard.button :href="route($prefix.'.withdrawal.create')" icon="withdraw">New withdrawal</x-dashboard.button>
        @endif
        <x-dashboard.button :href="route($prefix.'.banks.index')" variant="secondary" icon="withdraw">My Bank</x-dashboard.button>
    </x-slot:actions>

    @if ($hasOpen ?? false)
        <x-dashboard.alert type="info" class="mb-4">
            You have a withdrawal in progress. New requests are blocked until it completes.
        </x-dashboard.alert>
    @endif

    <x-dashboard.table
        :empty="$withdrawals->isEmpty()"
        empty-title="No withdrawals yet"
        empty-description="Request a bank withdrawal when you have available NGN balance."
        empty-icon="withdraw"
        :empty-action="['href' => route($prefix.'.withdrawal.create'), 'label' => 'New withdrawal']"
        striped
    >
        <x-slot:head>
            <x-dashboard.th>Reference</x-dashboard.th>
            <x-dashboard.th>Amount</x-dashboard.th>
            <x-dashboard.th>Status</x-dashboard.th>
        </x-slot:head>
        @foreach ($withdrawals as $w)
            <tr class="hover:bg-muted/50">
                <x-dashboard.td class="font-medium">
                    <a href="{{ route($prefix.'.withdrawal.show', $w) }}" class="underline">{{ $w->reference }}</a>
                </x-dashboard.td>
                <x-dashboard.td>₦{{ number_format($w->amount, 2) }}</x-dashboard.td>
                <x-dashboard.td><x-dashboard.badge :status="$w->status" /></x-dashboard.td>
            </tr>
        @endforeach
    </x-dashboard.table>

    <x-slot:pagination>
        <x-dashboard.pagination :paginator="$withdrawals" />
    </x-slot:pagination>
</x-layout.page>
@endsection
