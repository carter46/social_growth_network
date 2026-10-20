@extends($layout ?? 'layouts.dashboard-agent')

@section('title', 'Connect bank account')

@section('content')
@php
    $prefix = $prefix ?? 'agent';
    $prefill = $prefill ?? [];
    [$prefillDial, $prefillLocal] = \App\Support\PhoneNumber::split(
        (string) ($prefill['phone'] ?? ''),
        (string) ($prefill['country'] ?? '')
    );
@endphp
<x-layout.page
    title="Connect bank account"
    subtitle="Add the account where we should send your earnings. You stay on this page — nothing opens in another site."
    width="lg"
    :breadcrumb="[
        ['Agent', route('agent')],
        ['Wallet', route('agent.wallet')],
        ['Connect bank', null],
    ]"
>
    @if (session('error'))
        <x-dashboard.alert type="danger" class="mb-4">{{ session('error') }}</x-dashboard.alert>
    @endif
    @if ($errors->any())
        <x-dashboard.alert type="danger" class="mb-4">{{ $errors->first() }}</x-dashboard.alert>
    @endif

    @if (($countries ?? []) === [])
        <x-dashboard.alert type="warning">Payout setup is not available for any country right now. Please try again later.</x-dashboard.alert>
    @else
        <x-dashboard.card>
            <form
                method="POST"
                action="{{ route('agent.payouts.connect.submit') }}"
                enctype="multipart/form-data"
                class="space-y-5"
                x-data="payoutConnectForm({
                    countries: @js($countries),
                    dialCodes: @js(\App\Support\PhoneNumber::dialCodes()),
                    optionsUrl: @js(route('agent.payouts.connect.options')),
                    csrf: @js(csrf_token()),
                    country: @js($prefill['country'] ?? ''),
                    method: @js($prefill['method'] ?? 'bank_account'),
                    bankCode: @js($prefill['bank_code'] ?? ''),
                    mobileProvider: @js($prefill['mobile_provider'] ?? ''),
                    phoneDial: @js($prefillDial),
                    phoneLocal: @js($prefillLocal),
                })"
                x-init="onCountry()"
            >
                @csrf

                <div>
                    <h3 class="text-sm font-semibold text-text-primary">Payout country</h3>
                    <p class="mt-1 text-xs text-text-muted">Use the country of the bank or wallet that will receive payouts.</p>
                    <select name="country" x-model="country" @change="onCountry()" class="mt-2 w-full rounded-lg border border-border-subtle px-3 py-2 text-sm" required>
                        <option value="">Choose country</option>
                        <template x-for="(c, code) in countries" :key="code">
                            <option :value="code" x-text="c.name" :selected="code === country"></option>
                        </template>
                    </select>
                    <p class="mt-1 text-xs text-text-muted" x-show="country" x-cloak>
                        Paid out in <span x-text="countries[country]?.currency"></span>
                    </p>
                </div>

                <div x-show="country" x-cloak>
                    <label class="mb-1 block text-sm font-medium">Payout method</label>
                    <select name="method" x-model="method" @change="onMethod()" class="w-full rounded-lg border border-border-subtle px-3 py-2 text-sm" required>
                        <template x-for="(label, key) in (countries[country]?.methods || {})" :key="key">
                            <option :value="key" x-text="label" :selected="key === method"></option>
                        </template>
                    </select>
                </div>

                <div class="border-t border-border-subtle pt-4">
                    <h3 class="text-sm font-semibold text-text-primary">Your details</h3>
                    <p class="mt-1 text-xs text-text-muted">We prefilled what we already have. Confirm or correct anything that is wrong.</p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <x-dashboard.input name="first_name" label="First name" :value="$prefill['first_name'] ?? ''" required />
                        <x-dashboard.input name="last_name" label="Last name" :value="$prefill['last_name'] ?? ''" required />
                        <x-dashboard.input type="email" name="email" label="Email" :value="$prefill['email'] ?? ''" required />
                        <div>
                            <label class="mb-1 block text-sm font-medium" for="connect-phone">Phone number</label>
                            <div class="flex">
                                <span class="inline-flex items-center rounded-l-lg border border-r-0 border-border-subtle bg-muted px-3 text-sm font-medium text-text-secondary" x-text="phoneDial || '+…'" aria-hidden="true"></span>
                                <input
                                    id="connect-phone"
                                    type="tel"
                                    inputmode="tel"
                                    autocomplete="tel-national"
                                    x-model="phoneLocal"
                                    placeholder="Phone number"
                                    required
                                    class="block w-full rounded-r-lg border border-border-subtle px-3 py-2 text-sm bg-surface text-text-primary focus-ring"
                                >
                            </div>
                            <input type="hidden" name="phone" :value="phoneE164">
                            <p class="mt-1 text-xs text-text-muted">Country code follows the payout country above.</p>
                        </div>
                        <x-dashboard.input type="date" name="dob" label="Date of birth" :value="$prefill['dob'] ?? ''" required />
                    </div>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <x-dashboard.input name="address_line1" label="Address line" :value="$prefill['address_line1'] ?? ''" required />
                        </div>
                        <x-dashboard.input name="address_city" label="City" :value="$prefill['address_city'] ?? ''" required />
                        <x-dashboard.input name="address_state" label="State / region" :value="$prefill['address_state'] ?? ''" required />
                        <x-dashboard.input name="address_postal_code" label="Postal code (optional)" :value="$prefill['address_postal_code'] ?? ''" />
                    </div>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium" for="id_type">ID type (if required)</label>
                            <select id="id_type" name="id_type" class="w-full rounded-lg border border-border-subtle px-3 py-2 text-sm">
                                @foreach ([
                                    'id_card' => 'National / government ID',
                                    'passport' => 'Passport',
                                    'drivers_license' => 'Driver’s licence',
                                    'residence_permit' => 'Residence permit',
                                    'nin' => 'National identity number',
                                    'bvn' => 'Bank verification number',
                                ] as $value => $label)
                                    <option value="{{ $value }}" @selected(($prefill['id_type'] ?? 'id_card') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-dashboard.input name="id_number" label="ID number (if required)" :value="$prefill['id_number'] ?? ''" />
                    </div>
                    <div class="mt-3">
                        <label class="mb-1 block text-sm font-medium" for="id_document">ID document photo (if required)</label>
                        <input id="id_document" type="file" name="id_document" accept=".jpg,.jpeg,.png,.pdf" class="block w-full text-sm text-text-secondary" />
                        <p class="mt-1 text-xs text-text-muted">JPG, PNG, or PDF up to 5&nbsp;MB.</p>
                    </div>
                </div>

                <div class="border-t border-border-subtle pt-4" x-show="country && method" x-cloak>
                    <h3 class="text-sm font-semibold text-text-primary">Bank / wallet</h3>
                    <div class="mt-3 space-y-3" x-show="method === 'bank_account'">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Bank</label>
                            <select name="bank_code" x-model="bankCode" class="w-full rounded-lg border border-border-subtle px-3 py-2 text-sm" :required="method === 'bank_account'">
                                <option value="" x-text="loadingOptions ? 'Loading…' : 'Choose bank'"></option>
                                <template x-for="o in options" :key="o.code">
                                    <option :value="o.code" x-text="o.name"></option>
                                </template>
                            </select>
                        </div>
                        <x-dashboard.input name="account_number" label="Account number" :value="$prefill['account_number'] ?? ''" />
                        <x-dashboard.input name="account_name" label="Account name" :value="$prefill['account_name'] ?? ''" />
                    </div>
                    <div class="mt-3 space-y-3" x-show="method === 'mobile_money'" x-cloak>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Network</label>
                            <select name="mobile_provider" x-model="mobileProvider" class="w-full rounded-lg border border-border-subtle px-3 py-2 text-sm" :required="method === 'mobile_money'">
                                <option value="" x-text="loadingOptions ? 'Loading…' : 'Choose network'"></option>
                                <template x-for="o in options" :key="o.code">
                                    <option :value="o.code" x-text="o.name"></option>
                                </template>
                            </select>
                        </div>
                        <x-dashboard.input name="phone_number" label="Wallet phone number" :value="$prefill['phone_number'] ?? ''" />
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 pt-2">
                    <x-dashboard.button type="submit" icon="withdraw">Save and continue</x-dashboard.button>
                    <x-dashboard.button :href="route('agent.wallet')" variant="secondary">Cancel</x-dashboard.button>
                </div>
            </form>
        </x-dashboard.card>
    @endif
</x-layout.page>

@push('scripts')
<script>
function payoutConnectForm(cfg) {
    return {
        countries: cfg.countries || {},
        dialCodes: cfg.dialCodes || {},
        optionsUrl: cfg.optionsUrl,
        csrf: cfg.csrf,
        country: cfg.country || '',
        method: cfg.method || 'bank_account',
        bankCode: cfg.bankCode || '',
        mobileProvider: cfg.mobileProvider || '',
        phoneDial: cfg.phoneDial || '',
        phoneLocal: cfg.phoneLocal || '',
        options: [],
        loadingOptions: false,
        get phoneE164() {
            if (!this.phoneDial) return '';
            let digits = String(this.phoneLocal || '').replace(/\D+/g, '');
            digits = digits.replace(/^0+/, '');
            return digits ? (this.phoneDial + digits) : '';
        },
        onCountry() {
            const methods = this.countries[this.country]?.methods || {};
            if (!methods[this.method]) {
                this.method = Object.keys(methods)[0] || 'bank_account';
            }
            this.phoneDial = this.dialCodes[this.country] || '';
            this.onMethod();
        },
        onMethod() {
            this.options = [];
            if (!this.country || !this.method) return;
            this.loadingOptions = true;
            fetch(this.optionsUrl + '?country=' + encodeURIComponent(this.country) + '&method=' + encodeURIComponent(this.method), {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
            })
                .then(r => r.json())
                .then(data => { this.options = data.options || []; })
                .catch(() => { this.options = []; })
                .finally(() => { this.loadingOptions = false; });
        },
    };
}
</script>
@endpush
@endsection
