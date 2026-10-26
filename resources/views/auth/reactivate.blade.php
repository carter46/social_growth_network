@php
    $forAgents = (bool) ($forAgents ?? false);
@endphp
<x-layouts.auth
    :title="'Activate account | '.($siteName ?? config('app.name'))"
    :panel-image="$forAgents ? asset('assets/images/about-agents.jpg') : asset('assets/images/creators-hero.jpg')"
    panel-headline="Activate your account."
    panel-copy="Inactive accounts can be turned back on with a code sent to your registered email."
>
    <div class="mb-6 hidden lg:block">
        <a href="{{ $forAgents ? route('agent-login') : route('login') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 hover:text-primary transition-colors">
            <span aria-hidden="true">←</span>
            Back to login
        </a>
    </div>

    <header class="mb-7">
        <h2 class="font-display text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">Activate account</h2>
        <p class="mt-2 text-sm sm:text-base text-slate-500">Enter the email on your inactive account. We’ll send a one-time code if it matches.</p>
    </header>

    @if (session('status'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('account.reactivate.send') }}" class="space-y-5">
        @csrf
        @if ($forAgents)
            <input type="hidden" name="agent" value="1">
        @endif
        <x-ui.input
            label="Email"
            name="email"
            type="email"
            id="reactivate-email"
            placeholder="you@example.com"
            :value="old('email', $email ?? '')"
            required
            autofocus
            autocomplete="email"
            class="!bg-slate-50 !border-slate-200"
        />
        <x-ui.button type="submit" class="w-full" size="lg">
            Send activation code
        </x-ui.button>
    </form>
</x-layouts.auth>
