@php
    $isNew = $rule === null;
    $val = fn (string $field) => $isNew ? old($field) : old($field, $rule->{$field});
@endphp
<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <x-dashboard.select name="country" label="Country">
        <option value="">Any country</option>
        @foreach ($countries as $code => $name)
            <option value="{{ $code }}" @selected($val('country') === $code)>{{ $name }} ({{ $code }})</option>
        @endforeach
    </x-dashboard.select>
    <x-dashboard.select name="method" label="Payout method">
        <option value="">Any method</option>
        @foreach ($methods as $key => $label)
            <option value="{{ $key }}" @selected($val('method') === $key)>{{ $label }}</option>
        @endforeach
    </x-dashboard.select>
    <x-dashboard.select name="gateway_key" label="Gateway">
        <option value="">Any gateway</option>
        @foreach ($gateways as $key => $label)
            <option value="{{ $key }}" @selected($val('gateway_key') === $key)>{{ $label }}</option>
        @endforeach
    </x-dashboard.select>
</div>

<p class="text-sm font-medium text-text-primary">Platform fee (optional, added on top of the provider fee)</p>
<div class="grid grid-cols-1 md:grid-cols-4 gap-4">
    <x-dashboard.input name="flat_fee" type="number" step="0.01" :label="'Flat ('.site_currency()->code.')'" :value="$val('flat_fee')" />
    <x-dashboard.input name="percent_fee" type="number" step="0.001" label="Percent" :value="$val('percent_fee')" />
    <x-dashboard.input name="min_fee" type="number" step="0.01" :label="'Minimum ('.site_currency()->code.')'" :value="$val('min_fee')" />
    <x-dashboard.input name="max_fee" type="number" step="0.01" :label="'Maximum ('.site_currency()->code.')'" :value="$val('max_fee')" />
</div>

<p class="text-sm font-medium text-text-primary">Verified provider fee (used when no live quote is available)</p>
<div class="grid grid-cols-1 md:grid-cols-4 gap-4">
    <x-dashboard.input name="verified_provider_fee_flat" type="number" step="0.01" :label="'Flat ('.site_currency()->code.')'" :value="$val('verified_provider_fee_flat')" />
    <x-dashboard.input name="verified_provider_fee_percent" type="number" step="0.001" label="Percent" :value="$val('verified_provider_fee_percent')" />
    <x-dashboard.select name="verified_source" label="Verified from">
        <option value="">Not verified</option>
        <option value="{{ \App\Models\PayoutFeeRule::SOURCE_LIVE_QUOTE }}" @selected($val('verified_source') === \App\Models\PayoutFeeRule::SOURCE_LIVE_QUOTE)>Live quote</option>
        <option value="{{ \App\Models\PayoutFeeRule::SOURCE_PROVIDER_INVOICE }}" @selected($val('verified_source') === \App\Models\PayoutFeeRule::SOURCE_PROVIDER_INVOICE)>Provider invoice</option>
        <option value="{{ \App\Models\PayoutFeeRule::SOURCE_TEST_PAYOUT }}" @selected($val('verified_source') === \App\Models\PayoutFeeRule::SOURCE_TEST_PAYOUT)>Test payout</option>
    </x-dashboard.select>
    <x-dashboard.input name="verified_reference" label="Reference" :value="$val('verified_reference')" hint="Required for a test payout." />
</div>

<p class="text-sm font-medium text-text-primary">Route limits</p>
<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <x-dashboard.input name="min_withdrawal" type="number" step="0.01" :label="'Minimum withdrawal ('.site_currency()->code.')'" :value="$val('min_withdrawal')" hint="Must be above the verified provider fee." />
    <x-dashboard.input name="max_withdrawal" type="number" step="0.01" :label="'Maximum withdrawal ('.site_currency()->code.')'" :value="$val('max_withdrawal')" />
    <label class="flex items-center gap-2 text-sm text-text-secondary md:mt-7">
        <input type="hidden" name="active" value="0">
        <input type="checkbox" name="active" value="1" class="rounded border-border-default" @checked($isNew ? old('active', true) : old('active', $rule->active))>
        Active
    </label>
</div>
