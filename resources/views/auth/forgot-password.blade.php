<x-layouts.auth>
    <main class="w-full max-w-auth mx-auto space-y-8">
        <div class="flex flex-col gap-4 text-center">
            <div class="inline-flex items-center justify-center text-primary">
                <x-ui.icon name="lock" class="w-12 h-12" />
            </div>
            <div class="space-y-2">
                <h1 class="text-text-primary text-3xl font-bold tracking-tight">Forgot Password</h1>
                <p class="text-text-secondary leading-relaxed">
                    Enter your email to receive a password reset link. We'll help you get back into your account.
                </p>
            </div>
        </div>

        @if ($errors->any())
            <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
        @endif

        <x-ui.card class="p-8">
            <form class="space-y-6" action="{{ route('password.email') }}" method="POST" x-data="{ submitting: false }" @submit="submitting = true">
                @csrf
                <x-ui.input
                    label="Email Address"
                    name="email"
                    type="email"
                    id="email"
                    placeholder="name@company.com"
                    :value="old('email')"
                    required
                    autofocus
                />
                <x-ui.button type="submit" class="w-full" size="lg" icon-right="chevron-right" x-bind:loading="submitting">
                    Send Reset Link
                </x-ui.button>
            </form>
        </x-ui.card>

        <div class="text-center">
            <a class="inline-flex items-center gap-2 text-accent hover:text-primary font-semibold transition-colors group" href="{{ route('login') }}">
                <x-ui.icon name="chevron-right" class="w-4 h-4 rotate-180 group-hover:-translate-x-1 transition-transform" />
                <span>Back to Login</span>
            </a>
        </div>
        <div class="pt-4 flex items-center justify-center gap-2 text-text-muted text-xs font-medium uppercase tracking-widest">
            <x-ui.icon name="verified" class="w-4 h-4" />
            <span>Secured encryption for your account</span>
        </div>
    </main>
</x-layouts.auth>
