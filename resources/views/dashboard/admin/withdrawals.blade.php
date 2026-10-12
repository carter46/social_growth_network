@extends('layouts.dashboard-admin')

@section('title', 'Withdrawals')

@section('content')
<x-layout.page
    title="Withdrawals"
    subtitle="Approve and send payouts to members' primary payout accounts."
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Withdrawals', null],
    ]"
>
    @if ($errors->any())
        <x-dashboard.alert type="danger">{{ $errors->first() }}</x-dashboard.alert>
    @endif
    @if (session('status'))
        <x-dashboard.alert type="success">{{ session('status') }}</x-dashboard.alert>
    @endif
    @if (session('error'))
        <x-dashboard.alert type="danger">{{ session('error') }}</x-dashboard.alert>
    @endif

    @if (! $gatewaysConfigured)
        <x-dashboard.alert type="warning" class="mb-4">No payout gateway is configured. Approving a withdrawal marks it paid without sending money; pay it manually first.</x-dashboard.alert>
    @elseif ($balances !== [])
        <x-dashboard.alert type="info" class="mb-4">
            Payout balances:
            @foreach ($balances as $b)
                <strong>{{ $b['name'] }}</strong> {{ $b['balance'] !== null ? money($b['balance'], 2) : 'not available' }}@if (! $loop->last) · @endif
            @endforeach
        </x-dashboard.alert>
    @endif

    <x-dashboard.table :empty="$withdrawals->isEmpty()" empty-title="No withdrawals" empty-description="Withdrawal requests will appear here." empty-icon="withdraw" striped :min-height="false">
        <x-slot:head>
            <x-dashboard.th>User</x-dashboard.th>
            <x-dashboard.th>Amount</x-dashboard.th>
            <x-dashboard.th class="hidden sm:table-cell">Destination</x-dashboard.th>
            <x-dashboard.th>Status</x-dashboard.th>
            <x-dashboard.th>Actions</x-dashboard.th>
        </x-slot:head>
        @foreach ($withdrawals as $w)
            <tr>
                <x-dashboard.td class="min-w-0 max-w-[9rem] sm:max-w-none">
                    <div class="font-medium text-text-primary">{{ \App\Models\User::labelFor($w->user) }}</div>
                    <div class="mt-0.5 font-mono text-[10px] leading-snug text-text-muted break-all">
                        <a href="{{ route('admin.withdrawals.show', $w) }}" class="underline">{{ $w->reference }}</a>
                    </div>
                </x-dashboard.td>
                <x-dashboard.td class="whitespace-nowrap">
                    {{ money($w->amount, 2) }}
                    @if (bccomp((string) $w->fee_amount, '0', 2) > 0)
                        <div class="text-xs text-text-muted">net {{ money($w->netPayoutAmount(), 2) }}</div>
                    @endif
                </x-dashboard.td>
                <x-dashboard.td class="hidden text-sm sm:table-cell">
                    {{ $w->destinationLabel() }}<br>
                    <span class="text-text-muted">{{ $w->bank_name }} · {{ $w->maskedAccountNumber() }} · {{ $w->account_name }}</span>
                    @if ($w->payoutAccount && ! $w->payoutAccount->isResolved())
                        <div class="text-xs font-medium text-warning">Account not verified by gateway</div>
                    @endif
                </x-dashboard.td>
                <x-dashboard.td>
                    <x-dashboard.badge :status="$w->status" />
                    @if ($w->internal_status && $w->internal_status !== $w->status)
                        <div class="mt-1 text-xs text-text-muted">{{ str_replace('_', ' ', $w->internal_status) }}</div>
                    @endif
                    @if ($w->latestAttempt)
                        <div class="mt-1 text-xs text-text-muted">{{ $w->latestAttempt->gateway_key }}: {{ $w->latestAttempt->status }}</div>
                    @endif
                </x-dashboard.td>
                <x-dashboard.td>
                    @if ($w->status === 'pending' || $w->internal_status === 'pending_review')
                        <div class="flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('admin.withdrawals.approve', $w) }}">
                                @csrf
                                <input type="hidden" name="confirm_send" value="1">
                                <x-dashboard.button type="submit" size="sm" variant="primary">Approve</x-dashboard.button>
                            </form>
                            <form method="POST" action="{{ route('admin.withdrawals.reject', $w) }}">
                                @csrf
                                <x-dashboard.button type="submit" size="sm" variant="danger">Reject</x-dashboard.button>
                            </form>
                        </div>
                    @elseif ($w->needsAdminDecision())
                        <x-dashboard.button :href="route('admin.withdrawals.show', $w)" size="sm" variant="primary">Decide</x-dashboard.button>
                    @endif
                </x-dashboard.td>
            </tr>
        @endforeach
    </x-dashboard.table>

    <x-slot:pagination>
        <x-dashboard.pagination :paginator="$withdrawals" />
    </x-slot:pagination>
</x-layout.page>
@endsection
