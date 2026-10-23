@extends($layout ?? 'layouts.dashboard-user')

@section('title', 'Withdraw')

@section('content')
@php $prefix = $prefix ?? 'dashboard'; @endphp
@php $step = session('withdrawal_step', old('_step', 'confirm')); @endphp
@php $maxWithdrawable = min((float) $withdrawalMax, (float) $wallet->withdrawableBalance()); @endphp
@php
    $otpReceive = null;
    if ($breakdown ?? null) {
        $q = (array) ($breakdown['quote'] ?? []);
        $destAmount = isset($q['destination_amount']) ? (string) $q['destination_amount'] : null;
        $destCurrency = isset($q['destination_currency']) ? strtoupper((string) $q['destination_currency']) : null;
        $site = site_currency()->code;
        if ($destAmount !== null && $destCurrency !== null && $destCurrency !== $site) {
            $symbol = (string) (config('platform.currencies.'.$destCurrency.'.symbol') ?: $destCurrency.' ');
            $otpReceive = $symbol.number_format((float) $destAmount, 2, '.', ',');
        } else {
            $otpReceive = money($breakdown['net'], 2);
        }
        $otpFee = money(\App\Support\Money\Money::add($breakdown['provider_fee'], $breakdown['platform_fee'], 2), 2);
    }
@endphp
<x-layout.page
    title="Withdraw"
    width="full"
    :breadcrumb="[
        [$prefix === 'agent' ? 'Agent' : 'Dashboard', route($prefix === 'agent' ? 'agent' : 'dashboard')],
        ['Withdraw', route($prefix.'.withdrawal.index')],
        ['Request', null],
    ]"
>
    @if (session('status'))
        <x-dashboard.alert type="success" class="mb-4">{{ session('status') }}</x-dashboard.alert>
    @endif
    @if ($errors->any())
        <x-dashboard.alert type="danger" class="mb-4">{{ $errors->first() }}</x-dashboard.alert>
    @endif

    @if ($blocker ?? null)
        <x-dashboard.card>
            <div class="flex flex-col items-center px-4 py-8 text-center">
                <div class="mb-4 flex size-14 items-center justify-center rounded-2xl bg-warning/10 text-warning">
                    <x-ui.icon name="withdraw" class="h-7 w-7" />
                </div>
                <h2 class="text-lg font-semibold text-text-primary">{{ $blocker['title'] }}</h2>
                <p class="mt-2 max-w-md text-sm text-text-secondary">{{ $blocker['message'] }}</p>
                <div class="mt-4 rounded-xl bg-muted/40 px-4 py-3 text-sm">
                    <span class="text-text-muted">Available balance:</span>
                    <span class="font-semibold text-text-primary">{{ money($wallet?->availableBalance() ?? 0) }}</span>
                </div>
                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    @if ($blocker['action'])
                        <x-dashboard.button :href="$blocker['action']['href']">{{ $blocker['action']['label'] }}</x-dashboard.button>
                    @endif
                    <x-dashboard.button :href="route($prefix.'.withdrawal.index')" variant="secondary">Back to withdrawals</x-dashboard.button>
                </div>
            </div>
        </x-dashboard.card>
    @else
    <x-dashboard.card>
        <div class="mb-4 rounded-xl border border-border-subtle p-4 text-sm">
            <p class="font-medium text-text-primary">{{ $account->summary() }}</p>
            <p class="text-text-muted">{{ $account->account_holder_name }}</p>
            <a href="{{ route($prefix.'.banks.index') }}" class="mt-2 inline-block text-sm underline">Change account</a>
        </div>
        <p class="mb-4 text-sm text-text-secondary">
            Available: {{ money($wallet->withdrawableBalance(), 2, 2) }}
        </p>

        @if ($step === 'confirm')
            <form
                method="POST"
                action="{{ route($prefix.'.withdrawal.otp') }}"
                class="space-y-4"
                x-data="withdrawalQuote(@js(route($prefix.'.withdrawal.quote')), @js(old('amount', '')), @js($maxWithdrawable), @js(money($maxWithdrawable, 2, 2)))"
            >
                @csrf
                <input type="hidden" name="payout_account_id" value="{{ $account->id }}">
                <x-dashboard.input
                    :label="'Amount ('.site_currency()->code.')'"
                    type="number"
                    name="amount"
                    :min="$withdrawalMin"
                    :max="$maxWithdrawable"
                    step="0.01"
                    x-model="amount"
                    x-on:input.debounce.400ms="load()"
                    :hint="'Min '.money($withdrawalMin, 2).' · Max '.money($maxWithdrawable, 2, 2)"
                    required
                />

                <div class="rounded-xl border border-border-subtle p-4 text-sm space-y-1" x-show="amount" x-cloak>
                    <p class="text-xs text-text-muted" x-show="loading">Calculating…</p>
                    <p class="text-danger" x-show="error" x-text="error"></p>
                    <template x-if="quote && !error">
                        <div class="space-y-1">
                            <p class="flex justify-between gap-4"><span>You withdraw</span><strong x-text="quote.amount"></strong></p>
                            <p class="flex justify-between gap-4"><span>Fee</span><span x-text="quote.fee"></span></p>
                            <p class="flex justify-between gap-4 border-t border-border-subtle pt-1"><span>You'll receive</span><strong x-text="quote.receive"></strong></p>
                        </div>
                    </template>
                </div>

                <x-dashboard.input type="password" name="password" label="Password" required autocomplete="current-password" />
                <x-dashboard.button type="submit" icon="withdraw">Withdraw</x-dashboard.button>
            </form>
        @elseif ($step === 'otp')
            @if ($breakdown)
                <div class="mb-4 rounded-xl border border-border-subtle p-4 text-sm space-y-1">
                    <p class="flex justify-between gap-4"><span>You withdraw</span><strong>{{ money($breakdown['amount'], 2) }}</strong></p>
                    <p class="flex justify-between gap-4"><span>Fee</span><span>{{ $otpFee }}</span></p>
                    <p class="flex justify-between gap-4 border-t border-border-subtle pt-1"><span>You'll receive</span><strong>{{ $otpReceive }}</strong></p>
                </div>
            @endif
            <form method="POST" action="{{ route($prefix.'.withdrawal.verify-otp') }}" class="space-y-4">
                @csrf
                <x-dashboard.input name="otp" label="Email code" maxlength="6" required autocomplete="one-time-code" />
                <x-dashboard.button type="submit" icon="withdraw">Confirm withdrawal</x-dashboard.button>
            </form>
            <p class="mt-4 text-xs text-text-muted"><a href="{{ route($prefix.'.withdrawal.create') }}" class="underline">Change amount</a></p>
        @else
            <p class="text-sm text-text-secondary">Start again.</p>
            <x-dashboard.button :href="route($prefix.'.withdrawal.create')" class="mt-4">Restart</x-dashboard.button>
        @endif
    </x-dashboard.card>
    @endif
</x-layout.page>

<script>
    function withdrawalQuote(url, initial, maxWithdrawable, maxLabel) {
        return {
            amount: initial || '',
            quote: null,
            error: null,
            loading: false,
            maxWithdrawable: Number(maxWithdrawable),
            maxLabel: maxLabel,
            init() {
                if (this.amount) this.load();
            },
            async load() {
                this.error = null;
                const value = Number(this.amount);
                if (!this.amount || value <= 0) {
                    this.quote = null;
                    return;
                }
                if (value > this.maxWithdrawable) {
                    this.quote = null;
                    this.error = 'You can withdraw up to ' + this.maxLabel + '.';
                    return;
                }
                this.loading = true;
                try {
                    const res = await fetch(url + '?amount=' + encodeURIComponent(this.amount), { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    if (!res.ok) {
                        this.quote = null;
                        this.error = data.message || 'Could not calculate this amount.';
                        return;
                    }
                    this.quote = data;
                } catch (e) {
                    this.quote = null;
                    this.error = 'Could not calculate this amount. Try again.';
                } finally {
                    this.loading = false;
                }
            },
        };
    }
</script>
@endsection
