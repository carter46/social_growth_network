@extends('layouts.dashboard-admin')

@section('title', 'Payment Settings')

@section('content')
@php
    $manualBank = $manualBankTransfer ?? [];
    $gatewayNames = collect($gateways)->map(fn ($d) => $d->displayName())->all();
    $collectionCapable = collect($gateways)->filter(fn ($d) => $d instanceof \App\Modules\Wallet\Payments\Contracts\CollectionGateway)->keys()->all();
    $payoutCapable = collect($gateways)->filter(fn ($d) => $d instanceof \App\Modules\Wallet\Payments\Contracts\PayoutGateway)->keys()->all();
    $orderList = fn (array $saved, array $capable) => array_values(array_unique(array_merge($saved, $capable)));
@endphp
<x-layout.page
    title="Payment Settings"
    subtitle="Payment gateways for checkout, deposits and withdrawals, plus manual bank transfer for orders. All amounts are in {{ $siteCurrency }}."
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Site Settings', route('admin.settings')],
        ['Payments', null],
    ]"
>
    <div class="space-y-6">
        @php
            $gate = $payoutGate ?? ['open' => false, 'reasons' => [], 'enabled' => false, 'verified_at' => null];
        @endphp
        <x-dashboard.card variant="solid">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-2">
                <h2 class="text-lg font-semibold text-text-primary">Gateway payouts</h2>
                <x-dashboard.badge :variant="$gate['open'] ? 'success' : 'warning'">{{ $gate['open'] ? 'Running' : 'Paused' }}</x-dashboard.badge>
            </div>
            <p class="text-sm text-text-secondary mb-3">
                Payouts send wallet amounts as USD. They stay paused until the site runs in USD, the currency cutover is verified,
                and payouts are switched on here. While paused, agents can still request withdrawals; they wait in review and nothing is sent or refunded.
            </p>
            @if ($gate['reasons'] !== [])
                <ul class="text-sm text-text-secondary list-disc pl-5 mb-3">
                    @foreach ($gate['reasons'] as $reason)
                        <li>{{ $reason }}</li>
                    @endforeach
                </ul>
            @endif
            @error('payout_gate')
                <p class="text-sm text-danger mb-3">{{ $message }}</p>
            @enderror
            <div class="flex flex-wrap items-end gap-3">
                @if (! $gate['verified_at'])
                    <form method="POST" action="{{ route('admin.settings.payout-gate') }}" class="flex flex-wrap items-center gap-3">
                        @csrf
                        <input type="hidden" name="action" value="verify_cutover">
                        <label class="flex items-center gap-2 text-sm text-text-primary">
                            <input type="checkbox" name="confirm_report" value="1" class="rounded border-border-default">
                            I reviewed the post-conversion report (<code>php artisan currency:cutover-report</code>)
                        </label>
                        <x-dashboard.button type="submit" variant="secondary">Mark cutover verified</x-dashboard.button>
                    </form>
                @else
                    <p class="text-sm text-text-secondary">Cutover verified {{ $gate['verified_at']->format('M j, Y H:i') }}.</p>
                    <form method="POST" action="{{ route('admin.settings.payout-gate') }}">
                        @csrf
                        <input type="hidden" name="action" value="clear_cutover">
                        <x-dashboard.button type="submit" variant="ghost">Clear verification</x-dashboard.button>
                    </form>
                @endif
                <form method="POST" action="{{ route('admin.settings.payout-gate') }}">
                    @csrf
                    <input type="hidden" name="action" value="{{ $gate['enabled'] ? 'disable' : 'enable' }}">
                    <x-dashboard.button type="submit" :variant="$gate['enabled'] ? 'secondary' : 'primary'">{{ $gate['enabled'] ? 'Switch payouts off' : 'Switch payouts on' }}</x-dashboard.button>
                </form>
            </div>
        </x-dashboard.card>

        <x-dashboard.card variant="solid">
            <h2 class="text-lg font-semibold text-text-primary mb-1">Gateway order</h2>
            <p class="text-sm text-text-secondary mb-4">
                Checkout lists the ticked gateways in this order, and the first one is preselected. Payouts try the first gateway that is verified for the destination in
                @if (Route::has('admin.payout-coverage'))
                    <a href="{{ route('admin.payout-coverage') }}" class="text-accent hover:underline">payout coverage</a>.
                @else
                    payout coverage.
                @endif
                A second gateway is only used when the first one could not create the payout at all.
            </p>
            <form method="POST" action="{{ route('admin.settings.payment-gateways.order') }}" class="grid gap-6 md:grid-cols-2">
                @csrf
                @foreach ([
                    ['collection', 'Checkout and deposits', $orderList($collectionOrder, $collectionCapable), $collectionOrder],
                    ['payout', 'Withdrawals', $orderList($payoutOrder, $payoutCapable), $payoutOrder],
                ] as [$listName, $listLabel, $listKeys, $activeKeys])
                    <div
                        x-data="{ items: @js(collect($listKeys)->map(fn ($k) => ['key' => $k, 'on' => in_array($k, $activeKeys, true)])->values()) , move(i, d) { const j = i + d; if (j < 0 || j >= this.items.length) return; [this.items[i], this.items[j]] = [this.items[j], this.items[i]]; } }"
                    >
                        <p class="text-sm font-medium text-text-primary mb-2">{{ $listLabel }}</p>
                        <ul class="space-y-2">
                            <template x-for="(item, i) in items" :key="item.key">
                                <li class="flex items-center gap-3 rounded-lg border border-border-subtle px-3 py-2">
                                    <input type="checkbox" x-model="item.on" class="rounded border-border-default" :aria-label="'Use ' + item.key">
                                    <span class="flex-1 text-sm text-text-primary" x-text="@js($gatewayNames)[item.key] || item.key"></span>
                                    <button type="button" class="text-xs text-text-muted hover:text-text-primary" @click="move(i, -1)" :disabled="i === 0">Up</button>
                                    <button type="button" class="text-xs text-text-muted hover:text-text-primary" @click="move(i, 1)" :disabled="i === items.length - 1">Down</button>
                                    <template x-if="item.on">
                                        <input type="hidden" name="{{ $listName }}[]" :value="item.key">
                                    </template>
                                </li>
                            </template>
                        </ul>
                    </div>
                @endforeach
                <div class="md:col-span-2">
                    <x-dashboard.button type="submit" variant="primary">Save gateway order</x-dashboard.button>
                </div>
            </form>
        </x-dashboard.card>

        @foreach ($gateways as $key => $driver)
            @php
                $provider = $driver->provider();
                $meta = $provider->meta ?? [];
                $isCurrentForm = old('_gateway') === $key;
                $downUntil = $health->downUntil($key);
                $roles = collect([
                    $driver instanceof \App\Modules\Wallet\Payments\Contracts\CollectionGateway ? 'checkout and deposits' : null,
                    $driver instanceof \App\Modules\Wallet\Payments\Contracts\PayoutGateway ? 'withdrawals' : null,
                ])->filter()->implode(' and ');
            @endphp
            <x-dashboard.card variant="solid">
                <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-lg font-semibold text-text-primary">{{ $driver->displayName() }}</h2>
                        <p class="text-sm text-text-secondary">Used for {{ $roles }}. Credentials are stored encrypted here, not in <code>.env</code>.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if ($driver->isConfigured())
                            <x-dashboard.badge variant="success">Configured</x-dashboard.badge>
                        @elseif ($provider->enabled)
                            <x-dashboard.badge variant="warning">Missing fields</x-dashboard.badge>
                        @else
                            <x-dashboard.badge variant="neutral">Off</x-dashboard.badge>
                        @endif
                        <x-dashboard.badge :variant="$driver->isLive() ? 'danger' : 'info'">{{ $driver->isLive() ? 'Live' : 'Sandbox' }}</x-dashboard.badge>
                        @if ($downUntil)
                            <x-dashboard.badge variant="danger">Paused until {{ \Illuminate\Support\Carbon::createFromTimestamp($downUntil)->format('H:i') }}</x-dashboard.badge>
                        @endif
                    </div>
                </div>

                @error('gateway_test_'.$key)
                    <p class="mb-4 text-sm text-danger">{{ $message }}</p>
                @enderror

                <div class="mb-4 grid gap-3 sm:grid-cols-3 text-sm">
                    <div>
                        <p class="text-text-muted">Connection</p>
                        <p class="mt-1 font-medium text-text-primary capitalize">{{ $provider->status ?: 'idle' }}</p>
                        @if ($provider->last_error && $provider->status === 'error')
                            <p class="mt-1 text-xs text-danger break-words">{{ \Illuminate\Support\Str::limit($provider->last_error, 160) }}</p>
                        @endif
                    </div>
                    <div class="sm:col-span-2">
                        <p class="text-text-muted">Webhook URL</p>
                        <input type="text" readonly value="{{ $driver->webhookUrl() }}" class="mt-1 w-full rounded-lg border border-border-subtle bg-surface-muted px-3 py-2 text-xs text-text-secondary" onclick="this.select()" />
                        <p class="mt-1 text-xs text-text-muted">Add this URL in the {{ $driver->displayName() }} dashboard so payment and payout updates reach the site.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.settings.payment-gateways.update', $key) }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="_gateway" value="{{ $key }}">
                    <input type="hidden" name="enabled" value="0">
                    <x-dashboard.toggle name="enabled" :label="'Enable '.$driver->displayName()" :checked="$isCurrentForm ? old('enabled') : $provider->enabled" value="1" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach ($driver->credentialFields() as $field)
                            @php
                                $fieldName = 'fields['.$field['name'].']';
                                $errorKey = 'fields.'.$field['name'];
                                $store = $field['store'] ?? 'credentials';
                                $stored = $store === 'meta' ? ($meta[$field['name']] ?? null) : $provider->credential($field['name']);
                                $current = $isCurrentForm ? old($errorKey, $stored) : $stored;
                                $label = $field['label'].(($field['required'] ?? false) ? '' : ' (optional)');
                            @endphp
                            @if ($field['type'] === 'secret')
                                <x-dashboard.secret-input :name="$fieldName" :id="$key.'-'.$field['name']" :label="$label" :stored="$stored" :error="$errors->first($errorKey)" :hint="$field['hint'] ?? null" />
                            @elseif ($field['type'] === 'select')
                                <x-dashboard.select :name="$fieldName" :id="$key.'-'.$field['name']" :label="$label" :error="$errors->first($errorKey)" :hint="$field['hint'] ?? null">
                                    @foreach ($field['options'] ?? [] as $value => $optionLabel)
                                        <option value="{{ $value }}" @selected((string) $current === (string) $value)>{{ $optionLabel }}</option>
                                    @endforeach
                                </x-dashboard.select>
                            @else
                                <x-dashboard.input :name="$fieldName" :id="$key.'-'.$field['name']" :label="$label" :value="$current" :error="$errors->first($errorKey)" :hint="$field['hint'] ?? null" autocomplete="off" />
                            @endif
                        @endforeach
                    </div>
                    <x-dashboard.button type="submit" variant="primary">Save {{ $driver->displayName() }} settings</x-dashboard.button>
                </form>

                <form
                    method="POST"
                    action="{{ route('admin.settings.payment-gateways.test', $key) }}"
                    class="mt-4 space-y-2"
                    x-data="{
                        testing: false,
                        status: '',
                        ok: null,
                        async submit(e) {
                            e.preventDefault();
                            if (this.testing) return;
                            this.testing = true;
                            this.status = '';
                            this.ok = null;
                            try {
                                const res = await fetch(e.target.action, {
                                    method: 'POST',
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'X-CSRF-TOKEN': e.target.querySelector('[name=_token]')?.value || '',
                                    },
                                    body: new FormData(e.target),
                                });
                                const data = await res.json().catch(() => ({}));
                                this.ok = !!data.ok;
                                this.status = data.message || (this.ok ? 'Connected' : 'Failed');
                            } catch (err) {
                                this.ok = false;
                                this.status = err?.message || 'Failed';
                            } finally {
                                this.testing = false;
                            }
                        }
                    }"
                    @submit="submit"
                >
                    @csrf
                    <x-dashboard.button type="submit" variant="secondary" x-bind:disabled="testing">
                        <span x-text="testing ? 'Testing…' : 'Test connection'">Test connection</span>
                    </x-dashboard.button>
                    <p class="text-sm break-words" x-show="status" x-text="status" x-cloak :class="ok === true ? 'text-success' : 'text-danger'"></p>
                </form>

                @if ($key === 'bachs' && Route::has('admin.settings.payment-gateways.bachs.reset-connect'))
                    <form
                        method="POST"
                        action="{{ route('admin.settings.payment-gateways.bachs.reset-connect') }}"
                        class="mt-4 border-t border-border-subtle pt-4"
                        onsubmit="return confirm('Clear Connect accounts that were created in the other Bachs environment (e.g. sandbox after switching to live)? Agents keep their profile and local bank accounts, and can reconnect with autofill.');"
                    >
                        @csrf
                        <p class="mb-2 text-sm text-text-secondary">
                            After switching sandbox ↔ live, old Connect rows stay “Connected” locally even though Bachs no longer has them. Clear those so agents can connect again on the current environment.
                        </p>
                        <x-dashboard.button type="submit" variant="secondary" size="sm">Clear Connect accounts from the other environment</x-dashboard.button>
                    </form>
                @endif
            </x-dashboard.card>
        @endforeach

        <x-dashboard.card variant="solid">
            <h2 class="text-lg font-semibold text-text-primary mb-1">Manual bank transfer (order payments)</h2>
            <p class="text-sm text-text-secondary mb-4">
                Let customers pay for platform services by bank or wire transfer in {{ $siteCurrency }}. Use a {{ $siteCurrency }} receiving account. Not used for wallet funding, which goes through a gateway.
            </p>
            @if(empty($manualBankTransferSaveUrl))
                <x-dashboard.alert type="warning">
                    Manual bank transfer save route is not registered. Run <code class="text-xs">php artisan route:clear</code> after deploy, then reload.
                </x-dashboard.alert>
            @else
            <form method="POST" action="{{ $manualBankTransferSaveUrl }}" class="space-y-4">
                @csrf
                <input type="hidden" name="manual_bank_transfer_enabled" value="0">
                <x-dashboard.toggle
                    name="manual_bank_transfer_enabled"
                    label="Enable manual bank transfer at checkout"
                    :checked="old('manual_bank_transfer_enabled', $manualBankTransferEnabled ?? false)"
                    value="1"
                />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-dashboard.input
                        name="manual_bank_transfer_bank_name"
                        label="Bank name"
                        :value="old('manual_bank_transfer_bank_name', $manualBank['bank_name'] ?? '')"
                    />
                    <x-dashboard.input
                        name="manual_bank_transfer_account_name"
                        label="Account name"
                        :value="old('manual_bank_transfer_account_name', $manualBank['account_name'] ?? '')"
                    />
                    <x-dashboard.input
                        name="manual_bank_transfer_account_number"
                        label="Account number"
                        :value="old('manual_bank_transfer_account_number', $manualBank['account_number'] ?? '')"
                    />
                    <x-dashboard.input
                        name="manual_bank_transfer_swift"
                        label="SWIFT/BIC (optional)"
                        :value="old('manual_bank_transfer_swift', $manualBank['swift'] ?? '')"
                    />
                    <x-dashboard.input
                        name="manual_bank_transfer_routing_number"
                        label="US routing number (optional)"
                        :value="old('manual_bank_transfer_routing_number', $manualBank['routing_number'] ?? '')"
                    />
                    <x-dashboard.input
                        name="manual_bank_transfer_iban"
                        label="IBAN (optional)"
                        :value="old('manual_bank_transfer_iban', $manualBank['iban'] ?? '')"
                    />
                    <x-dashboard.input
                        name="manual_bank_transfer_bank_address"
                        label="Bank address (optional)"
                        class="sm:col-span-2"
                        :value="old('manual_bank_transfer_bank_address', $manualBank['bank_address'] ?? '')"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-primary mb-1">Transfer instructions</label>
                    <textarea
                        name="manual_bank_transfer_instructions"
                        rows="4"
                        class="w-full rounded-lg border border-border-subtle bg-surface px-3 py-2 text-sm text-text-primary"
                    >{{ old('manual_bank_transfer_instructions', $manualBank['instructions'] ?? '') }}</textarea>
                    <p class="mt-1 text-xs text-text-muted">Shown on the pending payment page after checkout.</p>
                </div>
                <x-dashboard.button type="submit" variant="primary">Save manual bank transfer settings</x-dashboard.button>
            </form>
            @endif
        </x-dashboard.card>
    </div>
</x-layout.page>
@endsection
