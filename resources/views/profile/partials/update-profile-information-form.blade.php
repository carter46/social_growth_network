@php
    $payoutCountries = collect((array) config('payout_methods.countries', []))
        ->map(fn ($row, $code) => ['code' => $code, 'name' => (string) ($row['name'] ?? $code)])
        ->sortBy('name')
        ->values();
    $showContactFields = \Illuminate\Support\Facades\Schema::hasColumn('users', 'address_line1');
@endphp

<section>
    <header class="mb-6">
        <h2 class="text-lg font-medium text-text-primary">
            {{ __('Profile Information') }}
        </h2>
        <p class="mt-1 text-sm text-text-secondary">
            {{ __("Update your account details, contact info, and address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route($profileUpdateRoute ?? 'profile.update') }}" class="space-y-5" x-data="{ submitting: false }" @submit="submitting = true">
        @csrf
        @method('patch')

        <x-dashboard.input
            label="{{ __('Name') }}"
            name="name"
            type="text"
            id="name"
            :value="old('name', $user->name)"
            required
            autofocus
            autocomplete="name"
            :readonly="$user->nameLockedByPayoutAccount()"
            :hint="$user->nameLockedByPayoutAccount() ? __('Locked because your payout accounts are in this name. Contact support to change it.') : null"
        />

        @if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'date_of_birth'))
            <x-dashboard.input
                label="{{ __('Date of birth') }}"
                name="date_of_birth"
                type="date"
                id="date_of_birth"
                :value="old('date_of_birth', optional($user->date_of_birth)->format('Y-m-d'))"
                required
                autocomplete="bday"
                :max="now()->subYears(18)->toDateString()"
                min="1900-01-01"
                :hint="__('You must be at least 18 years old.')"
            />
        @endif

        <x-dashboard.input
            label="{{ __('Username') }}"
            name="username"
            type="text"
            id="username"
            :value="old('username', $user->username)"
            required
            autocomplete="username"
        />

        <div class="space-y-2">
            <x-dashboard.input
                label="{{ __('Email') }}"
                name="email"
                type="email"
                id="email"
                :value="old('email', $user->email)"
                required
                autocomplete="username"
            />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <x-dashboard.alert type="warning">
                    {{ __('Your email address is unverified.') }}
                    <button form="send-verification" type="submit" class="underline font-medium ml-1">
                        {{ __('Click here to re-send the verification email.') }}
                    </button>
                </x-dashboard.alert>
            @endif
        </div>

        @if ($showContactFields)
            <div class="space-y-3 rounded-xl border border-border-subtle p-4">
                <div>
                    <p class="text-sm font-semibold text-text-primary">{{ __('Contact and address') }}</p>
                </div>
                <x-forms.country-phone
                    :countries="$payoutCountries"
                    :country="old('country', $user->country)"
                    :phone="old('phone', $user->phone)"
                    country-id="profile-country"
                    phone-id="profile-phone"
                    :required="false"
                />
                <x-dashboard.input
                    label="{{ __('Address') }}"
                    name="address_line1"
                    id="profile-address_line1"
                    :value="old('address_line1', $user->address_line1)"
                    autocomplete="address-line1"
                />
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-dashboard.input
                        label="{{ __('City') }}"
                        name="address_city"
                        id="profile-address_city"
                        :value="old('address_city', $user->address_city)"
                        autocomplete="address-level2"
                    />
                    <x-dashboard.input
                        label="{{ __('State / region') }}"
                        name="address_state"
                        id="profile-address_state"
                        :value="old('address_state', $user->address_state)"
                        autocomplete="address-level1"
                    />
                </div>
                <x-dashboard.input
                    label="{{ __('Postal code (optional)') }}"
                    name="address_postal_code"
                    id="profile-address_postal_code"
                    :value="old('address_postal_code', $user->address_postal_code)"
                    autocomplete="postal-code"
                />
            </div>
        @elseif (\Illuminate\Support\Facades\Schema::hasColumn('users', 'phone'))
            <x-dashboard.input
                label="{{ __('Phone') }}"
                name="phone"
                type="tel"
                id="phone"
                :value="old('phone', $user->phone)"
                autocomplete="tel"
            />
        @endif

        <x-dashboard.button type="submit" x-bind:loading="submitting">{{ __('Save') }}</x-dashboard.button>
    </form>
</section>
