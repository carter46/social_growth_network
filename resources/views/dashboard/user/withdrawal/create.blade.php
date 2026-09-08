@extends($layout ?? 'layouts.dashboard-user')

@section('title', 'Withdraw')

@section('content')
@php $prefix = $prefix ?? 'dashboard'; @endphp
@php $step = session('withdrawal_step', old('_step', 'confirm')); @endphp
<x-layout.page
    title="Withdraw to Bank"
    width="full"
    :breadcrumb="[
        [$prefix === 'agent' ? 'Agent' : 'Dashboard', route($prefix === 'agent' ? 'agent' : 'dashboard')],
        ['Withdraw', route($prefix.'.withdrawal.index')],
        ['Request', null],
    ]"
>
    <x-dashboard.card>
        <div class="mb-4 rounded-xl border border-border-subtle p-4 text-sm">
            <p class="font-medium text-text-primary">Payout bank</p>
            <p class="mt-1 text-text-secondary">{{ $bank->bank_name }} · {{ $bank->maskedAccountNumber() }}</p>
            <p class="text-text-muted">{{ $bank->verified_name }}</p>
            <a href="{{ route($prefix.'.banks.index') }}" class="mt-2 inline-block text-sm underline">Manage bank</a>
        </div>
        <p class="mb-4 text-sm text-text-secondary">Available: ₦{{ number_format($wallet->availableBalance(), 2) }}</p>

        @if ($step === 'confirm')
            <form method="POST" action="{{ route($prefix.'.withdrawal.otp') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="user_bank_account_id" value="{{ $bank->id }}">
                <x-dashboard.input label="Amount (NGN)" type="number" name="amount" min="100" step="0.01" :value="old('amount')" required />
                <x-dashboard.input type="password" name="password" label="Confirm your password" required autocomplete="current-password" />
                <x-dashboard.button type="submit" icon="withdraw">Send email code</x-dashboard.button>
            </form>
        @elseif ($step === 'otp')
            <form method="POST" action="{{ route($prefix.'.withdrawal.verify-otp') }}" class="space-y-4">
                @csrf
                <x-dashboard.input name="otp" label="6-digit verification code" maxlength="6" required />
                <x-dashboard.button type="submit" icon="withdraw">Verify and submit withdrawal</x-dashboard.button>
            </form>
            <p class="mt-4 text-xs text-text-muted">Check your email for the code. To change the amount, <a href="{{ route($prefix.'.withdrawal.create') }}" class="underline">start again</a>.</p>
        @else
            <p class="text-sm text-text-secondary">Start again from the beginning.</p>
            <x-dashboard.button :href="route($prefix.'.withdrawal.create')" class="mt-4">Restart</x-dashboard.button>
        @endif
    </x-dashboard.card>
</x-layout.page>
@endsection
