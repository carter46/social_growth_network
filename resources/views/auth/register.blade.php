@php
    $preferAgent = (bool) ($registerAsAgent ?? false) || request()->query('as') === 'agent';
    $initialType = old('account_type', $preferAgent ? 'agent' : '');
    $hasErrors = $errors->any();
    $initialStep = 1;
    if ($hasErrors || old('name') || old('email') || old('username')) {
        $initialStep = old('password') || $errors->has('password') || $errors->has('terms') || $errors->has('password_confirmation')
            ? 3
            : 2;
    } elseif ($preferAgent && $initialType === 'agent') {
        // Agent invite link: skip role pick, land on details.
        $initialStep = 2;
    }
    if ($initialType === '' && $initialStep > 1) {
        $initialType = 'creator';
    }
@endphp

<x-layouts.auth
    title="Create account — {{ $siteName ?? config('app.name') }}"
    :panel-image="asset('assets/images/about-creators.jpg')"
    panel-headline="Join as a Creator or Agent."
    panel-copy="Buy growth campaigns or complete paid tasks — pick your path and get started in a few steps."
>
    <div class="mb-6 hidden lg:block">
        <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 hover:text-primary transition-colors">
            <span aria-hidden="true">←</span>
            Back to Home
        </a>
    </div>

    <header class="mb-6">
        <h2 class="font-display text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">Create your account</h2>
        <p class="mt-2 text-sm sm:text-base text-slate-500">A few short steps — then you’re in.</p>
    </header>

    <div
        class="space-y-5"
        x-data="registerWizard({
            initialStep: {{ (int) $initialStep }},
            accountType: @js($initialType),
            name: @js(old('name', '')),
            email: @js(old('email', '')),
            username: @js(old('username', '')),
            usernameTouched: {{ old('username') ? 'true' : 'false' }},
        })"
        :data-account-type="accountType"
    >
        {{-- Progress --}}
        <div>
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-200">
                <div
                    class="h-full rounded-full bg-primary transition-all duration-300"
                    :style="'width: ' + ((step / 3) * 100) + '%'"
                ></div>
            </div>
            <p class="mt-2 text-xs text-slate-500" x-text="progressLabel"></p>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 space-y-1">
                @foreach ($errors->all() as $message)
                    <div>{{ $message }}</div>
                @endforeach
            </div>
        @endif

        <form
            action="{{ route('register') }}"
            method="POST"
            class="space-y-5"
            @submit="onSubmit($event)"
        >
            @csrf
            <input type="hidden" name="account_type" :value="accountType">

            {{-- Step 1: role (visible without JS; cloaked steps need Alpine) --}}
            <div class="space-y-4" x-show="step === 1">
                <fieldset>
                    <legend class="text-sm font-semibold text-slate-800 mb-3">I want to join as</legend>
                    <div class="grid grid-cols-1 gap-3">
                        <button
                            type="button"
                            @click="selectRole('creator')"
                            class="rounded-xl border p-4 text-left transition-colors"
                            :class="accountType === 'creator'
                                ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                : 'border-slate-200 bg-slate-50 hover:border-primary/40'"
                        >
                            <span class="block text-sm font-semibold text-slate-900">Creator</span>
                            <span class="mt-1 block text-xs leading-relaxed text-slate-600">
                                Buy campaign packages to grow your profiles — likes, comments, views, and watch sessions delivered by agents.
                            </span>
                        </button>
                        <button
                            type="button"
                            @click="selectRole('agent')"
                            class="rounded-xl border p-4 text-left transition-colors"
                            :class="accountType === 'agent'
                                ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                : 'border-slate-200 bg-slate-50 hover:border-primary/40'"
                        >
                            <span class="block text-sm font-semibold text-slate-900">Agent</span>
                            <span class="mt-1 block text-xs leading-relaxed text-slate-600">
                                Complete paid tasks for creators and earn money you can withdraw.
                            </span>
                        </button>
                    </div>
                </fieldset>
                <p class="text-xs text-slate-500">Select a role to continue.</p>
            </div>

            {{-- Step 2: identity --}}
            <div class="space-y-4" x-show="step === 2" x-cloak>
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
                        :required="step === 2"
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
                        :required="step === 2"
                    >
                </div>
                <div class="space-y-1.5">
                    <label for="signup-username" class="block text-sm font-medium text-slate-600">Username</label>
                    <input
                        id="signup-username"
                        name="username"
                        type="text"
                        x-model="username"
                        @input="usernameTouched = true"
                        placeholder="johndoe"
                        autocomplete="username"
                        maxlength="255"
                        pattern="[A-Za-z0-9_-]+"
                        title="Letters, numbers, dashes, or underscores only"
                        class="block w-full h-10 px-3 text-sm rounded-lg border border-slate-200 bg-slate-50 text-slate-900 placeholder:text-slate-400 focus-ring"
                        :required="step === 2"
                    >
                    <p class="text-xs text-slate-500">Auto-filled from your name — you can edit it.</p>
                </div>

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center pt-1">
                    <button
                        type="button"
                        @click="step = 1"
                        class="w-full sm:w-auto rounded-lg bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-200 transition-colors"
                    >
                        Back
                    </button>
                    <button
                        type="button"
                        @click="goNext()"
                        class="w-full flex-1 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover transition-colors"
                    >
                        Next
                    </button>
                </div>
            </div>

            {{-- Step 3: password + terms --}}
            <div class="space-y-4" x-show="step === 3" x-cloak>
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
                        :required="step === 3"
                    >
                    <span class="text-sm text-slate-600 leading-relaxed">
                        I agree to the
                        <a class="font-semibold text-primary hover:underline" href="{{ route('legal', ['doc' => 'terms']) }}">Terms of Service</a>
                        and
                        <a class="font-semibold text-primary hover:underline" href="{{ route('legal', ['doc' => 'privacy']) }}">Privacy Policy</a>.
                    </span>
                </label>

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center pt-1">
                    <button
                        type="button"
                        @click="step = 2"
                        class="w-full sm:w-auto rounded-lg bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-200 transition-colors"
                    >
                        Back
                    </button>
                    <button
                        type="submit"
                        class="w-full flex-1 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover transition-colors disabled:opacity-60"
                        x-bind:disabled="submitting"
                    >
                        <span x-text="submitting
                            ? 'Creating…'
                            : (accountType === 'agent' ? 'Create Agent Account' : 'Create Creator Account')"></span>
                    </button>
                </div>
            </div>
        </form>

        {{-- Google button only after role is chosen; keep inside account-type host --}}
        <div x-show="step >= 2" x-cloak>
            @include('partials.auth.google-gis', ['mode' => 'button', 'surface' => 'register', 'buttonText' => 'signup_with'])
            <p class="mt-2 text-center text-xs text-slate-500" x-text="accountType === 'agent'
                ? 'Google signup will use your Agent selection.'
                : 'Google signup will use your Creator selection.'"></p>
        </div>

        <p class="pt-2 text-center text-sm text-slate-600">
            Already have an account?
            <a href="{{ route('login') }}" class="font-bold text-primary hover:underline">Log in</a>
        </p>
    </div>
</x-layouts.auth>
