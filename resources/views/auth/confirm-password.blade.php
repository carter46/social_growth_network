<x-layouts.auth
    title="Confirm password — {{ $siteName ?? config('app.name') }}"
    :panel-image="asset('assets/images/campaign-workspace.jpg')"
    panel-headline="Confirm it’s you."
    panel-copy="This is a secure area. Enter your password to continue."
>
    <header class="mb-7">
        <h2 class="font-display text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">Confirm password</h2>
        <p class="mt-2 text-sm sm:text-base text-slate-500">
            Please confirm your password before continuing.
        </p>
    </header>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5" x-data="{ submitting: false }" @submit="submitting = true">
        @csrf

        <x-ui.input
            label="Password"
            name="password"
            type="password"
            id="password"
            required
            autocomplete="current-password"
            autofocus
            class="!bg-slate-50 !border-slate-200"
        />

        <x-ui.button type="submit" class="w-full" size="lg" x-bind:loading="submitting">Confirm</x-ui.button>
    </form>
</x-layouts.auth>
