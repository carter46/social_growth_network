@extends('layouts.dashboard-user')

@section('title', $title ?? 'Orders')

@section('content')
@php
    $title = $title ?? 'My Orders';
    $breadcrumbParent = $breadcrumbParent ?? null;
    $emptyTitle = $emptyTitle ?? 'No orders yet';
    $emptyDescription = $emptyDescription ?? 'Your purchases will appear here.';
    $emptyAction = $emptyAction ?? ['href' => route('dashboard.services'), 'label' => 'Browse services'];
    $crumbs = [['Dashboard', route('dashboard')]];
    if ($breadcrumbParent) {
        $crumbs[] = $breadcrumbParent;
    }
    $crumbs[] = [$title, null];
@endphp
<x-layout.page
    :title="$title"
    width="full"
    :breadcrumb="$crumbs"
>
    <x-dashboard.table
        :empty="$orders->isEmpty()"
        :empty-title="$emptyTitle"
        :empty-description="$emptyDescription"
        empty-icon="orders"
        :empty-action="$emptyAction"
        striped
    >
        <x-slot:head>
            <x-dashboard.th>Reference</x-dashboard.th>
            <x-dashboard.th>Item</x-dashboard.th>
            <x-dashboard.th>Amount</x-dashboard.th>
            <x-dashboard.th>Status</x-dashboard.th>
            <x-dashboard.th>Date</x-dashboard.th>
            <x-dashboard.th>Actions</x-dashboard.th>
        </x-slot:head>
        @foreach ($orders as $order)
            @php
                $item = $order->items->first();
                $title = ($item?->options['product_title'] ?? null)
                    ?? ($item ? ucfirst(str_replace('_', ' ', $item->item_type)).' #'.$item->item_id : null)
                    ?? '—';
                $meta = [];
                if ($item && $item->quantity > 1) {
                    $meta[] = 'Qty '.$item->quantity;
                }
                if (! empty($item?->options['variant_label'])) {
                    $meta[] = $item->options['variant_label'];
                } elseif ($item?->variant) {
                    $meta[] = $item->variant->displayLabel();
                }
                if ($order->source === 'platform') {
                    $meta[] = 'Platform';
                }
                $domainRegistration = $order->domainRegistrations->first();
                if ($domainRegistration) {
                    $meta[] = 'Domain: '.$domainRegistration->fqdn.' ('.$domainRegistration->status.')';
                }
            @endphp
            <tr class="hover:bg-muted/50">
                <x-dashboard.td class="font-mono text-sm">{{ $order->reference }}</x-dashboard.td>
                <x-dashboard.td>
                    <div>{{ $title }}</div>
                    @if($meta)
                        <div class="text-xs text-text-muted mt-0.5">{{ implode(' · ', $meta) }}</div>
                    @endif
                </x-dashboard.td>
                <x-dashboard.td>₦{{ number_format($order->total_amount ?? $order->amount, 2) }}</x-dashboard.td>
                <x-dashboard.td>
                    <x-dashboard.badge :status="$order->status === 'cancelled' ? 'danger' : $order->status">
                        @if ($order->status === 'cancelled' && $order->payment_method === \App\Models\Order::PAYMENT_MANUAL_BANK_TRANSFER)
                            payment failed
                        @else
                            {{ $order->status }}
                        @endif
                    </x-dashboard.badge>
                    @if ($order->status === 'pending' && $order->payment_method === \App\Models\Order::PAYMENT_MANUAL_BANK_TRANSFER && $order->payment_submitted_at)
                        <div class="text-xs text-amber-700 mt-0.5">Under review</div>
                    @endif
                </x-dashboard.td>
                <x-dashboard.td class="text-text-secondary text-sm">{{ $order->created_at->format('M j, Y H:i') }}</x-dashboard.td>
                <x-dashboard.td>
                    @if ($order->source === 'platform' && $order->status === 'paid')
                        @if($domainRegistration ?? null)
                            <x-dashboard.button :href="route('dashboard.my-domains.show', $domainRegistration)" size="xs" variant="ghost">Manage domain</x-dashboard.button>
                        @else
                            <span class="text-text-muted text-xs">Paid</span>
                        @endif
                    @elseif ($order->source === 'platform' && $order->status === 'pending' && $order->payment_method === \App\Models\Order::PAYMENT_MANUAL_BANK_TRANSFER && ! $order->payment_submitted_at)
                        <x-dashboard.button :href="route('dashboard.orders.manual-payment', $order)" size="xs" variant="primary">Complete payment</x-dashboard.button>
                    @elseif ($order->source === 'platform' && $order->status === 'pending' && $order->payment_method === \App\Models\Order::PAYMENT_MANUAL_BANK_TRANSFER && $order->payment_submitted_at)
                        <span class="text-xs text-amber-700">Payment under review</span>
                    @elseif ($order->source === 'platform' && $order->status === 'cancelled' && $order->payment_method === \App\Models\Order::PAYMENT_MANUAL_BANK_TRANSFER)
                        <span class="text-xs text-danger">Payment not completed</span>
                    @endif
                </x-dashboard.td>
            </tr>
        @endforeach
    </x-dashboard.table>

    <x-slot:pagination>
        <x-dashboard.pagination :paginator="$orders" />
    </x-slot:pagination>
</x-layout.page>
@endsection
