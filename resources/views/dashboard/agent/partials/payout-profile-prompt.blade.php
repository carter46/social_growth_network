@php
    $agent = auth()->user();
    $needsProfile = $agent && $agent->hasRole('agent') && ! $agent->hasCompletePayoutProfile();
    $payoutCountries = collect((array) config('payout_methods.countries', []))
        ->map(fn ($row, $code) => ['code' => $code, 'name' => (string) ($row['name'] ?? $code)])
        ->sortBy('name')
        ->values();
@endphp

@if ($needsProfile)
    <div
        x-data="{ open: true }"
        x-show="open"
        x-cloak
        class="fixed inset-0 z-[80] flex items-end justify-center overflow-y-auto overscroll-contain bg-black/40 p-4 sm:items-center"
        role="dialog"
        aria-modal="true"
        aria-labelledby="payout-profile-title"
    >
        <div
            class="my-auto flex w-full max-w-lg max-h-[min(92dvh,40rem)] flex-col overflow-hidden rounded-2xl border border-border-subtle bg-surface shadow-xl"
            @click.outside="open = false"
        >
            <div class="flex shrink-0 items-start justify-between gap-3 border-b border-border-subtle px-5 py-4">
                <div>
                    <h2 id="payout-profile-title" class="text-base font-semibold text-text-primary">Complete your payout profile</h2>
                    <p class="mt-1 text-sm text-text-secondary">Add your phone, country, and address so we can set up withdrawals for your country.</p>
                </div>
                <button type="button" class="shrink-0 text-sm text-text-muted hover:text-text-primary" @click="open = false" aria-label="Close">Close</button>
            </div>

            <form method="POST" action="{{ route('agent.payout-profile.update') }}" class="flex min-h-0 flex-1 flex-col">
                @csrf
                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-contain px-5 py-4">
                    <x-forms.country-phone
                        :countries="$payoutCountries"
                        :country="old('country', $agent->country)"
                        :phone="old('phone', $agent->phone)"
                        country-id="pp-country"
                        phone-id="pp-phone"
                    />
                    <x-dashboard.input name="address_line1" id="pp-line1" label="Address" :value="old('address_line1', $agent->address_line1)" required />
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-dashboard.input name="address_city" id="pp-city" label="City" :value="old('address_city', $agent->address_city)" required />
                        <x-dashboard.input name="address_state" id="pp-state" label="State / region" :value="old('address_state', $agent->address_state)" required />
                    </div>
                    <x-dashboard.input name="address_postal_code" id="pp-postal" label="Postal code (optional)" :value="old('address_postal_code', $agent->address_postal_code)" />
                    @if ($errors->any())
                        <p class="text-sm text-danger">{{ $errors->first() }}</p>
                    @endif
                </div>
                <div class="flex shrink-0 flex-wrap gap-2 border-t border-border-subtle px-5 py-3">
                    <x-dashboard.button type="submit" size="sm">Save profile</x-dashboard.button>
                    <x-dashboard.button type="button" size="sm" variant="secondary" @click="open = false">Not now</x-dashboard.button>
                </div>
            </form>
        </div>
    </div>
@endif
