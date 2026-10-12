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

        @php
            $productBriefs = $products->mapWithKeys(fn ($p) => [$p->slug => [
                'tasks' => $p->taskProfile()->sequenceLabel(),
                'channel' => $p->taskProfile()->needsChannelUrl(),
                'comment' => $p->taskProfile()->needsCommentInstructions(),
            ]]);
        @endphp
        <form
            method="POST"
            action="{{ route('admin.orders.store') }}"
            class="space-y-4"
            x-data="{ slug: @js((string) old('product_slug', '')), briefs: @js($productBriefs), companions: @js($companions ?? []), oldQty: @js((array) old('qty', [])), get brief() { return this.briefs[this.slug] || null }, get rows() { return this.companions[this.slug] || [] } }"
        >
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
                <select name="product_slug" x-model="slug" required class="w-full rounded-lg border border-border-subtle bg-surface px-3 py-2 text-sm">
                    <option value="">Select product…</option>
                    @foreach ($products as $p)
                        <option value="{{ $p->slug }}" @selected(old('product_slug') === $p->slug)>{{ $p->title }} ({{ $p->slug }})</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-text-muted" x-show="brief && (brief.channel || brief.comment)" x-cloak>
                    Agents do: <span x-text="brief?.tasks"></span>. Each unit is one new agent completing all tasks.
                </p>
            </div>
            <x-dashboard.input name="quantity" type="number" label="Quantity (between the product's minimum and maximum purchase)" :value="old('quantity')" min="1" max="1000000" required />
            <template x-for="row in rows" :key="slug + row.task">
                <div>
                    <label class="block text-sm font-medium text-text-secondary mb-1" :for="'order-qty-' + row.task" x-text="row.label"></label>
                    <input type="number" :id="'order-qty-' + row.task" :name="'qty[' + row.task + ']'" :min="row.min" :max="row.max" :value="oldQty[row.task] ?? row.min" required class="w-full rounded-lg border border-border-subtle bg-surface px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-text-muted">Between <span x-text="Number(row.min).toLocaleString()"></span> and <span x-text="Number(row.max).toLocaleString()"></span>. Units above the minimum are billed at the unit price.</p>
                </div>
            </template>
            <x-dashboard.input name="target_url" type="url" label="Campaign link (video URL, or channel URL for channel-only products)" :value="old('target_url')" placeholder="https://" required />
            <div x-show="brief?.channel" x-cloak>
                <label for="order-channel-url" class="block text-sm font-medium text-text-secondary mb-1">Channel URL</label>
                <input id="order-channel-url" type="url" name="channel_url" value="{{ old('channel_url') }}" placeholder="https://www.youtube.com/@channel" :required="!!brief?.channel" class="w-full rounded-lg border border-border-subtle bg-surface px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-text-muted">The channel agents subscribe to. The video must be on this channel.</p>
            </div>
            <div x-show="brief?.comment" x-cloak>
                <label for="order-comment-instructions" class="block text-sm font-medium text-text-secondary mb-1">Comment instructions</label>
                <textarea id="order-comment-instructions" name="comment_instructions" rows="3" maxlength="1000" :required="!!brief?.comment" class="w-full rounded-lg border border-border-subtle bg-surface px-3 py-2 text-sm">{{ old('comment_instructions') }}</textarea>
                <p class="mt-1 text-xs text-text-muted">What agents should comment about (up to 1,000 characters).</p>
            </div>
            <input type="hidden" name="mark_paid" value="0">
            <x-dashboard.toggle name="mark_paid" label="Mark paid immediately (fulfill order)" :checked="old('mark_paid')" value="1" />
            <x-dashboard.button type="submit" variant="primary">Create order</x-dashboard.button>
        </form>
    </x-dashboard.card>
</x-layout.page>
@endsection
