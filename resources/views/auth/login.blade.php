<x-layouts.auth>
    <main class="w-full max-w-auth mx-auto" data-purpose="auth-wrapper">
        <div class="text-center mb-8" data-purpose="branding">
            <a href="{{ route('home') }}" class="inline-flex flex-col items-center gap-3 group">
                @if(!empty($footer?->logoDarkUrl))
                    <img src="{{ $footer->logoDarkUrl }}" alt="{{ $siteName ?? config('app.name') }}" class="h-12 w-auto max-w-[200px] object-contain">
                    <span class="sr-only">{{ $siteName ?? config('app.name') }}</span>
                @else
                    <h1 class="text-3xl font-bold text-white tracking-tight group-hover:text-accent transition-colors">{{ $siteName ?? config('app.name') }}</h1>
                @endif
            </a>
            <p class="text-text-secondary mt-2">{{ $siteTagline ?? 'Instagram, TikTok, YouTube, Twitter/X, and Facebook growth packs — clear deliverables, secure checkout.' }}</p>
        </div>

        {{-- Login Section --}}
        <section class="auth-transition {{ (request()->get('view') === 'signup' || ($showSignup ?? false)) ? 'hidden-section' : '' }}" data-purpose="login-form-container" id="login-section">
            <x-ui.card class="!p-8 shadow-2xl">
                <header class="mb-8">
                    <h2 class="text-2xl font-semibold text-text-primary">Welcome Back</h2>
                    <p class="text-text-secondary text-sm">Please enter your details to sign in.</p>
                </header>

                <form action="{{ route('login') }}" class="space-y-5" method="POST" x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf
                    <x-ui.input
                        label="Email"
                        name="email"
                        type="email"
                        id="login-email"
                        placeholder="johndoe@example.com"
                        :value="old('email')"
                        required
                        autofocus
                    />
                    <x-ui.input
                        label="Password"
                        name="password"
                        type="password"
                        id="login-password"
                        placeholder="••••••••"
                        required
                    />
                    <div class="flex items-center justify-between text-sm">
                        <label class="flex items-center cursor-pointer group">
                            <input class="rounded border-border-default bg-elevated text-accent focus:ring-accent focus:ring-offset-surface" name="remember" type="checkbox"/>
                            <span class="ml-2 text-text-secondary group-hover:text-text-primary transition-colors">Remember me</span>
                        </label>
                        <a class="text-accent hover:text-primary font-medium transition-colors" href="{{ route('password.request') }}">Forgot Password?</a>
                    </div>
                    <x-ui.button type="submit" class="w-full" size="lg" x-bind:loading="submitting">Login</x-ui.button>
                </form>
                @include('partials.auth.google-gis', ['mode' => 'button', 'surface' => 'login', 'buttonText' => 'continue_with'])
                <footer class="mt-8 pt-6 border-t border-border-default text-center">
                    <p class="text-text-secondary text-sm">
                        Don't have an account?
                        <a href="{{ route('register') }}" class="text-accent hover:text-primary font-semibold transition-colors">Create Account</a>
                    </p>
                </footer>
            </x-ui.card>
        </section>

        {{-- Sign Up Section --}}
        <section class="auth-transition {{ (request()->get('view') === 'signup' || ($showSignup ?? false)) ? '' : 'hidden-section' }}" data-purpose="signup-form-container" id="signup-section">
            <x-ui.card class="!p-8 shadow-2xl">
                <header class="mb-6">
                    <h2 class="text-2xl font-semibold text-text-primary">Join the Hub</h2>
                    <p class="text-text-secondary text-sm">Get started with your free account today.</p>
                </header>
                <form action="{{ route('register') }}" class="space-y-4" method="POST" x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf
                    <x-ui.input
                        label="Full Name"
                        name="name"
                        type="text"
                        id="signup-name"
                        placeholder="John Doe"
                        :value="old('name')"
                        required
                    />
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <x-ui.input
                            label="Email"
                            name="email"
                            type="email"
                            id="signup-email"
                            placeholder="john@example.com"
                            :value="old('email')"
                            required
                        />
                        <x-ui.input
                            label="Username"
                            name="username"
                            type="text"
                            id="signup-username"
                            placeholder="johndoe7"
                            :value="old('username')"
                            required
                        />
                    </div>
                    <x-ui.input
                        label="Password"
                        name="password"
                        type="password"
                        id="signup-password"
                        placeholder="••••••••"
                        required
                    />
                    <x-ui.input
                        label="Confirm Password"
                        name="password_confirmation"
                        type="password"
                        id="signup-password_confirmation"
                        placeholder="••••••••"
                        required
                    />
                    <div class="flex items-start">
                        <div class="flex items-center h-5">
                            <input class="rounded border-border-default bg-elevated text-accent focus:ring-accent focus:ring-offset-surface h-4 w-4" id="terms" name="terms" type="checkbox" required/>
                        </div>
                        <div class="ml-3 text-sm">
                            <label class="text-text-secondary" for="terms">
                                I agree to the <a class="text-accent hover:underline" href="{{ route('legal', ['doc' => 'terms']) }}">Terms of Service</a> and <a class="text-accent hover:underline" href="{{ route('legal', ['doc' => 'privacy']) }}">Privacy Policy</a>.
                            </label>
                        </div>
                    </div>
                    <x-ui.button type="submit" class="w-full" size="lg" x-bind:loading="submitting">Create Account</x-ui.button>
                </form>
                @include('partials.auth.google-gis', ['mode' => 'button', 'surface' => 'register', 'buttonText' => 'signup_with'])
                <footer class="mt-6 pt-6 border-t border-border-default text-center">
                    <p class="text-text-secondary text-sm">
                        Already have an account?
                        <a href="{{ route('login') }}" class="text-accent hover:text-primary font-semibold transition-colors">Login Here</a>
                    </p>
                </footer>
            </x-ui.card>
        </section>

        @php
            $gisSurface = (request()->get('view') === 'signup' || ($showSignup ?? false)) ? 'register' : 'login';
        @endphp
        @include('partials.auth.google-gis', ['mode' => 'one_tap', 'surface' => $gisSurface])
    </main>
</x-layouts.auth>
