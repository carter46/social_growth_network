@extends($layout ?? 'layouts.dashboard-user')

@section('title', 'Withdraw')

@section('content')
@php $prefix = $prefix ?? 'dashboard'; @endphp
@php $step = session('withdrawal_step', old('_step', 'confirm')); @endphp
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
            <p class="font-medium text-text-primary">Sent to your primary payout account</p>
            <p class="mt-1 text-text-secondary">{{ $account->summary() }}</p>
            <p class="text-text-muted">{{ $account->account_holder_name }}</p>
            @unless ($account->isResolved())
                <p class="mt-1 text-xs text-text-muted">This account couldn't be checked automatically, so the first payout is reviewed by our team.</p>
            @endunless
            <a href="{{ route($prefix.'.banks.index') }}" class="mt-2 inline-block text-sm underline">Change primary account</a>
        </div>
        <p class="mb-4 text-sm text-text-secondary">
            Available: {{ money($wallet->availableBalance()) }}.
            You can withdraw up to {{ money($wallet->withdrawableBalance(), 2, 2) }}.
            @if (bccomp($wallet->subCentRemainder(), '0', 4) > 0)
                <span class="text-text-muted">The remaining {{ money($wallet->subCentRemainder()) }} stays in your wallet until it adds up to a full cent.</span>
            @endif
        </p>

        @if ($step === 'confirm')
            <form
                method="POST"
                action="{{ route($prefix.'.withdrawal.otp') }}"
                class="space-y-4"
                x-data="withdrawalQuote(@js(route($prefix.'.withdrawal.quote')), @js(old('amount', '')))"
            >
                @csrf
                <input type="hidden" name="payout_account_id" value="{{ $account->id }}">
                <x-dashboard.input
                    :label="'Amount ('.site_currency()->code.')'"
                    type="number"
                    name="amount"
                    :min="$withdrawalMin"
                    :max="min((float) $withdrawalMax, (float) $wallet->withdrawableBalance())"
                    step="0.01"
                    x-model="amount"
                    x-on:input.debounce.500ms="load()"
                    :hint="'Min '.money($withdrawalMin, 2).' · Max '.money($withdrawalMax, 2)"
                    required
                />

                <div class="rounded-xl border border-border-subtle p-4 text-sm space-y-1" x-show="amount" x-cloak>
                    <p class="text-xs text-text-muted" x-show="loading">Getting the current fee…</p>
                    <p class="text-danger" x-show="error" x-text="error"></p>
                    <template x-if="quote && !error">
                        <div class="space-y-1">
                            <p class="flex justify-between gap-4"><span>You withdraw</span><strong x-text="quote.amount"></strong></p>
                            <p class="flex justify-between gap-4"><span>Provider fee ({{ $account->methodLabel() }}, {{ $account->countryName() }})</span><span x-text="quote.provider_fee"></span></p>
                            <p class="flex justify-between gap-4" x-show="quote.platform_fee"><span>Platform fee</span><span x-text="quote.platform_fee"></span></p>
                            <p class="flex justify-between gap-4 border-t border-border-subtle pt-1"><span>Sent to your account</span><strong x-text="quote.net"></strong></p>
                            <p class="text-xs text-text-muted" x-show="quote.destination_amount">
                                Estimated amount received: <span x-text="quote.destination_amount"></span> <span x-text="quote.destination_currency"></span>
                                (rate <span x-text="quote.fx_rate"></span>, quoted <span x-text="quote.quoted_at"></span>). The final rate is set by the provider at payout.
                            </p>
                            <p class="text-xs text-text-muted" x-show="!quote.destination_amount && quote.destination_currency && quote.destination_currency !== '{{ site_currency()->code }}'">
                                Your account receives this in <span x-text="quote.destination_currency"></span> at the payment provider's rate at payout time.
                            </p>
                            <p class="text-xs text-warning" x-show="quote.high_fee">The fee is a large share of this amount. A cheaper payout method, or waiting until your balance is higher, would lower it.</p>
                        </div>
                    </template>
                </div>

                @if (bccomp($bounceFee, '0', 2) > 0)
                    <p class="text-xs text-text-muted">If the payout is returned for incorrect account details, a {{ money($bounceFee, 2) }} fee applies. All other failed payouts are refunded in full.</p>
                @else
                    <p class="text-xs text-text-muted">If a payout fails, the full amount including the fee is returned to your balance.</p>
                @endif

                <x-dashboard.input type="password" name="password" label="Confirm your password" required autocomplete="current-password" />
                <x-dashboard.button type="submit" icon="withdraw">Send email code</x-dashboard.button>
            </form>
        @elseif ($step === 'otp')
            @if ($breakdown)
                <div class="mb-4 rounded-xl border border-border-subtle p-4 text-sm space-y-1">
                    <p class="flex justify-between gap-4"><span>You withdraw</span><strong>{{ money($breakdown['amount'], 2) }}</strong></p>
                    <p class="flex justify-between gap-4"><span>Provider fee</span><span>{{ money($breakdown['provider_fee'], 2) }}</span></p>
                    @if (bccomp($breakdown['platform_fee'], '0', 2) > 0)
                        <p class="flex justify-between gap-4"><span>Platform fee</span><span>{{ money($breakdown['platform_fee'], 2) }}</span></p>
                    @endif
                    <p class="flex justify-between gap-4 border-t border-border-subtle pt-1"><span>Sent to your account</span><strong>{{ money($breakdown['net'], 2) }}</strong></p>
                </div>
            @endif
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
    @endif
</x-layout.page>

<script>
    function withdrawalQuote(url, initial) {
        return {
            amount: initial || '',
            quote: null,
            error: null,
            loading: false,
            init() {
                if (this.amount) this.load();
            },
            async load() {
                this.error = null;
                if (!this.amount || Number(this.amount) <= 0) {
                    this.quote = null;
                    return;
                }
                this.loading = true;
                try {
                    const res = await fetch(url + '?amount=' + encodeURIComponent(this.amount), { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    if (!res.ok) {
                        this.quote = null;
                        this.error = data.message || 'The fee could not be calculated.';
                        return;
                    }
                    this.quote = data;
                } catch (e) {
                    this.quote = null;
                    this.error = 'The fee could not be calculated. Try again.';
                } finally {
                    this.loading = false;
                }
            },
        };
    }
</script>
@endsection
