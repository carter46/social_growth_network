@php
    $kycRequired = (bool) ($kycRequired ?? true);
    $kycLevel = (int) ($kycLevel ?? 0);
    $submission = $submission ?? null;
    $prefix = $prefix ?? 'dashboard';
    $walletRoute = $prefix === 'agent' ? 'agent.wallet' : 'dashboard.wallet';
    $kycStoreRoute = $prefix === 'agent' ? 'agent.kyc.store' : 'dashboard.kyc.store';
@endphp

<x-dashboard.card>
    <h2 class="text-lg font-semibold text-text-primary mb-1">Identity verification (KYC)</h2>
    @if (! $kycRequired)
        <x-dashboard.alert type="info" class="mt-3">
            KYC is currently optional on this platform. You can still submit documents if you want a verified badge.
        </x-dashboard.alert>
    @else
        <p class="text-sm text-text-secondary mt-1">
            @if ($prefix === 'agent')
                Level 1 verification is required before creating a wallet and withdrawing earnings.
            @else
                Level 1 verification is required before creating a wallet.
            @endif
        </p>
    @endif

    <p class="text-text-primary mt-4">Current level: <strong>{{ $kycLevel }}</strong></p>

    @if ($kycLevel < 1)
        <form method="POST" action="{{ route($kycStoreRoute) }}" class="mt-6 space-y-4 max-w-xl" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <x-dashboard.input label="Document type" name="document_type" required :value="old('document_type')" />
            <x-dashboard.input label="Document number" name="document_number" required :value="old('document_number')" />
            <x-dashboard.button type="submit" icon="kyc" x-bind:disabled="submitting">Submit KYC Level 1</x-dashboard.button>
        </form>
    @else
        <x-dashboard.alert type="success" title="KYC approved" class="mt-4">
            You can <a href="{{ route($walletRoute) }}" class="underline font-medium">open your wallet</a>.
        </x-dashboard.alert>
    @endif

    @if ($submission)
        <p class="text-text-secondary mt-4 text-sm">Latest submission: {{ $submission->status }}</p>
    @endif
</x-dashboard.card>
