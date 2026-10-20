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
        class="fixed inset-0 z-[80] flex items-end justify-center bg-black/40 p-4 sm:items-center"
        role="dialog"
        aria-modal="true"
        aria-labelledby="payout-profile-title"
    >
        <div class="w-full max-w-lg rounded-2xl bg-surface p-5 shadow-xl border border-border-subtle" @click.outside="open = false">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 id="payout-profile-title" class="text-base font-semibold text-text-primary">Complete your payout profile</h2>
                    <p class="mt-1 text-sm text-text-secondary">Add your phone, country, and address so we can set up withdrawals for your country.</p>
                </div>
                <button type="button" class="text-sm text-text-muted hover:text-text-primary" @click="open = false" aria-label="Close">Close</button>
            </div>

            <form method="POST" action="{{ route('agent.payout-profile.update') }}" class="mt-4 space-y-3">
                @csrf
                <div>
                    <label class="mb-1 block text-sm font-medium" for="pp-country">Country</label>
                    <select id="pp-country" name="country" required class="w-full rounded-lg border border-border-subtle px-3 py-2 text-sm">
                        <option value="">Choose country</option>
                        @foreach ($payoutCountries as $c)
                            <option value="{{ $c['code'] }}" @selected(old('country', $agent->country) === $c['code'])>{{ $c['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <x-dashboard.input name="phone" id="pp-phone" label="Phone (with country code)" :value="old('phone', $agent->phone)" placeholder="+15551234567" required />
                <x-dashboard.input name="address_line1" id="pp-line1" label="Address" :value="old('address_line1', $agent->address_line1)" required />
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-dashboard.input name="address_city" id="pp-city" label="City" :value="old('address_city', $agent->address_city)" required />
                    <x-dashboard.input name="address_state" id="pp-state" label="State / region" :value="old('address_state', $agent->address_state)" required />
                </div>
                <x-dashboard.input name="address_postal_code" id="pp-postal" label="Postal code (optional)" :value="old('address_postal_code', $agent->address_postal_code)" />
                @if ($errors->any())
                    <p class="text-sm text-danger">{{ $errors->first() }}</p>
                @endif
                <div class="flex flex-wrap gap-2 pt-1">
                    <x-dashboard.button type="submit" size="sm">Save profile</x-dashboard.button>
                    <x-dashboard.button type="button" size="sm" variant="secondary" @click="open = false">Not now</x-dashboard.button>
                </div>
            </form>
        </div>
    </div>
@endif
