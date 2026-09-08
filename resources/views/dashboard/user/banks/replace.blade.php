@extends($layout ?? 'layouts.dashboard-user')

@section('title', 'Replace bank')

@section('content')
@php $prefix = $prefix ?? 'dashboard'; @endphp
@php $step = session('bank_replace_step', old('_step', 'password')); @endphp
<x-layout.page
    title="Replace Bank Account"
    width="full"
    :breadcrumb="[
        [$prefix === 'agent' ? 'Agent' : 'Dashboard', route($prefix === 'agent' ? 'agent' : 'dashboard')],
        ['My Bank', route($prefix.'.banks.index')],
        ['Replace', null],
    ]"
>
    <x-dashboard.card>
        @if ($step === 'password')
            <form method="POST" action="{{ route($prefix.'.banks.replace.otp') }}" class="space-y-4">
                @csrf
                <x-dashboard.input type="password" name="password" label="Confirm your password" required autocomplete="current-password" />
                <x-dashboard.button type="submit">Send email code</x-dashboard.button>
            </form>
        @elseif ($step === 'otp')
            <form method="POST" action="{{ route($prefix.'.banks.replace.verify-otp') }}" class="space-y-4">
                @csrf
                <x-dashboard.input name="otp" label="6-digit verification code" maxlength="6" required />
                <x-dashboard.button type="submit">Verify code</x-dashboard.button>
            </form>
        @elseif ($step === 'bank')
            <div
                class="space-y-4"
                x-data="bankReplaceResolve(@js(route($prefix.'.banks.replace.resolve')), @js(csrf_token()), @js($banks))"
                @click.outside="bankOpen = false"
            >
                <div class="relative">
                    <label class="block text-sm font-medium mb-1" for="bank_search">Bank</label>
                    <input
                        id="bank_search"
                        type="text"
                        autocomplete="off"
                        placeholder="Search bank…"
                        class="w-full rounded-lg border border-border-subtle px-3 py-2 text-sm"
                        x-model="bankQuery"
                        @focus="bankOpen = true"
                        @input="bankOpen = true; if (!bankQuery) clearBank()"
                        required
                    >
                    <input type="hidden" name="bank_code" :value="bankCode" required>
                    <div
                        x-show="bankOpen && filteredBanks.length"
                        x-cloak
                        class="absolute z-20 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-border-default bg-elevated shadow-panel"
                    >
                        <template x-for="bank in filteredBanks" :key="bank.code">
                            <button
                                type="button"
                                class="block w-full px-3 py-2 text-left text-sm hover:bg-muted/60"
                                @click="selectBank(bank)"
                                x-text="bank.name"
                            ></button>
                        </template>
                    </div>
                    <p class="mt-1 text-xs text-text-muted" x-show="!bankCode">Type to search from supported banks.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" for="account_number">Account number</label>
                    <input
                        id="account_number"
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        maxlength="20"
                        class="w-full rounded-lg border border-border-subtle px-3 py-2 text-sm"
                        required
                        x-model="accountNumber"
                        @input.debounce.500ms="tryResolve()"
                    >
                    <p class="mt-1 text-xs text-text-muted" x-show="resolving" x-cloak>Looking up account name…</p>
                    <p class="mt-1 text-xs text-danger" x-show="error" x-text="error" x-cloak></p>
                </div>

                <div
                    x-show="resolved"
                    x-cloak
                    class="rounded-xl border border-border-subtle p-4 text-sm space-y-1"
                >
                    <p><strong>Bank:</strong> <span x-text="resolved?.bankName"></span></p>
                    <p><strong>Account:</strong> <span x-text="resolved?.accountNumber"></span></p>
                    <p><strong>Account name:</strong> <span x-text="resolved?.accountName"></span></p>
                </div>

                <form
                    method="POST"
                    action="{{ route($prefix.'.banks.replace.confirm') }}"
                    class="space-y-4"
                    x-show="resolved"
                    x-cloak
                >
                    @csrf
                    <input type="hidden" name="bank_code" :value="resolved?.bankCode">
                    <input type="hidden" name="bank_name" :value="resolved?.bankName">
                    <input type="hidden" name="account_number" :value="resolved?.accountNumber">
                    <input type="hidden" name="verified_name" :value="resolved?.accountName">
                    <x-dashboard.button type="submit" variant="primary">Confirm &amp; save</x-dashboard.button>
                </form>
            </div>
        @elseif ($step === 'confirm' && $resolved)
            <div class="mb-4 rounded-xl border border-border-subtle p-4 text-sm space-y-1">
                <p><strong>Bank:</strong> {{ $resolved['bankName'] }}</p>
                <p><strong>Account:</strong> {{ $resolved['accountNumber'] }}</p>
                <p><strong>Account name:</strong> {{ $resolved['accountName'] }}</p>
            </div>
            <form method="POST" action="{{ route($prefix.'.banks.replace.confirm') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="bank_code" value="{{ $resolved['bankCode'] }}">
                <input type="hidden" name="bank_name" value="{{ $resolved['bankName'] }}">
                <input type="hidden" name="account_number" value="{{ $resolved['accountNumber'] }}">
                <input type="hidden" name="verified_name" value="{{ $resolved['accountName'] }}">
                <x-dashboard.button type="submit" variant="primary">Confirm &amp; save</x-dashboard.button>
            </form>
        @else
            <p class="text-sm text-text-secondary">Start again from the beginning.</p>
            <x-dashboard.button :href="route($prefix.'.banks.replace')" class="mt-4">Restart</x-dashboard.button>
        @endif
    </x-dashboard.card>
</x-layout.page>
@endsection
