@extends('layouts.dashboard-user')

@section('title', 'Bank transfer | '.$order->reference)

@section('content')
@php
    $proofSubmitted = filled($order->payment_submitted_at);
    $bankConfigured = filled($bankDetails['bank_name'] ?? null) && filled($bankDetails['account_number'] ?? null);
    $showActivePayment = ! $proofSubmitted && ! $paymentExpired;
    $showFailedPayment = ! $proofSubmitted && $paymentExpired;
@endphp
<x-layout.page
    title="Complete bank transfer"
    width="default"
    :breadcrumb="[
        ['Dashboard', route('dashboard')],
        ['Service orders', route('dashboard.service-orders')],
        [$order->reference, null],
    ]"
>
    <x-dashboard.card class="w-full">
        <div class="space-y-6">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-primary mb-1">Pending payment</p>
                <h2 class="text-xl font-semibold text-text-primary">Order {{ $order->reference }}</h2>
                <p class="text-3xl font-bold text-primary mt-2">₦{{ number_format((float) $order->total_amount, 2) }}</p>
            </div>

            @if ($proofSubmitted)
                <x-dashboard.alert type="info">
                    <p class="font-semibold text-text-primary">Payment under review</p>
                    <p class="mt-1 text-sm">{{ session('status') ?: 'Your payment is being processed. We will review your transfer and confirm your order shortly.' }}</p>
                </x-dashboard.alert>
            @else
                <div
                    x-data="manualBankPayment({
                        secondsRemaining: {{ (int) $secondsRemaining }},
                        paymentExpired: @js((bool) $paymentExpired),
                        proofSubmitted: false,
                        paymentSession: {{ (int) $paymentSession }},
                        maxSessions: {{ (int) $maxPaymentSessions }},
                        expireUrl: @js(route('dashboard.orders.manual-payment.expire', $order)),
                        restartUrl: @js(route('dashboard.orders.manual-payment.restart', $order)),
                        submitUrl: @js(route('dashboard.orders.manual-payment.submit', $order)),
                        dashboardUrl: @js(route('dashboard')),
                        csrfToken: @js(csrf_token()),
                        accountNumber: @js($bankDetails['account_number'] ?? ''),
                    })"
                    x-init="init()"
                    class="space-y-5"
                >
                    <div
                        @if ($showActivePayment) @else style="display: none" @endif
                        x-show="phase === 'active' && !submitted"
                        class="space-y-5"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3">
                            <p class="text-sm font-semibold text-primary">Time remaining to complete payment</p>
                            <p class="text-2xl font-bold tabular-nums text-primary" x-text="countdownLabel">{{ sprintf('%d:%02d', intdiv((int) $secondsRemaining, 60), (int) $secondsRemaining % 60) }}</p>
                        </div>

                        @if ($bankConfigured)
                            <div class="rounded-2xl border-2 border-primary/40 bg-primary/5 px-5 py-6 space-y-5">
                                <p class="text-sm font-bold uppercase tracking-widest text-primary">Transfer to this account</p>

                                <div class="space-y-4">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-primary/80">Bank name</p>
                                        <p class="mt-1 text-xl font-bold text-primary">{{ $bankDetails['bank_name'] }}</p>
                                    </div>

                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-primary/80">Account number</p>
                                        <div class="mt-1 flex flex-wrap items-center gap-3">
                                            <p class="text-3xl font-bold tracking-wide text-primary font-mono">{{ $bankDetails['account_number'] }}</p>
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-2 rounded-lg border border-primary/40 bg-white/80 px-3 py-2 text-sm font-semibold text-primary hover:bg-primary/10"
                                                x-on:click="copyAccountNumber()"
                                            >
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                                </svg>
                                                <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                                            </button>
                                        </div>
                                    </div>

                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-primary/80">Account name</p>
                                        <p class="mt-1 text-xl font-bold text-primary">{{ $bankDetails['account_name'] }}</p>
                                    </div>

                                    <p class="text-base font-bold text-primary">
                                        Transfer exactly <span class="text-2xl">₦{{ number_format((float) $order->total_amount, 2) }}</span>
                                    </p>
                                    <p class="text-sm font-semibold text-primary/90">
                                        Use order reference <span class="font-mono">{{ $order->reference }}</span> as your transfer narration where possible.
                                    </p>
                                </div>
                            </div>
                        @else
                            <x-dashboard.alert type="warning">
                                Bank transfer details are not configured yet. Please contact support with your order reference <strong>{{ $order->reference }}</strong>.
                            </x-dashboard.alert>
                        @endif

                        <div x-show="!showProofForm" class="space-y-4">
                            <x-dashboard.button type="button" variant="primary" class="w-full" x-on:click="showProofForm = true; $nextTick(() => document.getElementById('proof-form')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))">
                                I Have Made This Payment
                            </x-dashboard.button>

                            @if(filled($bankDetails['instructions'] ?? null))
                                <div class="rounded-xl border border-border-default bg-muted/30 px-4 py-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-text-muted mb-1.5">Instructions</p>
                                    <p class="text-sm text-black whitespace-pre-line leading-relaxed">{{ $bankDetails['instructions'] }}</p>
                                </div>
                            @endif
                        </div>

                        <form
                            id="proof-form"
                            method="POST"
                            action="{{ route('dashboard.orders.manual-payment.submit', $order) }}"
                            enctype="multipart/form-data"
                            class="space-y-4"
                            style="display: none"
                            x-show="showProofForm"
                            x-cloak
                            x-on:submit.prevent="submitProof()"
                        >
                            @csrf
                            <x-dashboard.alert type="info">
                                Upload your payment proof so we can verify your transfer.
                            </x-dashboard.alert>

                            <div class="space-y-1.5">
                                <label for="proof" class="block text-sm font-semibold text-text-primary">Payment proof <span class="text-danger">*</span></label>
                                <input
                                    id="proof"
                                    type="file"
                                    name="proof"
                                    accept=".jpg,.jpeg,.png,.pdf"
                                    required
                                    x-ref="proofInput"
                                    class="block w-full text-sm text-text-secondary file:mr-3 file:rounded-lg file:border-0 file:bg-elevated file:px-3 file:py-2 file:text-sm file:text-text-primary"
                                />
                                <p class="text-xs text-text-muted">JPG, PNG, or PDF up to 5 MB.</p>
                            </div>

                            <x-dashboard.button type="submit" icon="upload" class="w-full" x-bind:disabled="submitting">
                                <span x-text="submitting ? 'Processing…' : 'Submit payment proof'">Submit payment proof</span>
                            </x-dashboard.button>
                        </form>
                    </div>

                    <div
                        @if ($showFailedPayment) @else style="display: none" @endif
                        x-show="phase === 'failed' && !submitted"
                        class="rounded-xl border-2 border-danger/40 bg-danger/10 px-5 py-6 text-center space-y-3"
                    >
                        <p class="text-2xl font-bold text-danger">Payment Failed</p>
                        <p class="text-sm text-text-secondary">The payment window expired before your transfer was submitted.</p>
                        <x-dashboard.button type="button" variant="primary" class="w-full sm:w-auto" x-on:click="restartPayment()" x-bind:disabled="restarting">
                            <span x-text="restarting ? 'Restarting…' : 'Restart Payment'">Restart Payment</span>
                        </x-dashboard.button>
                    </div>

                    <div x-show="submitted" style="display: none">
                        <x-dashboard.alert type="info">
                            <p class="font-semibold text-text-primary">Payment under review</p>
                            <p class="mt-1 text-sm" x-text="statusMessage || @js(session('status') ?: 'Your payment is being processed. We will review your transfer and confirm your order shortly.')"></p>
                        </x-dashboard.alert>
                    </div>

                    <div
                        x-show="cancelModalOpen"
                        x-cloak
                        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
                        role="dialog"
                        aria-modal="true"
                    >
                        <div class="w-full max-w-md rounded-2xl border border-border-default bg-surface p-6 shadow-xl space-y-4">
                            <h3 class="text-lg font-semibold text-text-primary">Order cancelled</h3>
                            <p class="text-sm text-text-secondary" x-text="cancelMessage"></p>
                            <x-dashboard.button type="button" variant="primary" class="w-full" x-on:click="goToDashboard()">
                                Continue to dashboard
                            </x-dashboard.button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </x-dashboard.card>
</x-layout.page>
@endsection
