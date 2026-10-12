@extends('layouts.dashboard-user')

@section('title', 'Deposit')

@section('content')
<x-layout.page
    title="Deposit"
    width="full"
    :breadcrumb="[
        ['Dashboard', route('dashboard')],
        ['Deposit', route('dashboard.deposit.index')],
        ['Receipt', null],
    ]"
>
    <p class="-mt-2 font-mono text-xs leading-relaxed text-text-muted break-all sm:text-sm">
        {{ $funding->reference }}
    </p>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-dashboard.card>
            <dl class="space-y-2 text-sm">
                <div class="flex flex-col gap-1 sm:flex-row sm:justify-between sm:gap-3">
                    <dt class="shrink-0 text-text-muted">Reference</dt>
                    <dd class="font-mono text-xs break-all sm:text-right sm:text-sm">{{ $funding->reference }}</dd>
                </div>
                <div class="flex justify-between gap-3"><dt class="text-text-muted">Amount</dt><dd class="font-medium">{{ money($funding->amount, 2) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-text-muted">Method</dt><dd class="break-words text-right">{{ $funding->method }}</dd></div>
                @if ($funding->checkout_url && $funding->status !== 'approved' && ! $funding->isCheckoutExpired())
                    <div class="pt-2">
                        <x-dashboard.button :href="$funding->checkout_url" variant="secondary">Continue payment</x-dashboard.button>
                    </div>
                @endif
            </dl>
        </x-dashboard.card>
        <x-dashboard.card>
            <h3 class="font-semibold text-text-primary mb-3">Timeline</h3>
            <ol class="space-y-3 border-l border-border-subtle pl-4">
                @forelse ($funding->timelineEvents as $event)
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
