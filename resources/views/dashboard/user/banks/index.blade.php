@extends($layout ?? 'layouts.dashboard-user')

@section('title', 'Payout accounts')

@section('content')
@php $prefix = $prefix ?? 'dashboard'; @endphp
<x-layout.page
    title="Payout accounts"
    subtitle="Withdrawals are sent to your primary account. Accounts must be in your profile name."
    width="full"
    :breadcrumb="[
        [$prefix === 'agent' ? 'Agent' : 'Dashboard', route($prefix === 'agent' ? 'agent' : 'dashboard')],
        ['Payout accounts', null],
    ]"
>
    @if (session('status'))
        <x-dashboard.alert type="success" class="mb-4">{{ session('status') }}</x-dashboard.alert>
    @endif
    @if ($errors->any())
        <x-dashboard.alert type="danger" class="mb-4">{{ $errors->first() }}</x-dashboard.alert>
    @endif

    @if ($hasOpenWithdrawal)
        <x-dashboard.alert type="warning" class="mb-4">
            You can't change your primary account or remove an account while a withdrawal is pending or being processed.
        </x-dashboard.alert>
    @endif

    <div class="space-y-4">
        @forelse ($accounts as $account)
            <x-dashboard.card>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="space-y-1 text-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-base font-semibold text-text-primary">{{ $account->bank_name ?: $account->methodLabel() }}</p>
                            @if ($account->is_primary)
                                <x-dashboard.badge status="active">Primary</x-dashboard.badge>
                            @endif
                            @if ($account->isRejected())
                                <x-dashboard.badge status="rejected">Rejected</x-dashboard.badge>
                            @elseif ($account->isResolved())
                                <x-dashboard.badge status="completed">Verified</x-dashboard.badge>
                            @else
                                <x-dashboard.badge status="pending">Checked on first payout</x-dashboard.badge>
                            @endif
                        </div>
                        <p class="font-mono">{{ $account->display_mask }}</p>
                        <p class="text-text-secondary">{{ $account->account_holder_name }}</p>
                        <p class="text-xs text-text-muted">{{ $account->countryName() }} · {{ $account->methodLabel() }} · paid out in {{ $account->currency }}</p>
                        @if ($account->isRejected() && $account->rejected_reason)
                            <p class="text-xs text-danger">{{ $account->rejected_reason }}</p>
                        @elseif (! $account->routeAvailable())
                            <p class="text-xs text-warning">Payouts to this method are paused. Choose another primary account.</p>
                        @endif
                    </div>

                    @unless ($hasOpenWithdrawal)
                        <div class="flex flex-wrap gap-2">
                            @if ($verified)
                                @if (! $account->is_primary && ! $account->isRejected())
                                    <form method="POST" action="{{ route($prefix.'.banks.primary', $account) }}">
                                        @csrf
                                        <x-dashboard.button type="submit" size="sm" variant="secondary">Set as primary</x-dashboard.button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route($prefix.'.banks.remove', $account) }}" onsubmit="return confirm('Remove this payout account?');">
                                    @csrf
                                    @method('DELETE')
                                    <x-dashboard.button type="submit" size="sm" variant="danger">Remove</x-dashboard.button>
                                </form>
                            @else
                                <x-dashboard.button :href="route($prefix.'.banks.replace', ['intent' => 'manage'])" size="sm" variant="secondary">Manage</x-dashboard.button>
                            @endif
                        </div>
                    @endunless
                </div>
            </x-dashboard.card>
        @empty
            <x-dashboard.card>
                <p class="text-sm text-text-secondary">No payout account on file. Add one to request withdrawals.</p>
            </x-dashboard.card>
        @endforelse
    </div>

    <div class="mt-6">
        @if ($accounts->count() >= $maxAccounts)
            <p class="text-sm text-text-muted">You have {{ $maxAccounts }} payout accounts, the most allowed. Remove one to add another.</p>
        @elseif (! $hasRoutes)
            <x-dashboard.alert type="warning">Payout accounts can't be added right now. Try again later.</x-dashboard.alert>
        @else
            <x-dashboard.button :href="route($prefix.'.banks.replace')" icon="withdraw">Add payout account</x-dashboard.button>
        @endif
    </div>
</x-layout.page>
@endsection
