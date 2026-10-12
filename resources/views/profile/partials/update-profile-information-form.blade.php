<section>
    <header class="mb-6">
        <h2 class="text-lg font-medium text-text-primary">
            {{ __('Profile Information') }}
        </h2>
        <p class="mt-1 text-sm text-text-secondary">
            {{ __("Update your account's profile information and email address.") }}
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

        @if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'phone'))
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
