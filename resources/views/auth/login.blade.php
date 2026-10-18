@php
    $forAgents = (bool) ($forAgents ?? false);
@endphp
<x-layouts.auth
    :title="($forAgents ? 'Agent login' : 'Login').' | '.($siteName ?? config('app.name'))"
    :panel-image="$forAgents ? asset('assets/images/about-agents.jpg') : asset('assets/images/creators-hero.jpg')"
    :panel-headline="$forAgents ? 'Welcome back, Agent.' : 'Welcome back.'"
    :panel-copy="$forAgents
        ? 'Sign in to find open YouTube tasks, submit your work and track your rewards.'
        : 'Sign in to manage campaigns, track growth, and keep your account secure.'"
>
    <div class="mb-6 hidden lg:block">
        <a href="{{ $forAgents ? route('agents') : route('home') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 hover:text-primary transition-colors">
            <span aria-hidden="true">←</span>
            {{ $forAgents ? 'Back to For Agents' : 'Back to Home' }}
        </a>
    </div>

    <header class="mb-7">
        <h2 class="font-display text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">{{ $forAgents ? 'Agent Login' : 'Welcome Back' }}</h2>
        <p class="mt-2 text-sm sm:text-base text-slate-500">Enter your details to sign in.</p>
    </header>

    @if (session('status'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @elseif (! $forAgents && app(\App\Services\Catalog\PurchaseDraftService::class)->has())
        <div class="mb-5 rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm text-slate-700">
            Your package is saved. Log in with your Creator account, or <a href="{{ route('register') }}" class="font-semibold text-primary hover:underline">sign up as a Creator</a>, to finish your order.
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @elseif (session('error'))
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert" data-login-error>
            {{ session('error') }}
        </div>
    @endif

    <form action="{{ route('login') }}" class="space-y-5" method="POST" x-data="{ submitting: false }" @submit="submitting = true">
        @csrf

        <x-ui.input
            label="Email or username"
            name="email"
            type="text"
            id="login-email"
            placeholder="you@example.com or username"
            autocapitalize="none"
            spellcheck="false"
            :value="old('email')"
            required
            autofocus
            autocomplete="username"
            class="!bg-slate-50 !border-slate-200"
        />

        <x-ui.input
            label="Password"
            name="password"
            type="password"
            id="login-password"
            placeholder="••••••••"
            required
            autocomplete="current-password"
            class="!bg-slate-50 !border-slate-200"
        />

        <div class="space-y-3 text-sm">
            <label class="flex items-center cursor-pointer group">
                <input
                    class="rounded border-slate-300 bg-white text-primary focus:ring-primary focus:ring-offset-0"
                    name="remember"
                    type="checkbox"
                >
                <span class="ml-2 text-slate-600 group-hover:text-slate-900 transition-colors">Remember me</span>
            </label>
            <a class="inline-block font-semibold text-primary hover:text-primary/80 transition-colors" href="{{ route('password.request') }}">
                Forgot password?
            </a>
        </div>

        <x-ui.button type="submit" class="w-full" size="lg" x-bind:loading="submitting">
            Login
        </x-ui.button>
    </form>

    @if ($forAgents)
        {{-- New Google accounts here are treated as Agent signups, so they still need an invite code. --}}
        <div data-account-type="agent">
            @include('partials.auth.google-gis', ['mode' => 'button', 'surface' => 'login', 'buttonText' => 'continue_with'])
        </div>

        <p class="mt-8 text-center text-sm text-slate-600">
            New Agent?
            <a href="{{ route('agent-onboarding') }}" class="font-bold text-primary hover:underline">Join as an Agent</a>
        </p>
    @else
        @include('partials.auth.google-gis', ['mode' => 'button', 'surface' => 'login', 'buttonText' => 'continue_with'])

        <p class="mt-8 text-center text-sm text-slate-600">
            Don’t have an account?
            <a href="{{ route('register') }}" class="font-bold text-primary hover:underline">Create account</a>
        </p>

        @include('partials.auth.google-gis', ['mode' => 'one_tap', 'surface' => 'login'])
    @endif
</x-layouts.auth>
