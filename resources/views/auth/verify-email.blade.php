<x-layouts.auth
    title="Verify email — {{ $siteName ?? config('app.name') }}"
    :panel-image="asset('assets/images/services-hero.jpg')"
    panel-headline="One more step."
    panel-copy="Enter the code we sent to your email to activate your account."
>
    <header class="mb-8 text-center lg:text-left">
        <div class="mx-auto lg:mx-0 mb-5 flex size-16 items-center justify-center rounded-full bg-primary/10 text-primary">
            <x-ui.icon name="notifications" class="w-8 h-8" />
        </div>
        <h2 class="font-display text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">Verify your email</h2>
        <p class="mt-2 text-sm sm:text-base text-slate-500">
            We’ve sent a 6-digit code to your email. Enter it below to activate your account.
        </p>
    </header>

    <form action="{{ route('verification.verify') }}" method="POST" class="space-y-6" id="otp-form" x-data="{ submitting: false }" @submit="submitting = true">
        @csrf
        <div class="flex justify-between gap-2 max-w-sm mx-auto lg:mx-0" id="otp-inputs">
            @php $oldOtp = str_split(old('otp', '')); @endphp
            @for ($i = 0; $i < 6; $i++)
                <input
                    inputmode="numeric"
                    autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}"
                    class="w-11 h-12 sm:w-12 sm:h-14 text-center text-xl sm:text-2xl font-bold rounded-lg border-2 border-slate-200 bg-slate-50 focus:border-primary focus:ring-0 transition-colors text-slate-900 @error('otp') border-danger @enderror"
                    maxlength="1"
                    type="text"
                    value="{{ $oldOtp[$i] ?? '' }}"
                    data-index="{{ $i }}"
                    aria-label="Digit {{ $i + 1 }}"
                >
            @endfor
        </div>
        <input type="hidden" name="otp" id="otp-combined" value="{{ old('otp') }}">
        @error('otp')
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div>
        @enderror
        <x-ui.button type="submit" class="w-full" size="lg" x-bind:loading="submitting">
            Verify email
        </x-ui.button>
    </form>

    <form action="{{ route('verification.send') }}" method="POST" class="mt-4 text-center">
        @csrf
        <p class="text-sm text-slate-500 mb-1">Didn’t receive a code?</p>
        <x-ui.button type="submit" variant="link">Resend</x-ui.button>
    </form>

    <x-slot:scripts>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const form = document.getElementById('otp-form');
                const inputs = form.querySelectorAll('#otp-inputs input');
                const combined = document.getElementById('otp-combined');

                function updateCombined() {
                    combined.value = Array.from(inputs).map(i => i.value).join('');
                }

                updateCombined();
                inputs.forEach((input, i) => {
                    input.addEventListener('input', function () {
                        const v = this.value.replace(/\D/g, '');
                        this.value = v.slice(-1);
                        updateCombined();
                        if (v && i < inputs.length - 1) inputs[i + 1].focus();
                    });
                    input.addEventListener('keydown', function (e) {
                        if (e.key === 'Backspace' && !this.value && i > 0) {
                            inputs[i - 1].focus();
                        }
                    });
                    input.addEventListener('paste', function (e) {
                        e.preventDefault();
                        const pasted = (e.clipboardData?.getData('text') || '').replace(/\D/g, '').slice(0, 6);
                        pasted.split('').forEach((char, j) => {
                            if (inputs[j]) inputs[j].value = char;
                        });
                        updateCombined();
                        if (inputs[Math.min(pasted.length, inputs.length) - 1]) {
                            inputs[Math.min(pasted.length, inputs.length) - 1].focus();
                        }
                    });
                });

                form.addEventListener('submit', function () {
                    updateCombined();
                });
            });
        </script>
    </x-slot:scripts>
</x-layouts.auth>
