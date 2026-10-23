@extends('layouts.dashboard-admin')

@section('title', 'Order '.$order->reference)

@section('content')
@php
    $meta = $order->payment_metadata ?? [];
    $hasProof = ! empty($meta['proof_path'] ?? null);
    $awaitingBank = $order->isAwaitingManualBankTransfer();
@endphp
<x-layout.page
    :title="'Order '.$order->reference"
    subtitle="Platform service purchase"
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Orders', route('admin.orders')],
        [$order->reference, null],
    ]"
>
    @if (session('status'))
        <x-dashboard.alert type="success" class="mb-4">{{ session('status') }}</x-dashboard.alert>
    @endif
    @if (session('error'))
        <x-dashboard.alert type="danger" class="mb-4">{{ session('error') }}</x-dashboard.alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-dashboard.card class="space-y-3 text-sm">
            <h2 class="text-lg font-semibold text-text-primary">Order</h2>
            <dl class="space-y-2">
                <div class="flex justify-between gap-4"><dt class="text-text-muted">User</dt><dd>{{ \App\Models\User::labelFor($order->user) }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-text-muted">Amount</dt><dd class="font-semibold">{{ money((float) $order->total_amount, 2) }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-text-muted">Payment</dt><dd>{{ str_replace('_', ' ', $order->payment_method ?? '—') }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-text-muted">Status</dt><dd><x-dashboard.badge :status="$order->status" /></dd></div>
                @if ($order->payment_submitted_at)
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Proof submitted</dt><dd>{{ $order->payment_submitted_at->format('M j, Y g:i A') }}</dd></div>
                @endif
                @if ($order->payment_confirmed_at)
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Confirmed</dt><dd>{{ $order->payment_confirmed_at->format('M j, Y g:i A') }}</dd></div>
                @endif
                @if ($order->payment_method === \App\Models\Order::PAYMENT_MANUAL_BANK_TRANSFER)
                    @php
                        $paymentSession = (int) ($meta['manual_payment_session'] ?? 1);
                        $maxSessions = \App\Modules\Catalog\Services\PlatformCheckoutService::MANUAL_PAYMENT_MAX_SESSIONS;
                    @endphp
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Payment attempts</dt><dd>{{ $paymentSession }} / {{ $maxSessions }}</dd></div>
                    @if (! empty($meta['manual_payment_failed_at']))
                        <div class="flex justify-between gap-4"><dt class="text-text-muted">Last window expired</dt><dd>{{ \Illuminate\Support\Carbon::parse($meta['manual_payment_failed_at'])->format('M j, Y g:i A') }}</dd></div>
                    @endif
                    @if (! empty($meta['manual_payment_cancelled_at']))
                        <div class="flex justify-between gap-4"><dt class="text-text-muted">Cancelled at</dt><dd>{{ \Illuminate\Support\Carbon::parse($meta['manual_payment_cancelled_at'])->format('M j, Y g:i A') }}</dd></div>
                    @endif
                    @if (! empty($meta['cancel_reason']))
                        <div class="flex justify-between gap-4"><dt class="text-text-muted">Cancel reason</dt><dd class="text-right">{{ $meta['cancel_reason'] }}</dd></div>
                    @endif
                @endif
                @if ($order->paymentConfirmer)
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Confirmed by</dt><dd>{{ $order->paymentConfirmer->name }}</dd></div>
                @endif
            </dl>

            @if ($awaitingBank)
                <div class="flex flex-wrap gap-2 pt-2">
                    <form method="POST" action="{{ route('admin.orders.confirm', $order) }}">
                        @csrf
                        <x-dashboard.button type="submit" size="sm" variant="primary">Confirm payment</x-dashboard.button>
                    </form>
                    <form method="POST" action="{{ route('admin.orders.reject', $order) }}" class="space-y-2" onsubmit="return confirm('Cancel this order and release any domain holds?');">
                        @csrf
                        <textarea name="notes" rows="2" placeholder="Reason (optional)" class="w-full rounded-lg border border-border-subtle bg-surface px-3 py-2 text-sm">{{ old('notes') }}</textarea>
                        <x-dashboard.button type="submit" size="sm" variant="danger">Reject / cancel</x-dashboard.button>
                    </form>
                </div>
            @endif

            @if ($order->payment_method === 'gateway' && in_array($order->status, ['pending', 'processing'], true))
                <div class="flex flex-wrap gap-2 pt-2">
                    <form method="POST" action="{{ route('admin.reconciliation.confirm-order', $order) }}">
                        @csrf
                        <x-dashboard.button type="submit" size="sm" variant="primary">Verify with Bachs and fulfill</x-dashboard.button>
                    </form>
                    <p class="w-full text-xs text-text-muted">Calls Bachs to confirm the checkout was paid, then creates the campaign if it was.</p>
                </div>
            @endif
        </x-dashboard.card>

        <x-dashboard.card class="space-y-3 text-sm">
            <h2 class="text-lg font-semibold text-text-primary">Items</h2>
            <ul class="space-y-2">
                @foreach ($order->items as $item)
                    <li class="flex justify-between gap-4 border-b border-border-subtle pb-2">
                        <span>{{ $item->variant?->displayLabel() ?? 'Product #'.$item->item_id }} × {{ $item->quantity }}</span>
                        <span>{{ money((float) $item->line_total, 2) }}</span>
                    </li>
                @endforeach
            </ul>

            @if ($order->payment_method === \App\Models\Order::PAYMENT_MANUAL_BANK_TRANSFER)
                <h3 class="text-sm font-semibold text-text-primary pt-2">Bank details (company)</h3>
                <dl class="space-y-1">
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Bank</dt><dd>{{ $bankDetails['bank_name'] ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Account</dt><dd class="font-mono">{{ $bankDetails['account_number'] ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Name</dt><dd>{{ $bankDetails['account_name'] ?: '—' }}</dd></div>
                    @if (filled($bankDetails['swift'] ?? null))
                        <div class="flex justify-between gap-4"><dt class="text-text-muted">SWIFT/BIC</dt><dd class="font-mono">{{ $bankDetails['swift'] }}</dd></div>
                    @endif
                    @if (filled($bankDetails['iban'] ?? null))
                        <div class="flex justify-between gap-4"><dt class="text-text-muted">IBAN</dt><dd class="font-mono">{{ $bankDetails['iban'] }}</dd></div>
                    @endif
                </dl>

                @if (! empty($meta['payer_bank_name']) || ! empty($meta['transfer_reference']))
                    <h3 class="text-sm font-semibold text-text-primary pt-2">Customer transfer</h3>
                    <dl class="space-y-1">
                        @if (! empty($meta['payer_bank_name']))
                            <div class="flex justify-between gap-4"><dt class="text-text-muted">Payer bank</dt><dd>{{ $meta['payer_bank_name'] }}</dd></div>
                        @endif
                        @if (! empty($meta['transfer_reference']))
                            <div class="flex justify-between gap-4"><dt class="text-text-muted">Reference</dt><dd>{{ $meta['transfer_reference'] }}</dd></div>
                        @endif
                    </dl>
                @endif

                @if ($hasProof)
                    <x-dashboard.button :href="route('admin.orders.proof', $order)" variant="secondary" size="sm" target="_blank">View proof</x-dashboard.button>
                @endif
            @endif
        </x-dashboard.card>
    </div>
</x-layout.page>
@endsection
