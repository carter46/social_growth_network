<x-layouts.auth>
    <main class="w-full max-w-auth mx-auto">
        <div class="text-center mb-8">
            <a href="{{ route('home') }}" class="inline-flex flex-col items-center gap-3 group">
                @if(!empty($footer?->logoDarkUrl))
                    <img src="{{ $footer->logoDarkUrl }}" alt="{{ $siteName ?? config('app.name') }}" class="h-12 w-auto max-w-[200px] object-contain">
                    <span class="sr-only">{{ $siteName ?? config('app.name') }}</span>
                @else
                    <h1 class="text-3xl font-bold text-white tracking-tight group-hover:text-accent transition-colors">{{ $siteName ?? config('app.name') }}</h1>
                @endif
            </a>
            <p class="text-text-secondary mt-2">{{ $siteTagline ?? 'Instagram, TikTok, YouTube, Twitter/X, and Facebook growth packs — clear deliverables, secure payment.' }}</p>
        </div>

        <x-ui.card class="p-8">
            <header class="mb-6">
                <h2 class="text-2xl font-semibold text-text-primary">Join the Hub</h2>
                <p class="text-text-secondary text-sm">Get started with your free account today.</p>
            </header>

            <form method="POST" action="{{ route('register') }}" class="space-y-4" x-data="{ submitting: false }" @submit="submitting = true">
                @csrf

                <x-ui.input label="Name" name="name" type="text" id="name" :value="old('name')" required autofocus autocomplete="name" />
                <x-ui.input label="Email" name="email" type="email" id="email" :value="old('email')" required autocomplete="username" />
                <x-ui.input label="Password" name="password" type="password" id="password" required autocomplete="new-password" />
                <x-ui.input label="Confirm Password" name="password_confirmation" type="password" id="password_confirmation" required autocomplete="new-password" />

                <div class="flex items-center justify-between gap-4 pt-2">
                    <a class="text-sm text-text-secondary hover:text-accent transition-colors" href="{{ route('login') }}">
                        Already registered?
                    </a>
                    <x-ui.button type="submit" x-bind:loading="submitting">Register</x-ui.button>
                </div>
            </form>
            @include('partials.auth.google-gis', ['mode' => 'button', 'surface' => 'register', 'buttonText' => 'signup_with'])
            @include('partials.auth.google-gis', ['mode' => 'one_tap', 'surface' => 'register'])
        </x-ui.card>
    </main>
</x-layouts.auth>
