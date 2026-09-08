@extends($layout ?? 'layouts.dashboard-user')

@section('title', 'My Bank')

@section('content')
@php $prefix = $prefix ?? 'dashboard'; @endphp
<x-layout.page
    title="My Bank"
    width="full"
    :breadcrumb="[
        [$prefix === 'agent' ? 'Agent' : 'Dashboard', route($prefix === 'agent' ? 'agent' : 'dashboard')],
        ['My Bank', null],
    ]"
>
    <x-dashboard.card>
        @if ($bank)
            <div class="space-y-2 text-sm">
                <p class="text-text-muted">Current bank</p>
                <p class="text-lg font-semibold text-text-primary">{{ $bank->bank_name }}</p>
                <p>{{ $bank->maskedAccountNumber() }}</p>
                <p class="text-text-secondary">{{ $bank->verified_name }}</p>
                <p class="text-xs text-text-muted">Verified {{ $bank->verified_at?->toDayDateTimeString() }} via {{ $bank->verified_by }}</p>
            </div>
            @if ($canReplace)
                <div class="mt-6">
                    <x-dashboard.button :href="route($prefix.'.banks.replace')" variant="secondary">Replace Bank Account</x-dashboard.button>
                </div>
            @else
                <x-dashboard.alert type="warning" class="mt-6">
                    You cannot replace your bank while a withdrawal request is pending or being processed.
                </x-dashboard.alert>
            @endif
        @else
            <p class="text-sm text-text-secondary mb-4">No bank account on file.</p>
            @if (! $monnifyReady)
                <x-dashboard.alert type="warning" class="mb-4">Bank verification is not available yet. Try again later.</x-dashboard.alert>
            @endif
            <x-dashboard.button :href="route($prefix.'.banks.replace')" icon="withdraw">Add Bank Account</x-dashboard.button>
        @endif
    </x-dashboard.card>
</x-layout.page>
@endsection
