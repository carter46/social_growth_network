@extends('layouts.dashboard-admin')

@section('title', 'Platform orders')

@section('content')
<x-layout.page
    title="Platform orders"
    subtitle="Manual bank transfer and other platform service purchases."
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Orders', null],
    ]"
>
    <x-slot:actions>
        <x-dashboard.button :href="route('admin.orders.create')" icon="orders" size="sm">Create order</x-dashboard.button>
        <x-dashboard.button :href="route('admin.orders', ['filter' => 'awaiting_bank'])" variant="secondary" size="sm">Awaiting bank</x-dashboard.button>
        <x-dashboard.button :href="route('admin.orders', ['filter' => 'failed_bank'])" variant="secondary" size="sm">Failed bank</x-dashboard.button>
        <x-dashboard.button :href="route('admin.orders')" variant="secondary" size="sm">All orders</x-dashboard.button>
    </x-slot:actions>

    <x-dashboard.table :empty="$orders->isEmpty()" empty-title="No orders" empty-description="Platform service orders appear here." empty-icon="orders" striped>
        <x-slot:head>
            <x-dashboard.th>User</x-dashboard.th>
            <x-dashboard.th>Payment</x-dashboard.th>
            <x-dashboard.th>Amount</x-dashboard.th>
            <x-dashboard.th>Status</x-dashboard.th>
            <x-dashboard.th></x-dashboard.th>
        </x-slot:head>
        @foreach ($orders as $order)
            <tr>
                <x-dashboard.td>
                    <span class="font-medium text-text-primary">{{ \App\Models\User::labelFor($order->user) }}</span>
                    <a href="{{ route('admin.orders.show', $order) }}" class="mt-0.5 block text-xs text-text-muted underline hover:text-primary">{{ $order->reference }}</a>
                </x-dashboard.td>
                <x-dashboard.td>{{ str_replace('_', ' ', $order->payment_method ?? '—') }}</x-dashboard.td>
                <x-dashboard.td>₦{{ number_format((float) $order->total_amount, 2) }}</x-dashboard.td>
                <x-dashboard.td>
                    <x-dashboard.badge :status="$order->status" />
                    @if($order->isAwaitingManualBankTransfer())
                        @if($order->payment_submitted_at)
                            <span class="mt-1 block text-xs font-medium text-amber-700">Proof submitted — review</span>
                        @else
                            <span class="mt-1 block text-xs font-medium text-amber-700">Awaiting bank transfer</span>
                        @endif
                    @elseif($order->payment_method === \App\Models\Order::PAYMENT_MANUAL_BANK_TRANSFER && $order->status === 'cancelled')
                        <span class="mt-1 block text-xs font-medium text-danger">Payment not completed</span>
                    @endif
                </x-dashboard.td>
                <x-dashboard.td>
                    <x-dashboard.button :href="route('admin.orders.show', $order)" variant="link" size="xs">View</x-dashboard.button>
                </x-dashboard.td>
            </tr>
        @endforeach
    </x-dashboard.table>

    <x-slot:pagination>
        <x-dashboard.pagination :paginator="$orders" />
    </x-slot:pagination>
</x-layout.page>
@endsection
