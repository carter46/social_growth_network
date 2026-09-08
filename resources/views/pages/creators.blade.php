@extends('layouts.marketing')

@section('title', 'For Creators')

@section('content')
@php $brandName = $siteName ?? config('app.name', 'Social Growth Network'); @endphp

@include('partials.marketing.page-header', [
    'breadcrumbs' => [
        ['label' => 'Home', 'href' => route('home')],
        ['label' => 'For Creators'],
    ],
    'title' => 'For Creators',
    'subtitle' => 'Grow your audience with fixed campaign packages — full creator content coming soon.',
])

<section class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 pb-16 sm:pb-20">
    <div class="rounded-2xl border border-slate-200 bg-white p-8 sm:p-10 shadow-sm">
        <p class="text-slate-600 leading-relaxed mb-6">
            This page will explain how creators use {{ $brandName }} to launch growth and engagement campaigns with clear pricing.
        </p>
        <p class="text-slate-600 leading-relaxed mb-8">
            Until then, explore live packages on the services page or create an account to get started.
        </p>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('services') }}" class="inline-flex items-center justify-center rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover transition-colors">Browse Services</a>
            <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50 transition-colors">Create account</a>
        </div>
    </div>
</section>
@endsection
