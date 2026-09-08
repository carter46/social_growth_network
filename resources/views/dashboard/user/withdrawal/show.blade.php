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

    <div class="grid gap-6 lg:grid-cols-2">
        <x-dashboard.card>
            <dl class="space-y-2 text-sm">
                <div class="flex flex-col gap-1 sm:flex-row sm:justify-between sm:gap-3">
                    <dt class="shrink-0 text-text-muted">Reference</dt>
                    <dd class="font-mono text-xs break-all sm:text-right sm:text-sm">{{ $withdrawal->reference }}</dd>
                </div>
                <div class="flex justify-between"><dt class="text-text-muted">Amount</dt><dd class="font-medium">₦{{ number_format($withdrawal->amount, 2) }}</dd></div>
                <div class="flex justify-between"><dt class="text-text-muted">Bank</dt><dd>{{ $withdrawal->bank_name }}</dd></div>
                <div class="flex justify-between"><dt class="text-text-muted">Account</dt><dd>{{ $withdrawal->maskedAccountNumber() }}</dd></div>
                <div class="flex justify-between"><dt class="text-text-muted">Name</dt><dd>{{ $withdrawal->account_name }}</dd></div>
            </dl>
            @if ($withdrawal->isOpen())
                <p class="mt-4 text-sm text-text-secondary">This withdrawal is in progress. You cannot cancel or change the bank until it finishes.</p>
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
