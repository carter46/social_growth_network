@extends($layout ?? 'layouts.dashboard-user')

@section('title', $prefix === 'agent' ? 'My Bank' : 'Payout accounts')

@section('content')
@php
    $prefix = $prefix ?? 'dashboard';
    $isAgent = $prefix === 'agent';
    $connection = $payoutConnection ?? null;
    $connectStatus = $connection?->status ?? \App\Models\PayoutConnection::NOT_STARTED;
    $connectActive = $connectStatus === \App\Models\PayoutConnection::ACTIVE;
    $pageTitle = $isAgent ? 'My Bank' : 'Payout accounts';
@endphp
<x-layout.page
    :title="$pageTitle"
    subtitle="Withdrawals go to your primary account. Accounts must be in your profile name."
    width="full"
    :breadcrumb="[
        [$isAgent ? 'Agent' : 'Dashboard', route($isAgent ? 'agent' : 'dashboard')],
        [$pageTitle, null],
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

    @if ($connectAvailable ?? false)
        @php
            $badgeStatus = match ($connectStatus) {
                \App\Models\PayoutConnection::ACTIVE => 'success',
                \App\Models\PayoutConnection::UNDER_REVIEW => 'pending',
                \App\Models\PayoutConnection::RESTRICTED, \App\Models\PayoutConnection::REJECTED => 'danger',
                default => 'warning',
            };
        @endphp
        <x-dashboard.card class="mb-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-sm font-semibold text-text-primary">Payout setup</h2>
                        <x-dashboard.badge :status="$badgeStatus">{{ $connection?->label() ?? \App\Models\PayoutConnection::STATUS_LABELS[\App\Models\PayoutConnection::NOT_STARTED] }}</x-dashboard.badge>
                    </div>
                    <p class="mt-1 text-sm text-text-secondary">
                        @switch($connectStatus)
                            @case(\App\Models\PayoutConnection::ACTIVE)
                                Your payout identity is verified. Manage the bank accounts below for withdrawals.
                                @break
                            @case(\App\Models\PayoutConnection::UNDER_REVIEW)
                                Your details are under review. Withdrawals open once that finishes.
                                @break
                            @case(\App\Models\PayoutConnection::RESTRICTED)
                                More information is needed before payouts can resume.
                                @break
                            @case(\App\Models\PayoutConnection::REJECTED)
                                This payout setup could not be approved. Contact support for help.
                                @break
                            @default
                                Connect a bank account to withdraw your earnings.
                        @endswitch
                    </p>
                </div>
                @if ($connectActive)
                    @if ($accounts->count() < $maxAccounts && ($hasRoutes || $connectActive))
                        <x-dashboard.button :href="route($prefix.'.banks.replace')" size="sm" icon="withdraw">
                            {{ $accounts->isEmpty() ? 'Add bank account' : 'Add another account' }}
                        </x-dashboard.button>
                    @endif
                @elseif ($connectStatus === \App\Models\PayoutConnection::RESTRICTED)
                    <x-dashboard.button :href="route('agent.payouts.connect')" size="sm" variant="secondary" icon="withdraw">
                        Provide more information
                    </x-dashboard.button>
                @elseif ($connectStatus !== \App\Models\PayoutConnection::REJECTED && $connectStatus !== \App\Models\PayoutConnection::UNDER_REVIEW)
                    <x-dashboard.button :href="route('agent.payouts.connect')" size="sm" icon="withdraw">
                        @if ($connection && filled($connection->account_id))
                            Continue setup
                        @else
                            Connect bank account
                        @endif
                    </x-dashboard.button>
                @endif
            </div>
        </x-dashboard.card>
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
                        <p class="text-xs text-text-muted">{{ $account->countryName() }} · {{ $account->methodLabel() }} · paid in {{ $account->currency }}</p>
                        @if ($account->isRejected() && $account->rejected_reason)
                            <p class="text-xs text-danger">{{ $account->rejected_reason }}</p>
                        @elseif (! $account->routeAvailable() && ! $connectActive)
                            <p class="text-xs text-warning">
                                This payout method isn’t available right now.
                                @if ($accounts->count() > 1 && $account->is_primary)
                                    If you have another account, set that one as primary.
                                @else
                                    Try again later or contact support.
                                @endif
                            </p>
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
                @if ($connectAvailable ?? false)
                    @if ($connectActive)
                        <p class="text-sm text-text-secondary">Your payout identity is ready. Add a bank account below to receive withdrawals. Account numbers show here after you save one on this site.</p>
                    @else
                        <p class="text-sm text-text-secondary">No bank account on file yet. Connect a bank account to request withdrawals.</p>
                    @endif
                @else
                    <p class="text-sm text-text-secondary">No payout account on file. Add one to request withdrawals.</p>
                @endif
            </x-dashboard.card>
        @endforelse
    </div>

    <div class="mt-6">
        @if ($accounts->count() >= $maxAccounts)
            <p class="text-sm text-text-muted">You have {{ $maxAccounts }} payout accounts, the most allowed. Remove one to add another.</p>
        @elseif ($connectAvailable ?? false)
            @if ($connectActive)
                {{-- Add bank account CTA is in the payout setup card above --}}
            @elseif ($accounts->isEmpty() && ! ($connection && filled($connection->account_id)))
                {{-- Connect CTA is in the payout setup card above --}}
            @elseif ($hasRoutes)
                <x-dashboard.button :href="route($prefix.'.banks.replace')" variant="secondary" icon="withdraw">
                    {{ $accounts->isEmpty() ? 'Add bank account' : 'Add another account' }}
                </x-dashboard.button>
            @endif
        @elseif (! $hasRoutes)
            <x-dashboard.alert type="warning">Payout accounts can't be added right now. Try again later.</x-dashboard.alert>
        @else
            <x-dashboard.button :href="route($prefix.'.banks.replace')" icon="withdraw">Add payout account</x-dashboard.button>
        @endif
    </div>
</x-layout.page>
@endsection
