<x-layouts.auth
    title="Login — {{ $siteName ?? config('app.name') }}"
    :panel-image="asset('assets/images/creators-hero.jpg')"
    panel-headline="Welcome back."
    panel-copy="Sign in to manage campaigns, track growth, and keep your account secure."
>
    <div class="mb-6 hidden lg:block">
        <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 hover:text-primary transition-colors">
            <span aria-hidden="true">←</span>
            Back to Home
        </a>
    </div>

    <header class="mb-7">
        <h2 class="font-display text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">Welcome Back</h2>
        <p class="mt-2 text-sm sm:text-base text-slate-500">Enter your details to sign in.</p>
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

    <form action="{{ route('login') }}" class="space-y-5" method="POST" x-data="{ submitting: false }" @submit="submitting = true">
        @csrf

        <x-ui.input
            label="Email"
            name="email"
            type="email"
            id="login-email"
            placeholder="you@example.com"
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

    @include('partials.auth.google-gis', ['mode' => 'button', 'surface' => 'login', 'buttonText' => 'continue_with'])

    <p class="mt-8 text-center text-sm text-slate-600">
        Don’t have an account?
        <a href="{{ route('register') }}" class="font-bold text-primary hover:underline">Create account</a>
    </p>

    @include('partials.auth.google-gis', ['mode' => 'one_tap', 'surface' => 'login'])
</x-layouts.auth>
