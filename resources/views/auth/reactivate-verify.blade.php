@php
    $forAgents = (bool) ($forAgents ?? false);
@endphp
<x-layouts.auth
    :title="'Enter activation code | '.($siteName ?? config('app.name'))"
    :panel-image="$forAgents ? asset('assets/images/about-agents.jpg') : asset('assets/images/creators-hero.jpg')"
    panel-headline="Check your email."
    panel-copy="Enter the 6-digit code we sent to activate your account."
>
    <div class="mb-6 hidden lg:block">
        <a href="{{ route('account.reactivate', array_filter(['email' => $email, 'agent' => $forAgents ? 1 : null])) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 hover:text-primary transition-colors">
            <span aria-hidden="true">←</span>
            Back
        </a>
    </div>

    <header class="mb-7">
        <h2 class="font-display text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">Enter code</h2>
        <p class="mt-2 text-sm sm:text-base text-slate-500">Sent to {{ $email }}</p>
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

    <form method="POST" action="{{ route('account.reactivate.verify.store') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="email" value="{{ $email }}">
        @if ($forAgents)
            <input type="hidden" name="agent" value="1">
        @endif
        <x-ui.input
            label="Activation code"
            name="otp"
            type="text"
            id="reactivate-otp"
            placeholder="123456"
            inputmode="numeric"
            autocomplete="one-time-code"
            maxlength="6"
            required
            autofocus
            class="!bg-slate-50 !border-slate-200 tracking-widest"
        />
        <x-ui.button type="submit" class="w-full" size="lg">
            Activate and continue
        </x-ui.button>
    </form>

    <form method="POST" action="{{ route('account.reactivate.send') }}" class="mt-4 text-center">
        @csrf
        <input type="hidden" name="email" value="{{ $email }}">
        @if ($forAgents)
            <input type="hidden" name="agent" value="1">
        @endif
        <button type="submit" class="text-sm font-semibold text-primary hover:underline">
            Resend code
        </button>
    </form>
</x-layouts.auth>
