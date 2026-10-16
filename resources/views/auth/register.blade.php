@php
    $hasPackageDraft = app(\App\Services\Catalog\PurchaseDraftService::class)->has();
@endphp

<x-layouts.auth
    title="Create account | {{ $siteName ?? config('app.name') }}"
    :panel-image="asset('assets/images/creators-hero.jpg')"
    panel-headline="Get your YouTube videos seen."
    panel-copy="Create an account to launch promotion campaigns that boost your visibility, reach and engagement."
>
    <div class="mb-6 hidden lg:block">
        <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 hover:text-primary transition-colors">
            <span aria-hidden="true">←</span>
            Back to Home
        </a>
    </div>

    <header class="mb-6">
        <h2 class="font-display text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">Create your account</h2>
        <p class="mt-2 text-sm sm:text-base text-slate-500">Start promoting your YouTube videos in a few short steps.</p>
    </header>

    @include('auth._signup-wizard', [
        'action' => route('register'),
        'accountType' => 'creator',
        'submitLabel' => 'Create account',
        'withInviteCode' => false,
        'notice' => $hasPackageDraft ? 'Your package is saved. Create a Creator account to finish your order.' : null,
    ])
</x-layouts.auth>
