@extends('layouts.dashboard-admin')

@section('title', 'Create platform order')

@section('content')
<x-layout.page
    title="Create order for user"
    subtitle="Manual bank transfer. Works even when the checkout toggle is off. Mark paid to create the campaign immediately."
    width="default"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Orders', route('admin.orders')],
        ['Create', null],
    ]"
>
    <x-dashboard.card class="space-y-4">
        @if(session('error'))
            <x-dashboard.alert type="danger">{{ session('error') }}</x-dashboard.alert>
        @endif

        <form method="POST" action="{{ route('admin.orders.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-text-secondary mb-1">User</label>
                <select name="user_id" required class="w-full rounded-lg border border-border-subtle bg-surface px-3 py-2 text-sm">
                    <option value="">Select user…</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected((int) old('user_id') === (int) $u->id)>{{ $u->name }} ({{ $u->email }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-text-secondary mb-1">Product</label>
                <select name="product_slug" required class="w-full rounded-lg border border-border-subtle bg-surface px-3 py-2 text-sm">
                    <option value="">Select product…</option>
                    @foreach ($products as $p)
                        <option value="{{ $p->slug }}" @selected(old('product_slug') === $p->slug)>{{ $p->title }} ({{ $p->slug }})</option>
                    @endforeach
                </select>
            </div>
            <x-dashboard.input name="quantity" type="number" label="Quantity (between the product's minimum and maximum purchase)" :value="old('quantity')" min="1" max="1000000" required />
            <x-dashboard.input name="target_url" type="url" label="Campaign link (post, video or profile URL)" :value="old('target_url')" placeholder="https://" required />
            <input type="hidden" name="mark_paid" value="0">
            <x-dashboard.toggle name="mark_paid" label="Mark paid immediately (fulfill order)" :checked="old('mark_paid')" value="1" />
            <x-dashboard.button type="submit" variant="primary">Create order</x-dashboard.button>
        </form>
    </x-dashboard.card>
</x-layout.page>
@endsection
