@extends('layouts.dashboard-agent')

@section('title', 'Wallet')

@section('content')
@php
    $prefix = $prefix ?? 'agent';
    $profileReady = auth()->user()?->hasCompletePayoutProfile() ?? false;
    $connection = $payoutConnection ?? null;
    $connectStatus = $connection?->status ?? \App\Models\PayoutConnection::NOT_STARTED;
    $bankBadge = match (true) {
        ! ($connectAvailable ?? false) => null,
        $connectStatus === \App\Models\PayoutConnection::ACTIVE => ['status' => 'success', 'label' => 'Ready'],
        $connectStatus === \App\Models\PayoutConnection::UNDER_REVIEW => ['status' => 'pending', 'label' => 'In review'],
        $connectStatus === \App\Models\PayoutConnection::RESTRICTED => ['status' => 'danger', 'label' => 'Needs info'],
        $connectStatus === \App\Models\PayoutConnection::REJECTED => ['status' => 'danger', 'label' => 'Rejected'],
        $connection && filled($connection->account_id) => ['status' => 'warning', 'label' => 'Setup incomplete'],
        default => ['status' => 'warning', 'label' => 'Not connected'],
    };
@endphp
<x-layout.page
    title="Wallet"
    width="full"
    :breadcrumb="[
        ['Agent', route('agent')],
        ['Wallet', null],
    ]"
>
    @if (! $wallet)
        <x-dashboard.card>
            @if (! ($canCreateWallet ?? false))
                <x-dashboard.alert type="warning" title="KYC required">
                    Complete <a href="{{ route($prefix.'.account.kyc') }}" class="underline font-medium">KYC Level 1</a> then create your wallet to withdraw earnings.
                </x-dashboard.alert>
            @else
                @if (! ($kycRequired ?? true))
                    <x-dashboard.alert type="info" class="mb-4">
                        KYC is optional right now. Create a wallet to withdraw earnings.
                    </x-dashboard.alert>
                @endif
                <form method="POST" action="{{ route($prefix.'.wallet.create') }}" x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf
                    <x-dashboard.button type="submit" icon="wallet" x-bind:disabled="submitting">Create Wallet</x-dashboard.button>
                </form>
            @endif
        </x-dashboard.card>
    @else
        <x-dashboard.stat-grid :count="4">
            <x-dashboard.stats-card
                :label="'Balance ('.site_currency()->code.')'"
                :value="money($wallet->balance, 2)"
                icon="wallet"
            >
                <div class="mt-4 flex flex-col gap-2">
                    <x-dashboard.button :href="route($prefix.'.withdrawal.create')" variant="secondary" size="sm" icon="withdraw">Withdraw to Bank</x-dashboard.button>
                </div>
            </x-dashboard.stats-card>
            <x-dashboard.stats-card
                label="Locked (Held)"
                :value="money($wallet->locked_balance, 2)"
                icon="lock"
            />
            <x-dashboard.stats-card
                label="Available"
                :value="money($wallet->availableBalance(), 2)"
                icon="wallet"
            />
            <x-dashboard.card class="flex flex-col justify-center gap-3 min-h-[120px]">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-medium text-text-muted">My Bank</p>
                    @if ($bankBadge)
                        <x-dashboard.badge :status="$bankBadge['status']">{{ $bankBadge['label'] }}</x-dashboard.badge>
                    @endif
                </div>
                <x-dashboard.button :href="route($prefix.'.banks.index')" variant="secondary" size="sm" icon="withdraw">Open My Bank</x-dashboard.button>
                <x-dashboard.button :href="route($prefix.'.history')" variant="ghost" size="sm" icon="history">Transaction History</x-dashboard.button>
            </x-dashboard.card>
        </x-dashboard.stat-grid>

        @if (! $profileReady)
            <x-dashboard.alert type="warning" class="mt-4">
                <p class="font-semibold">Update your payout profile</p>
                <p class="mt-1">Add your phone (with country code), country, and address before you can connect a bank account.</p>
                <form method="POST" action="{{ route('agent.payout-profile.update') }}" class="mt-3 grid gap-2 sm:grid-cols-2">
                    @csrf
                    @php
                        $payoutCountries = collect((array) config('payout_methods.countries', []))
                            ->map(fn ($row, $code) => ['code' => $code, 'name' => (string) ($row['name'] ?? $code)])
                            ->sortBy('name')->values();
                        $u = auth()->user();
                    @endphp
                    <div class="sm:col-span-2">
                        <x-forms.country-phone
                            :countries="$payoutCountries"
                            :country="old('country', $u->country)"
                            :phone="old('phone', $u->phone)"
                            country-id="wallet-country"
                            phone-id="wallet-phone"
                        />
                    </div>
                    <x-dashboard.input name="address_line1" label="Address" :value="old('address_line1', $u->address_line1)" required />
                    <x-dashboard.input name="address_city" label="City" :value="old('address_city', $u->address_city)" required />
                    <x-dashboard.input name="address_state" label="State / region" :value="old('address_state', $u->address_state)" required />
                    <x-dashboard.input name="address_postal_code" label="Postal code (optional)" :value="old('address_postal_code', $u->address_postal_code)" />
                    <div class="sm:col-span-2">
                        <x-dashboard.button type="submit" size="sm">Save profile</x-dashboard.button>
                    </div>
                </form>
            </x-dashboard.alert>
        @endif

        <x-dashboard.table
            class="mt-4 text-xs sm:text-sm"
            :empty="$transactions->isEmpty()"
            empty-title="No transactions yet"
            empty-description="Task rewards and withdrawals will appear here."
            empty-icon="transactions"
            striped
        >
            <x-slot:head>
                <x-dashboard.th class="text-xs sm:text-sm">Type</x-dashboard.th>
                <x-dashboard.th class="text-xs sm:text-sm">Amount ({{ site_currency()->code }})</x-dashboard.th>
                <x-dashboard.th class="text-xs sm:text-sm">Status</x-dashboard.th>
            </x-slot:head>
            @foreach ($transactions as $tx)
                <tr class="hover:bg-muted/50">
                    <x-dashboard.td class="align-top">
                        <p class="text-xs font-medium text-text-primary sm:text-sm">{{ \App\Enums\TransactionType::labelFor($tx->type) }}</p>
                        <p class="mt-0.5 break-all font-mono text-[10px] leading-snug text-text-muted sm:text-xs">{{ $tx->reference }}</p>
                    </x-dashboard.td>
                    <x-dashboard.td class="align-top text-xs whitespace-nowrap sm:text-sm">{{ money($tx->amount, 2) }}</x-dashboard.td>
                    <x-dashboard.td class="align-top"><x-dashboard.badge :status="$tx->status" /></x-dashboard.td>
                </tr>
            @endforeach
        </x-dashboard.table>
    @endif
</x-layout.page>
@endsection
