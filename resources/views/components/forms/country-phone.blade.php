{{--
  Country select + phone input with auto dial-code prefix.
  Submits `country` and full E.164 `phone` (hidden). User only types the national number.
--}}
@props([
    'countries' => [],
    'country' => '',
    'phone' => '',
    'variant' => 'dashboard', // dashboard | marketing
    'countryId' => 'country',
    'phoneId' => 'phone',
    'countryName' => 'country',
    'phoneName' => 'phone',
    'required' => true,
])

@php
    $country = strtoupper((string) ($country ?: ''));
    [$initialDial, $initialLocal] = \App\Support\PhoneNumber::split($phone, $country !== '' ? $country : null);
    $selectClass = $variant === 'marketing'
        ? 'block w-full h-10 px-3 text-sm rounded-lg border border-slate-200 bg-white text-slate-900 focus-ring'
        : 'w-full rounded-lg border border-border-subtle px-3 py-2 text-sm';
    $prefixClass = $variant === 'marketing'
        ? 'inline-flex items-center rounded-l-lg border border-r-0 border-slate-200 bg-slate-100 px-3 text-sm font-medium text-slate-700'
        : 'inline-flex items-center rounded-l-lg border border-r-0 border-border-subtle bg-muted px-3 text-sm font-medium text-text-secondary';
    $localClass = $variant === 'marketing'
        ? 'block w-full h-10 px-3 text-sm rounded-r-lg border border-slate-200 bg-white text-slate-900 focus-ring'
        : 'block w-full rounded-r-lg border border-border-subtle px-3 py-2 text-sm bg-surface text-text-primary focus-ring';
    $labelClass = $variant === 'marketing'
        ? 'block text-sm font-medium text-slate-600'
        : 'mb-1 block text-sm font-medium';
    $phoneError = $errors->first($phoneName);
@endphp

<div
    class="space-y-3"
    x-data="{
        dialCodes: {{ \Illuminate\Support\Js::from(\App\Support\PhoneNumber::dialCodes()) }},
        country: {{ \Illuminate\Support\Js::from($country) }},
        dial: {{ \Illuminate\Support\Js::from($initialDial) }},
        local: {{ \Illuminate\Support\Js::from($initialLocal) }},
        init() {
            if (this.country) {
                this.applyDial(false);
            }
        },
        applyDial(resetLocal) {
            const next = this.dialCodes[this.country] || '';
            if (resetLocal && this.dial && this.local && this.dial !== next) {
                // keep national digits when switching countries
            }
            this.dial = next;
        },
        onCountryChange() {
            this.applyDial(true);
        },
        get e164() {
            if (! this.dial) return '';
            let digits = String(this.local || '').replace(/\D+/g, '');
            digits = digits.replace(/^0+/, '');
            return digits ? (this.dial + digits) : '';
        }
    }"
>
    <div>
        <label class="{{ $labelClass }}" for="{{ $countryId }}">Country</label>
        <select
            id="{{ $countryId }}"
            name="{{ $countryName }}"
            x-model="country"
            @change="onCountryChange()"
            @if ($required) required @endif
            class="{{ $selectClass }}"
        >
            <option value="">{{ $required ? 'Choose country' : 'Country (optional)' }}</option>
            @foreach ($countries as $c)
                @php
                    $code = is_array($c) ? (string) ($c['code'] ?? '') : (string) $c;
                    $name = is_array($c) ? (string) ($c['name'] ?? $code) : (string) $c;
                @endphp
                <option value="{{ $code }}">{{ $name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="{{ $labelClass }}" for="{{ $phoneId }}">Phone number</label>
        <div class="flex">
            <span class="{{ $prefixClass }}" x-text="dial || '+…'" aria-hidden="true"></span>
            <input
                id="{{ $phoneId }}"
                type="tel"
                inputmode="tel"
                autocomplete="tel-national"
                x-model="local"
                placeholder="Phone number"
                @if ($required) required @endif
                class="{{ $localClass }} {{ $phoneError ? ($variant === 'marketing' ? '!border-danger' : 'border-danger') : '' }}"
                @if ($phoneError) aria-invalid="true" @endif
            >
        </div>
        <input type="hidden" name="{{ $phoneName }}" :value="e164">
        @if ($phoneError)
            <p class="mt-1 text-xs text-danger">{{ $phoneError }}</p>
        @endif
    </div>
</div>
