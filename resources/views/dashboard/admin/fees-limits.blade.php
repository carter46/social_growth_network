@extends('layouts.dashboard-admin')

@section('title', 'Fees & Limits')

@section('content')
<x-layout.page
    title="Fees & Limits"
    subtitle="Amount limits for creator wallet deposits and agent withdrawals, and payout fee rules per route."
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Fees & Limits', null],
    ]"
>
    <x-dashboard.card variant="solid">
        <form method="POST" action="{{ route('admin.fees-limits.update') }}" class="w-full space-y-6" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 w-full">
                <div class="w-full">
                    <x-dashboard.input
                        name="deposit_min_amount"
                        type="number"
                        step="0.01"
                        :label="'Minimum deposit ('.site_currency()->code.')'"
                        :value="old('deposit_min_amount', $depositMinAmount)"
                        hint="Smallest amount a creator can add to their wallet in one payment."
                        required
                    />
                </div>
                <div class="w-full">
                    <x-dashboard.input
                        name="deposit_max_amount"
                        type="number"
                        step="0.01"
                        :label="'Maximum deposit ('.site_currency()->code.')'"
                        :value="old('deposit_max_amount', $depositMaxAmount)"
                        hint="Largest amount a creator can add to their wallet in one payment."
                        required
                    />
                </div>
                <div class="w-full">
                    <x-dashboard.input
                        name="withdrawal_min_amount"
                        type="number"
                        step="0.01"
                        :label="'Minimum withdrawal ('.site_currency()->code.')'"
                        :value="old('withdrawal_min_amount', $withdrawalMinAmount)"
                        hint="Floor for every route. A route can be higher because of its own rule, the provider minimum or the fee-share limit."
                        required
                    />
                </div>
                <div class="w-full">
                    <x-dashboard.input
                        name="withdrawal_max_amount"
                        type="number"
                        step="0.01"
                        :label="'Maximum withdrawal ('.site_currency()->code.')'"
                        :value="old('withdrawal_max_amount', $withdrawalMaxAmount)"
                        hint="Largest amount an agent can withdraw in one request."
                        required
                    />
                </div>
                <div class="w-full">
                    <x-dashboard.input
                        name="withdrawal_bounce_fee"
                        type="number"
                        step="0.01"
                        :label="'Returned payout fee ('.site_currency()->code.')'"
                        :value="old('withdrawal_bounce_fee', $bounceFee)"
                        hint="Charged only when a payout is returned because the account details were wrong, the provider reports a return charge, and the agent accepted this fee when adding the account. 0 turns it off. Raising it asks agents to accept the new amount before their next withdrawal."
                        required
                    />
                </div>
            </div>
            <x-dashboard.button type="submit" variant="primary" x-bind:disabled="submitting">Save fees & limits</x-dashboard.button>
        </form>
    </x-dashboard.card>

    <x-dashboard.card variant="solid" class="mt-6">
        <div class="space-y-4">
            <div>
                <h2 class="text-base font-semibold text-text-primary">Payout fee rules</h2>
                <p class="text-sm text-text-secondary">
                    A rule can set a platform fee, a verified provider fee and route limits. Blank country, method or gateway means "any". The most specific active rule wins.
                    A verified provider fee is used only when the gateway gives no live quote, and it stops being used after {{ $staleDays }} days until it is verified again.
                </p>
            </div>

            @if ($rules->isEmpty())
                <p class="text-sm text-text-secondary">No payout fee rules yet.</p>
            @endif

            @foreach ($rules as $rule)
                @php($info = $routeInfo->get($rule->id))
                <details class="rounded-lg border border-border-default p-4">
                    <summary class="cursor-pointer flex flex-wrap items-center gap-2 text-sm">
                        <span class="font-medium text-text-primary">{{ $rule->routeLabel() }}</span>
                        @if ($rule->active)
                            <x-dashboard.badge variant="success">Active</x-dashboard.badge>
                        @else
                            <x-dashboard.badge variant="neutral">Inactive</x-dashboard.badge>
                        @endif
                        @if ($rule->hasVerifiedProviderFee())
                            @if ($rule->verifiedFeeIsStale())
                                <x-dashboard.badge variant="warning">Verified fee is stale</x-dashboard.badge>
                            @else
                                <x-dashboard.badge variant="info">Verified fee</x-dashboard.badge>
                            @endif
                        @endif
                        @if ($rule->drift_flagged_at)
                            <x-dashboard.badge variant="danger">Fee drift flagged</x-dashboard.badge>
                        @endif
                    </summary>

                    <div class="mt-4 space-y-3 text-sm text-text-secondary">
                        @if ($rule->hasVerifiedProviderFee())
                            <p>
                                Verified provider fee: {{ money($rule->verified_provider_fee_flat ?? 0, 2) }}
                                @if ((float) $rule->verified_provider_fee_percent > 0) + {{ rtrim(rtrim((string) $rule->verified_provider_fee_percent, '0'), '.') }}% @endif
                                · source {{ str_replace('_', ' ', (string) $rule->verified_source) }}
                                @if ($rule->verified_reference) · ref {{ $rule->verified_reference }} @endif
                                · verified {{ $rule->verified_at?->format('M j, Y') }}
                                @if ($rule->verifier) by {{ $rule->verifier->name }} @endif
                            </p>
                        @endif
                        @if ($info)
                            <p>
                                Effective minimum {{ money($info['min'], 2) }}, maximum {{ money($info['max'], 2) }}.
                                @if ($info['fee_at_min'])
                                    Provider fee at the minimum: {{ money($info['fee_at_min'], 2) }} ({{ $info['share_at_min'] }}% of the amount).
                                @endif
                            </p>
                        @endif
                        @if ($rule->drift_note)
                            <p class="text-danger">{{ $rule->drift_note }}</p>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('admin.fees-limits.rules.update', $rule) }}" class="mt-4 space-y-4">
                        @csrf
                        @method('PUT')
                        @include('dashboard.admin.partials.payout-fee-rule-fields', ['rule' => $rule])
                        <label class="flex items-center gap-2 text-sm text-text-secondary">
                            <input type="checkbox" name="reverify" value="1" class="rounded border-border-default">
                            Mark the provider fee as verified again today
                        </label>
                        <div class="flex flex-wrap gap-2">
                            <x-dashboard.button type="submit" variant="primary">Save rule</x-dashboard.button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('admin.fees-limits.rules.destroy', $rule) }}" class="mt-2" onsubmit="return confirm('Remove this payout fee rule?')">
                        @csrf
                        @method('DELETE')
                        <x-dashboard.button type="submit" variant="danger">Remove rule</x-dashboard.button>
                    </form>
                </details>
            @endforeach

            <details class="rounded-lg border border-dashed border-border-default p-4" @if ($errors->any() && old('_rule_form') === 'new') open @endif>
                <summary class="cursor-pointer text-sm font-medium text-text-primary">Add a payout fee rule</summary>
                <form method="POST" action="{{ route('admin.fees-limits.rules.store') }}" class="mt-4 space-y-4">
                    @csrf
                    <input type="hidden" name="_rule_form" value="new">
                    @include('dashboard.admin.partials.payout-fee-rule-fields', ['rule' => null])
                    <x-dashboard.button type="submit" variant="primary">Add rule</x-dashboard.button>
                </form>
            </details>
        </div>
    </x-dashboard.card>
</x-layout.page>
@endsection
