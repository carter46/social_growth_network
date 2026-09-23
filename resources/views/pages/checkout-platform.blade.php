<?php

/**
 * Legacy public checkout view — PlatformCheckoutController redirects to dashboard checkout.
 * Kept as a stub so stale references fail closed without a package-quantity UI.
 */
?>
@extends('layouts.marketing')

@section('title', 'Redirecting…')

@section('content')
<section class="py-14 sm:py-20">
    <div class="max-w-form mx-auto px-5 sm:px-6 text-center space-y-4">
        <h1 class="text-2xl font-bold font-display">Checkout moved</h1>
        <p class="text-slate-400">Complete purchases from your dashboard services checkout.</p>
        <a href="{{ route('dashboard.services') }}" class="inline-flex text-accent underline">Go to Services</a>
    </div>
</section>
@endsection
