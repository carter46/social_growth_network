<x-layouts.auth
    title="Forgot password — {{ $siteName ?? config('app.name') }}"
    :panel-image="asset('assets/images/campaign-workspace.jpg')"
    panel-headline="Reset securely."
    panel-copy="We’ll email you a link so you can choose a new password and get back to work."
>
    <div class="mb-6 hidden lg:block">
        <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 hover:text-primary transition-colors">
            <span aria-hidden="true">←</span>
            Back to Login
        </a>
    </div>

    <header class="mb-7">
        <h2 class="font-display text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">Forgot password</h2>
        <p class="mt-2 text-sm sm:text-base text-slate-500">
            Enter your email and we’ll send a reset link.
        </p>
    </header>

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif
    @if (session('status'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    <form class="space-y-5" action="{{ route('password.email') }}" method="POST" x-data="{ submitting: false }" @submit="submitting = true">
        @csrf
        <x-ui.input
            label="Email address"
            name="email"
            type="email"
            id="email"
            placeholder="you@example.com"
            :value="old('email')"
            required
            autofocus
            class="!bg-slate-50 !border-slate-200"
        />
        <x-ui.button type="submit" class="w-full" size="lg" x-bind:loading="submitting">
            Send reset link
        </x-ui.button>
    </form>

    <p class="mt-8 text-center text-sm text-slate-600">
        <a class="font-bold text-primary hover:underline" href="{{ route('login') }}">Back to login</a>
    </p>
</x-layouts.auth>
