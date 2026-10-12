@extends($layout ?? 'layouts.dashboard-user')

@section('title', 'Withdrawal')

@section('content')
@php $prefix = $prefix ?? 'dashboard'; @endphp
<x-layout.page
    title="Withdrawal"
    width="full"
    :breadcrumb="[
        [$prefix === 'agent' ? 'Agent' : 'Dashboard', route($prefix === 'agent' ? 'agent' : 'dashboard')],
        ['Withdraw', route($prefix.'.withdrawal.index')],
        ['Receipt', null],
    ]"
>
    <p class="-mt-2 font-mono text-xs leading-relaxed text-text-muted break-all sm:text-sm">
        {{ $withdrawal->reference }}
    </p>

    @if (session('status'))
        <x-dashboard.alert type="success" class="mb-4">{{ session('status') }}</x-dashboard.alert>
    @endif
    @if (session('error'))
        <x-dashboard.alert type="danger" class="mb-4">{{ session('error') }}</x-dashboard.alert>
    @endif

    @if ($withdrawal->awaitingAgentConfirmation())
        @php
            $requote = (array) ($withdrawal->requote_snapshot ?? []);
            $newProviderFee = (string) ($requote['provider_fee'] ?? $withdrawal->provider_fee);
            $newFee = bcadd($newProviderFee, (string) $withdrawal->platform_fee, 2);
            $newNet = bcsub((string) $withdrawal->amount, $newFee, 2);
        @endphp
        <x-dashboard.card class="mb-6 space-y-3 text-sm">
            <h2 class="text-base font-semibold text-text-primary">The payout fee went up</h2>
            <p class="text-text-secondary">The payment provider's fee for this payout is now higher than when you requested it. Accept the new fee, or cancel for a full refund.</p>
            <dl class="space-y-1">
                <div class="flex justify-between gap-4"><dt>You withdraw</dt><dd class="font-medium">{{ money($withdrawal->amount, 2) }}</dd></div>
                <div class="flex justify-between gap-4"><dt>Provider fee</dt><dd>{{ money($withdrawal->provider_fee, 2) }} → <strong>{{ money($newProviderFee, 2) }}</strong></dd></div>
                @if (bccomp((string) $withdrawal->platform_fee, '0', 2) > 0)
                    <div class="flex justify-between gap-4"><dt>Platform fee</dt><dd>{{ money($withdrawal->platform_fee, 2) }}</dd></div>
                @endif
                <div class="flex justify-between gap-4 border-t border-border-subtle pt-1"><dt>Sent to your account</dt><dd class="font-semibold">{{ money($newNet, 2) }}</dd></div>
            </dl>
            @if ($withdrawal->confirmation_expires_at)
                <p class="text-xs text-text-muted">If you don't answer by {{ $withdrawal->confirmation_expires_at->toDayDateTimeString() }}, the withdrawal is cancelled and fully refunded.</p>
            @endif
            <div class="flex flex-wrap gap-2">
                @if (bccomp($newNet, '0', 2) > 0)
                    <form method="POST" action="{{ route($prefix.'.withdrawal.accept-fee', $withdrawal) }}">
                        @csrf
                        <x-dashboard.button type="submit" size="sm" variant="primary">Accept new fee</x-dashboard.button>
                    </form>
                @endif
                <form method="POST" action="{{ route($prefix.'.withdrawal.cancel', $withdrawal) }}" onsubmit="return confirm('Cancel this withdrawal? The full amount returns to your balance.');">
                    @csrf
                    <x-dashboard.button type="submit" size="sm" variant="secondary">Cancel and refund</x-dashboard.button>
                </form>
            </div>
        </x-dashboard.card>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-dashboard.card>
            <dl class="space-y-2 text-sm">
                <div class="flex flex-col gap-1 sm:flex-row sm:justify-between sm:gap-3">
                    <dt class="shrink-0 text-text-muted">Reference</dt>
                    <dd class="font-mono text-xs break-all sm:text-right sm:text-sm">{{ $withdrawal->reference }}</dd>
                </div>
                <div class="flex justify-between"><dt class="text-text-muted">Amount</dt><dd class="font-medium">{{ money($withdrawal->amount, 2) }}</dd></div>
                @if (bccomp((string) $withdrawal->fee_amount, '0', 2) > 0)
                    <div class="flex justify-between"><dt class="text-text-muted">Withdrawal fee</dt><dd>{{ money($withdrawal->fee_amount, 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-text-muted">Sent to your account</dt><dd class="font-medium">{{ money($withdrawal->netPayoutAmount(), 2) }}</dd></div>
                @endif
                <div class="flex justify-between"><dt class="text-text-muted">Destination</dt><dd>{{ $withdrawal->destinationLabel() }}</dd></div>
                <div class="flex justify-between"><dt class="text-text-muted">{{ $withdrawal->method === 'mobile_money' ? 'Network' : 'Bank' }}</dt><dd>{{ $withdrawal->bank_name }}</dd></div>
                <div class="flex justify-between"><dt class="text-text-muted">Account</dt><dd>{{ $withdrawal->maskedAccountNumber() }}</dd></div>
                <div class="flex justify-between"><dt class="text-text-muted">Name</dt><dd>{{ $withdrawal->account_name }}</dd></div>
                @if (bccomp((string) $withdrawal->bounce_fee_amount, '0', 2) > 0)
                    <div class="flex justify-between"><dt class="text-text-muted">Bounce fee</dt><dd>{{ money($withdrawal->bounce_fee_amount, 2) }}@if ($withdrawal->bounce_waived_at) (waived)@endif</dd></div>
                @endif
            </dl>
            @if ($withdrawal->isOpen())
                <p class="mt-4 text-sm text-text-secondary">This withdrawal is in progress. You can't change payout accounts until it finishes.</p>
            @endif
        </x-dashboard.card>
        <x-dashboard.card>
            <h3 class="font-semibold text-text-primary mb-3">Timeline</h3>
            <ol class="space-y-3 border-l border-border-subtle pl-4">
                @forelse ($withdrawal->timelineEvents as $event)
                    <li>
                        <p class="text-xs text-text-muted">{{ $event->occurred_at?->format('H:i') }} · {{ $event->occurred_at?->toDayDateTimeString() }}</p>
                        <p class="text-sm font-medium text-text-primary">{{ $event->label }}</p>
                    </li>
                @empty
                    <li class="text-sm text-text-muted">No timeline events yet.</li>
                @endforelse
            </ol>
        </x-dashboard.card>
    </div>
</x-layout.page>
@endsection
