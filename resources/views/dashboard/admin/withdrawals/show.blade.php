@extends('layouts.dashboard-admin')

@section('title', 'Withdrawal '.$withdrawal->reference)

@section('content')
@php
    $account = $withdrawal->payoutAccount;
    $latest = $withdrawal->attempts->last();
    $requote = (array) ($withdrawal->requote_snapshot ?? []);
    $stored = (array) ($withdrawal->quote_snapshot ?? []);
    $retryOptions = collect($routeOptions)->sortBy(fn ($name, $key) => $key === $latest?->gateway_key ? 1 : 0)->all();
@endphp
<x-layout.page
    :title="'Withdrawal '.$withdrawal->reference"
    subtitle="Payout details, fee quotes and every provider attempt."
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Withdrawals', route('admin.withdrawals')],
        [$withdrawal->reference, null],
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

    @php
        $bachsBalance = $balances['bachs']['balance'] ?? null;
        $bachsEmpty = $bachsBalance !== null && bccomp((string) $bachsBalance, '0', 2) <= 0;
    @endphp
    @if ($balances !== [])
        <x-dashboard.alert :type="$bachsEmpty ? 'warning' : 'info'" class="mb-4">
            <p class="font-medium">Gateway platform balances (USD available right now)</p>
            <p class="mt-1">
                @foreach ($balances as $b)
                    <strong>{{ $b['name'] }}</strong> {{ $b['balance'] !== null ? money($b['balance'], 2) : 'not available' }}@if (! $loop->last) · @endif
                @endforeach
            </p>
            <p class="mt-2 text-xs">
                Pulled live from each gateway (Bachs <code class="text-[11px]">GET /v1/balances</code>). Connected agents still need this platform float funded before payouts can leave.
            </p>
        </x-dashboard.alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-dashboard.card class="space-y-3 text-sm">
            <h2 class="text-lg font-semibold text-text-primary">Withdrawal</h2>
            <dl class="space-y-2">
                <div class="flex justify-between gap-4"><dt class="text-text-muted">User</dt><dd>{{ \App\Models\User::labelFor($withdrawal->user) }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-text-muted">Requested</dt><dd class="font-semibold">{{ money($withdrawal->amount, 2) }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-text-muted">Provider fee</dt><dd>{{ money($withdrawal->provider_fee, 2) }}</dd></div>
                @if (bccomp((string) $withdrawal->platform_fee, '0', 2) > 0)
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Platform fee</dt><dd>{{ money($withdrawal->platform_fee, 2) }}</dd></div>
                @endif
                <div class="flex justify-between gap-4"><dt class="text-text-muted">Net sent</dt><dd class="font-semibold">{{ money($withdrawal->netPayoutAmount(), 2) }}</dd></div>
                @if (bccomp((string) $withdrawal->absorbed_fee, '0', 2) > 0)
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Absorbed by platform</dt><dd>{{ money($withdrawal->absorbed_fee, 2) }}</dd></div>
                @endif
                @if (bccomp((string) $withdrawal->bounce_fee_amount, '0', 2) > 0)
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Bounce fee</dt><dd>{{ money($withdrawal->bounce_fee_amount, 2) }} ({{ $withdrawal->bounce_failure_code }})@if ($withdrawal->bounce_waived_at) · waived @endif</dd></div>
                @endif
                <div class="flex justify-between gap-4"><dt class="text-text-muted">Status</dt><dd><x-dashboard.badge :status="$withdrawal->status" /></dd></div>
                @if ($withdrawal->internal_status)
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Internal</dt><dd>{{ str_replace('_', ' ', $withdrawal->internal_status) }}</dd></div>
                @endif
            </dl>

            @if ($withdrawal->status === 'pending' || $withdrawal->internal_status === 'pending_review')
                <form method="POST" action="{{ route('admin.withdrawals.approve', $withdrawal) }}" class="space-y-2 pt-2">
                    @csrf
                    <input type="hidden" name="confirm_send" value="1">
                    @if (count($routeOptions) > 1)
                        <label class="block text-xs text-text-muted" for="gateway">Gateway</label>
                        <select id="gateway" name="gateway" class="w-full rounded-lg border border-border-subtle px-3 py-2 text-sm">
                            <option value="">Route default ({{ reset($routeOptions) }})</option>
                            @foreach ($routeOptions as $key => $name)
                                <option value="{{ $key }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    @endif
                    <x-dashboard.input name="approval_note" label="Note (optional)" />
                    <x-dashboard.button type="submit" size="sm" variant="primary">Approve &amp; send</x-dashboard.button>
                </form>
            @endif

            @if ($withdrawal->canBeRejectedByAdmin() && ! $latest?->isLive())
                <form method="POST" action="{{ route('admin.withdrawals.reject', $withdrawal) }}" class="space-y-2 pt-2">
                    @csrf
                    <x-dashboard.input name="notes" label="Rejection reason" required maxlength="1000" />
                    <x-dashboard.button type="submit" size="sm" variant="danger">Reject &amp; refund</x-dashboard.button>
                </form>
            @endif

            @if ($withdrawal->awaitingAgentConfirmation())
                <x-dashboard.alert type="warning">
                    Waiting for the member to accept a higher provider fee ({{ money($requote['provider_fee'] ?? 0, 2) }} on {{ $requote['gateway_key'] ?? 'the gateway' }})
                    @if ($withdrawal->confirmation_expires_at) until {{ $withdrawal->confirmation_expires_at->toDayDateTimeString() }}@endif.
                </x-dashboard.alert>
            @endif

            @if (bccomp((string) $withdrawal->bounce_fee_amount, '0', 2) > 0 && ! $withdrawal->bounce_waived_at)
                <form method="POST" action="{{ route('admin.withdrawals.waive-bounce-fee', $withdrawal) }}" onsubmit="return confirm('Waive the bounce fee and credit it back?');">
                    @csrf
                    <x-dashboard.button type="submit" size="sm" variant="secondary">Waive bounce fee</x-dashboard.button>
                </form>
            @endif
        </x-dashboard.card>

        <x-dashboard.card class="space-y-3 text-sm">
            <h2 class="text-lg font-semibold text-text-primary">Destination</h2>
            <dl class="space-y-2">
                <div class="flex justify-between gap-4"><dt class="text-text-muted">Route</dt><dd>{{ $withdrawal->destinationLabel() }} ({{ $withdrawal->destination_currency ?: 'NGN' }})</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-text-muted">{{ $withdrawal->method === 'mobile_money' ? 'Network' : 'Bank' }}</dt><dd>{{ $withdrawal->bank_name }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-text-muted">Account</dt><dd class="font-mono break-all">{{ $withdrawal->accountNumber() ?: $withdrawal->maskedAccountNumber() }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-text-muted">Holder name</dt><dd>{{ $withdrawal->account_name }}</dd></div>
                @if ($withdrawal->user)
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Member name</dt><dd>{{ $withdrawal->user->name }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Email</dt><dd class="break-all">{{ $withdrawal->user->email }}</dd></div>
                @endif
                @if ($account)
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Verification</dt><dd>
                        @if ($account->isResolved())
                            <x-dashboard.badge status="completed">Verified ({{ $account->resolved_by }})</x-dashboard.badge>
                        @elseif ($account->isRejected())
                            <x-dashboard.badge status="rejected">Rejected</x-dashboard.badge>
                        @else
                            <x-dashboard.badge status="pending">Account not verified by gateway</x-dashboard.badge>
                        @endif
                    </dd></div>
                @endif
            </dl>
            @if ($account && ! $account->isResolved() && ! $account->isRejected() && ! $withdrawal->isTerminal())
                <form method="POST" action="{{ route('admin.withdrawals.reject-account', $withdrawal) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div class="min-w-[12rem] flex-1"><x-dashboard.input name="reason" label="Reject account (reason)" required /></div>
                    <x-dashboard.button type="submit" size="sm" variant="danger">Reject account</x-dashboard.button>
                </form>
            @endif

            <h3 class="pt-2 font-semibold text-text-primary">Fee quotes</h3>
            <dl class="space-y-1 text-xs">
                <div class="flex justify-between gap-4"><dt class="text-text-muted">At request</dt><dd>{{ isset($stored['provider_fee']) ? money($stored['provider_fee'], 2) : '—' }} · {{ $stored['source'] ?? '—' }} · {{ $stored['gateway_key'] ?? '' }} {{ isset($stored['quoted_at']) ? \Carbon\Carbon::parse($stored['quoted_at'])->toDayDateTimeString() : '' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-text-muted">At approval</dt><dd>{{ isset($requote['provider_fee']) ? money($requote['provider_fee'], 2).' · '.($requote['decision'] ?? '').' · '.($requote['gateway_key'] ?? '') : '—' }}</dd></div>
                @if (! empty($stored['fx_rate']))
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">FX estimate</dt><dd>{{ $stored['destination_amount'] ?? '' }} {{ $stored['destination_currency'] ?? '' }} at {{ $stored['fx_rate'] }}</dd></div>
                @endif
            </dl>
        </x-dashboard.card>
    </div>

    <x-dashboard.card class="mt-6 space-y-3 text-sm">
        <h2 class="text-lg font-semibold text-text-primary">Payout attempts</h2>
        @forelse ($withdrawal->attempts as $attempt)
            <div class="rounded-xl border border-border-subtle p-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-mono text-xs">{{ $attempt->reference }}</span>
                    <x-dashboard.badge :status="$attempt->status" />
                </div>
                <p class="mt-1 text-xs text-text-muted">
                    {{ $attempt->gateway_key }} · {{ str_replace('_', ' ', $attempt->trigger) }} · {{ money($attempt->amount, 2) }}
                    @if ($attempt->provider_reference) · provider ref {{ $attempt->provider_reference }} @endif
                    · {{ $attempt->created_at?->toDayDateTimeString() }}
                    @if ($attempt->authoriser) · authorised by {{ \App\Models\User::labelFor($attempt->authoriser) }} @endif
                </p>
                @if ($attempt->failure_code || $attempt->failure_reason)
                    <p class="mt-1 text-xs text-danger">{{ $attempt->failure_code }} {{ $attempt->failure_reason }} @if ($attempt->failure_class) ({{ str_replace('_', ' ', $attempt->failure_class) }}) @endif</p>
                @endif
            </div>
        @empty
            <p class="text-text-muted">No payout attempts yet.</p>
        @endforelse

        @if ($latest?->isLive() && ! $withdrawal->isTerminal())
            <x-dashboard.alert type="info">Waiting for {{ $latest->gateway_key }} to confirm the result. Retry and refund stay disabled until then.</x-dashboard.alert>
        @elseif ($withdrawal->needsAdminDecision())
            <div class="grid gap-4 md:grid-cols-2">
                @if ($retryOptions !== [] && $latest?->failure_class !== \App\Modules\Wallet\Payments\FailureClass::ACCOUNT_DETAILS)
                    <form method="POST" action="{{ route('admin.withdrawals.retry', $withdrawal) }}" class="space-y-2 rounded-xl border border-border-subtle p-3" onsubmit="return confirm('Send this payout through the selected gateway? It will be re-quoted first.');">
                        @csrf
                        <input type="hidden" name="confirm_retry" value="1">
                        <label class="block text-xs text-text-muted" for="retry_gateway">Retry via</label>
                        <select id="retry_gateway" name="gateway" class="w-full rounded-lg border border-border-subtle px-3 py-2 text-sm">
                            @foreach ($retryOptions as $key => $name)
                                <option value="{{ $key }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        <x-dashboard.button type="submit" size="sm" variant="primary">Retry via selected gateway</x-dashboard.button>
                    </form>
                @endif
                <form method="POST" action="{{ route('admin.withdrawals.refund', $withdrawal) }}" class="space-y-2 rounded-xl border border-border-subtle p-3" onsubmit="return confirm('Refund the full amount to the member\'s wallet?');">
                    @csrf
                    <input type="hidden" name="confirm_refund" value="1">
                    <x-dashboard.input name="notes" label="Note (optional)" />
                    <x-dashboard.button type="submit" size="sm" variant="secondary">Refund to wallet</x-dashboard.button>
                </form>
            </div>
        @endif
    </x-dashboard.card>

    <x-dashboard.card class="mt-6">
        <h2 class="mb-3 text-lg font-semibold text-text-primary">Timeline</h2>
        <ul class="space-y-2 text-sm">
            @forelse ($withdrawal->timelineEvents as $event)
                <li class="flex flex-wrap justify-between gap-2 border-b border-border-subtle pb-2">
                    <span>{{ $event->label }}</span>
                    <span class="text-text-muted">{{ $event->occurred_at?->toDayDateTimeString() }}</span>
                </li>
            @empty
                <li class="text-text-muted">No timeline events yet.</li>
            @endforelse
        </ul>
    </x-dashboard.card>
</x-layout.page>
@endsection
