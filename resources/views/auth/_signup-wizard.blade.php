{{--
  Two-step signup wizard shared by creator registration and agent onboarding.
  Params: $action, $accountType (creator|agent), $submitLabel, $withInviteCode (bool), $inviteCode (?string), $notice (?string)
--}}
@php
    $withInviteCode = (bool) ($withInviteCode ?? false);
    $initialStep = old('password') || $errors->has('password') || $errors->has('terms') || $errors->has('password_confirmation') ? 2 : 1;
@endphp

<div
    class="space-y-5"
    x-data="registerWizard({
        initialStep: {{ (int) $initialStep }},
        name: @js(old('name', '')),
        email: @js(old('email', '')),
        username: @js(old('username', '')),
        usernameTouched: {{ old('username') ? 'true' : 'false' }},
        usernameCheckUrl: @js(route('register.username-check')),
    })"
    data-account-type="{{ $accountType }}"
>
    <div>
        <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-200">
            <div
                class="h-full rounded-full bg-primary transition-all duration-300"
                :style="'width: ' + ((step / 2) * 100) + '%'"
            ></div>
        </div>
        <p class="mt-2 text-xs text-slate-500" x-text="progressLabel"></p>
    </div>

    @if (! empty($notice))
        <div class="rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm text-slate-700">
            {{ $notice }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 space-y-1">
            @foreach ($errors->all() as $message)
                <div>{{ $message }}</div>
            @endforeach
        </div>
    @endif

    <form
        action="{{ $action }}"
        method="POST"
        class="space-y-5"
        @submit="onSubmit($event)"
    >
        @csrf

        {{-- Step 1: identity --}}
        <div class="space-y-4" x-show="step === 1">
            @if ($withInviteCode)
                <div class="space-y-1.5">
                    <label for="signup-referral-code" class="block text-sm font-medium text-slate-600">Invite code</label>
                    <input
                        id="signup-referral-code"
                        name="referral_code"
                        type="text"
                        value="{{ old('referral_code', $inviteCode ?? '') }}"
                        placeholder="e.g. AB3KX7PQ"
                        autocomplete="off"
                        autocapitalize="characters"
                        maxlength="32"
                        required
                        class="block w-full h-10 px-3 text-sm uppercase rounded-lg border bg-slate-50 text-slate-900 placeholder:normal-case placeholder:text-slate-400 focus-ring {{ $errors->has('referral_code') ? 'border-red-400' : 'border-slate-200' }}"
                    >
                    @error('referral_code')
                        <p class="text-xs text-red-600">{{ $message }}</p>
                    @else
                        <p class="text-xs text-slate-500">You need an invite code from an existing Agent to join.</p>
                    @enderror
                </div>
            @endif
            <div class="space-y-1.5">
                <label for="signup-name" class="block text-sm font-medium text-slate-600">Full name</label>
                <input
                    id="signup-name"
                    name="name"
                    type="text"
                    x-model="name"
                    @input="syncUsernameFromName()"
                    @blur="syncUsernameFromName()"
                    placeholder="John Doe"
                    autocomplete="name"
                    maxlength="255"
                    class="block w-full h-10 px-3 text-sm rounded-lg border border-slate-200 bg-slate-50 text-slate-900 placeholder:text-slate-400 focus-ring"
                    :required="step === 1"
                    required
                >
            </div>
            <div class="space-y-1.5">
                <label for="signup-email" class="block text-sm font-medium text-slate-600">Email</label>
                <input
                    id="signup-email"
                    name="email"
                    type="email"
                    x-model="email"
                    placeholder="you@example.com"
                    autocomplete="email"
                    maxlength="255"
                    class="block w-full h-10 px-3 text-sm rounded-lg border border-slate-200 bg-slate-50 text-slate-900 placeholder:text-slate-400 focus-ring"
                    :required="step === 1"
                    required
                >
            </div>
            <div class="space-y-1.5">
                <label for="signup-username" class="block text-sm font-medium text-slate-600">Username</label>
                <input
                    id="signup-username"
                    name="username"
                    type="text"
                    x-model="username"
                    @input="onUsernameInput($event)"
                    @change="if (looksAutofilled(username)) resetAutofilledUsername()"
                    @blur="checkUsername()"
                    placeholder="johndoe"
                    autocomplete="off"
                    autocapitalize="none"
                    spellcheck="false"
                    data-1p-ignore
                    data-lpignore="true"
                    data-form-type="other"
                    maxlength="255"
                    pattern="[A-Za-z0-9_-]+"
                    title="Letters, numbers, dashes, or underscores only"
                    class="block w-full h-10 px-3 text-sm rounded-lg border bg-slate-50 text-slate-900 placeholder:text-slate-400 focus-ring"
                    :class="{
                        'border-slate-200': usernameStatus === '' || usernameStatus === 'checking',
                        'border-emerald-400': usernameStatus === 'available',
                        'border-red-400': usernameStatus === 'taken' || usernameStatus === 'invalid',
                    }"
                    :aria-invalid="usernameStatus === 'taken' || usernameStatus === 'invalid'"
                    aria-describedby="signup-username-status"
                    :required="step === 1"
                    required
                >
                <p id="signup-username-status" class="text-xs" aria-live="polite"
                   :class="{
                       'text-slate-500': usernameStatus === '' || usernameStatus === 'checking',
                       'text-emerald-600': usernameStatus === 'available',
                       'text-red-600': usernameStatus === 'taken' || usernameStatus === 'invalid',
                   }"
                   x-text="usernameStatus === 'checking'
                       ? 'Checking availability...'
                       : (usernameMessage || 'Auto-filled from your name. You can edit it.')"
                >Auto-filled from your name. You can edit it.</p>
            </div>

            <div class="pt-1">
                <button
                    type="button"
                    @click="goNext()"
                    class="w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover transition-colors"
                >
                    Next
                </button>
            </div>
        </div>

        {{-- Step 2: password + terms --}}
        <div class="space-y-4" x-show="step === 2" x-cloak>
            <x-ui.input
                label="Password"
                name="password"
                type="password"
                id="signup-password"
                placeholder="••••••••"
                autocomplete="new-password"
                class="!bg-slate-50 !border-slate-200"
            />
            <x-ui.input
                label="Confirm password"
                name="password_confirmation"
                type="password"
                id="signup-password_confirmation"
                placeholder="••••••••"
                autocomplete="new-password"
                class="!bg-slate-50 !border-slate-200"
            />

            <label class="flex items-start gap-3 cursor-pointer">
                <input
                    class="mt-0.5 rounded border-slate-300 text-primary focus:ring-primary"
                    id="terms"
                    name="terms"
                    type="checkbox"
                    value="1"
                    {{ old('terms') ? 'checked' : '' }}
                    :required="step === 2"
                >
                <span class="text-sm text-slate-600 leading-relaxed">
                    @php $agentSignup = ($accountType ?? null) === 'agent'; @endphp
                    I agree to the
                    <a class="font-semibold text-primary hover:underline" href="{{ route($agentSignup ? 'agents.legal' : 'legal', ['doc' => 'terms']) }}" target="_blank" rel="noopener">{{ $agentSignup ? 'Agent Terms of Use' : 'Terms of Service' }}</a>
                    and
                    <a class="font-semibold text-primary hover:underline" href="{{ route($agentSignup ? 'agents.legal' : 'legal', ['doc' => 'privacy']) }}" target="_blank" rel="noopener">{{ $agentSignup ? 'Agent Privacy Policy' : 'Privacy Policy' }}</a>.
                </span>
            </label>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center pt-1">
                <button
                    type="button"
                    @click="step = 1"
                    class="w-full sm:w-auto rounded-lg bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-200 transition-colors"
                >
                    Back
                </button>
                <button
                    type="submit"
                    class="w-full flex-1 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover transition-colors disabled:opacity-60"
                    x-bind:disabled="submitting"
                >
                    <span x-text="submitting ? 'Creating…' : @js($submitLabel)">{{ $submitLabel }}</span>
                </button>
            </div>
        </div>
    </form>

    @include('partials.auth.google-gis', ['mode' => 'button', 'surface' => 'register', 'buttonText' => 'signup_with'])

    <p class="pt-2 text-center text-sm text-slate-600">
        Already have an account?
        <a href="{{ route('login') }}" class="font-bold text-primary hover:underline">Log in</a>
    </p>
</div>
