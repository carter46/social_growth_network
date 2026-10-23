@extends($layout ?? 'layouts.dashboard-user')

@section('title', 'Add payout account')

@section('content')
@php $prefix = $prefix ?? 'dashboard'; @endphp
<x-layout.page
    :title="$intent === 'manage' ? 'Verify it\'s you' : 'Add payout account'"
    width="full"
    :breadcrumb="[
        [$prefix === 'agent' ? 'Agent' : 'Dashboard', route($prefix === 'agent' ? 'agent' : 'dashboard')],
        ['Payout accounts', route($prefix.'.banks.index')],
        [$intent === 'manage' ? 'Verify' : 'Add', null],
    ]"
>
    @if (session('status'))
        <x-dashboard.alert type="success" class="mb-4">{{ session('status') }}</x-dashboard.alert>
    @endif
    @if ($errors->any())
        <x-dashboard.alert type="danger" class="mb-4">{{ $errors->first() }}</x-dashboard.alert>
    @endif

    <x-dashboard.card>
        @if ($step === 'password')
            <form method="POST" action="{{ route($prefix.'.banks.replace.otp') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="intent" value="{{ $intent }}">
                <p class="text-sm text-text-secondary">Changes to payout accounts need your password and a code sent to your email.</p>
                <x-dashboard.input type="password" name="password" label="Confirm your password" required autocomplete="current-password" />
                <x-dashboard.button type="submit">Send email code</x-dashboard.button>
            </form>
        @elseif ($step === 'otp')
            <form method="POST" action="{{ route($prefix.'.banks.replace.verify-otp') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="intent" value="{{ session('payout_intent', $intent) }}">
                <x-dashboard.input name="otp" label="6-digit verification code" maxlength="6" required />
                <x-dashboard.button type="submit">Verify code</x-dashboard.button>
            </form>
        @elseif ($atLimit)
            <p class="text-sm text-text-secondary">You already have the most payout accounts allowed. Remove one first.</p>
            <x-dashboard.button :href="route($prefix.'.banks.index')" class="mt-4" variant="secondary">Back to payout accounts</x-dashboard.button>
        @elseif ($countries === [])
            <x-dashboard.alert type="warning">Payout accounts can't be added right now. Try again later.</x-dashboard.alert>
        @else
            <form
                method="POST"
                action="{{ route($prefix.'.banks.replace.confirm') }}"
                class="space-y-5"
                x-data="payoutAccountForm({
                    countries: @js($countries),
                    schemas: @js($schemas),
                    optionsUrl: @js(route($prefix.'.banks.options')),
                    resolveUrl: @js(route($prefix.'.banks.replace.resolve')),
                    csrf: @js(csrf_token()),
                    country: @js(old('country', '')),
                    method: @js(old('method', '')),
                    fields: @js((object) old('fields', [])),
                })"
            >
                @csrf
                <div>
                    <label class="mb-1 block text-sm font-medium" for="payout_country">Country</label>
                    <select id="payout_country" name="country" x-model="country" @change="onCountry()" class="w-full rounded-lg border border-border-subtle px-3 py-2 text-sm" required>
                        <option value="">Choose the country of the account</option>
                        <template x-for="(c, code) in countries" :key="code">
                            <option :value="code" x-text="c.name" :selected="code === country"></option>
                        </template>
                    </select>
                </div>

                <div x-show="country" x-cloak>
                    <label class="mb-1 block text-sm font-medium" for="payout_method">Payout method</label>
                    <select id="payout_method" name="method" x-model="method" @change="onMethod()" class="w-full rounded-lg border border-border-subtle px-3 py-2 text-sm" required>
                        <option value="">Choose a method</option>
                        <template x-for="(label, key) in (countries[country]?.methods || {})" :key="key">
                            <option :value="key" x-text="label" :selected="key === method"></option>
                        </template>
                    </select>
                    <p class="mt-1 text-xs text-text-muted" x-show="country">Paid out in <span x-text="countries[country]?.currency"></span> at the payment provider's rate.</p>
                </div>

                <template x-for="(def, name) in currentFields()" :key="country + method + name">
                    <div>
                        <label class="mb-1 block text-sm font-medium" :for="'f_' + name" x-text="def.label"></label>
                        <template x-if="def.type === 'select'">
                            <select :id="'f_' + name" :name="'fields[' + name + ']'" x-model="fields[name]" @change="resetResolved(); tryResolve()" class="w-full rounded-lg border border-border-subtle px-3 py-2 text-sm" :required="def.required">
                                <option value="" x-text="loadingOptions ? 'Loading…' : 'Choose'"></option>
                                <template x-for="o in options" :key="o.code">
                                    <option :value="o.code" x-text="o.name" :selected="o.code === fields[name]"></option>
                                </template>
                            </select>
                        </template>
                        <template x-if="def.type === 'choice'">
                            <select :id="'f_' + name" :name="'fields[' + name + ']'" x-model="fields[name]" class="w-full rounded-lg border border-border-subtle px-3 py-2 text-sm" :required="def.required">
                                <option value="">Choose</option>
                                <template x-for="(label, value) in def.options" :key="value">
                                    <option :value="value" x-text="label" :selected="value === fields[name]"></option>
                                </template>
                            </select>
                        </template>
                        <template x-if="def.type !== 'select' && def.type !== 'choice'">
                            <input
                                :id="'f_' + name"
                                type="text"
                                :name="'fields[' + name + ']'"
                                x-model="fields[name]"
                                @input.debounce.600ms="resetResolved(); tryResolve()"
                                :inputmode="def.type === 'digits' ? 'numeric' : 'text'"
                                :maxlength="def.max || 160"
                                autocomplete="off"
                                class="w-full rounded-lg border border-border-subtle px-3 py-2 text-sm"
                                :required="def.required"
                            >
                        </template>
                    </div>
                </template>

                <div x-show="method" x-cloak class="rounded-xl border border-border-subtle p-4 text-sm space-y-2">
                    <p><span class="text-text-muted">Must match your profile name:</span> <strong>{{ $profileName }}</strong></p>
                    <template x-if="isAuto()">
                        <div class="space-y-1">
                            <p class="text-xs text-text-muted" x-show="resolving">Checking the name on this account…</p>
                            <p class="text-xs text-danger" x-show="resolveError" x-text="resolveError"></p>
                            <p x-show="resolvedName"><span class="text-text-muted">Name at the bank:</span> <strong x-text="resolvedName"></strong></p>
                            <p class="text-xs text-success" x-show="matches === true">Names match. You can save this account.</p>
                            <p class="text-xs text-danger" x-show="resolvedName && matches === false">This bank name doesn’t match your profile. Use an account in your own name, or update your profile name first. You can’t save until they match.</p>
                        </div>
                    </template>
                    <template x-if="!isAuto()">
                        <label class="flex items-start gap-2">
                            <input type="checkbox" name="confirm_name" value="1" class="mt-1" required>
                            <span>This account is in my name ({{ $profileName }}). It can’t be checked automatically, so the first payout is reviewed.</span>
                        </label>
                    </template>
                    <label class="flex items-start gap-2">
                        <input type="checkbox" name="accept_terms" value="1" class="mt-1" required>
                        <span>I confirm this payout account belongs to {{ $profileName }}.</span>
                    </label>
                    <p class="text-xs text-text-muted">Your profile name is locked after you save a payout account.</p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <x-dashboard.button type="submit" variant="primary" x-bind:disabled="!canSave()">Save payout account</x-dashboard.button>
                    <x-dashboard.button :href="route($prefix.'.banks.index')" variant="secondary">Cancel</x-dashboard.button>
                </div>
            </form>
        @endif
    </x-dashboard.card>
</x-layout.page>

<script>
    function payoutAccountForm(cfg) {
        return {
            countries: cfg.countries,
            schemas: cfg.schemas,
            country: cfg.country || '',
            method: cfg.method || '',
            fields: Object.assign({}, cfg.fields || {}),
            options: [],
            loadingOptions: false,
            resolving: false,
            resolvedName: null,
            matches: null,
            resolveError: null,
            init() {
                if (this.country && this.method) this.loadOptions();
            },
            currentFields() {
                return (this.schemas[this.country] || {})[this.method]?.fields || {};
            },
            isAuto() {
                return ((this.schemas[this.country] || {})[this.method]?.name_check) === 'auto';
            },
            onCountry() {
                this.method = '';
                this.fields = {};
                this.options = [];
                this.resetResolved();
                const methods = Object.keys(this.countries[this.country]?.methods || {});
                if (methods.length === 1) {
                    this.method = methods[0];
                    this.onMethod();
                }
            },
            onMethod() {
                this.fields = {};
                this.resetResolved();
                this.loadOptions();
            },
            resetResolved() {
                this.resolvedName = null;
                this.matches = null;
                this.resolveError = null;
            },
            async loadOptions() {
                this.options = [];
                const needs = Object.values(this.currentFields()).some(f => f.type === 'select');
                if (!needs) return;
                this.loadingOptions = true;
                try {
                    const url = cfg.optionsUrl + '?country=' + encodeURIComponent(this.country) + '&method=' + encodeURIComponent(this.method);
                    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    this.options = data.options || [];
                } catch (e) {
                    this.options = [];
                } finally {
                    this.loadingOptions = false;
                }
            },
            ready() {
                return Object.entries(this.currentFields()).every(([name, def]) => !def.required || (this.fields[name] || '').toString().trim() !== '');
            },
            canSave() {
                if (!this.method || this.resolving) return false;
                if (this.isAuto()) return this.matches === true;
                return true;
            },
            async tryResolve() {
                if (!this.isAuto() || !this.ready()) return;
                this.resolving = true;
                this.resolveError = null;
                this.matches = null;
                this.resolvedName = null;
                try {
                    const res = await fetch(cfg.resolveUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf },
                        body: JSON.stringify({ country: this.country, method: this.method, fields: this.fields }),
                    });
                    const data = await res.json();
                    if (!res.ok) {
                        this.resolveError = data.message || 'We could not check this account.';
                        this.matches = false;
                        return;
                    }
                    this.resolvedName = data.account_name;
                    this.matches = data.matches === true;
                } catch (e) {
                    this.resolveError = 'We could not check this account. Try again.';
                    this.matches = false;
                } finally {
                    this.resolving = false;
                }
            },
        };
    }
</script>
@endsection
