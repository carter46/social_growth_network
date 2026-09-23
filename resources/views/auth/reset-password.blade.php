<x-layouts.auth
    title="Reset password — {{ $siteName ?? config('app.name') }}"
    :panel-image="asset('assets/images/campaign-workspace.jpg')"
    panel-headline="Choose a new password."
    panel-copy="Pick something strong and unique — then you’re back in."
>
    <div class="mb-6 hidden lg:block">
        <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 hover:text-primary transition-colors">
            <span aria-hidden="true">←</span>
            Back to Login
        </a>
    </div>

    <header class="mb-7">
        <h2 class="font-display text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">Reset password</h2>
        <p class="mt-2 text-sm sm:text-base text-slate-500">Create a new secure password for your account.</p>
    </header>

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <form class="space-y-5" action="{{ route('password.store') }}" method="POST" x-data="{ submitting: false }" @submit="submitting = true">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-ui.input
            label="Email"
            name="email"
            type="email"
            id="email"
            :value="old('email', $request->email)"
            required
            autofocus
            placeholder="you@example.com"
            class="!bg-slate-50 !border-slate-200"
        />

        <x-ui.input
            label="New password"
            name="password"
            type="password"
            id="password"
            placeholder="Min. 8 characters"
            required
            class="!bg-slate-50 !border-slate-200"
        />

        <x-ui.input
            label="Confirm new password"
            name="password_confirmation"
            type="password"
            id="password_confirmation"
            placeholder="Repeat your new password"
            required
            class="!bg-slate-50 !border-slate-200"
        />

        <x-ui.button type="submit" class="w-full" size="lg" x-bind:loading="submitting">
            Reset password
        </x-ui.button>
    </form>

    <p class="mt-8 text-center text-sm text-slate-600">
        <a class="font-bold text-primary hover:underline" href="{{ route('login') }}">Back to login</a>
    </p>
</x-layouts.auth>
