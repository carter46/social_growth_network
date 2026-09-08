@extends('layouts.marketing')

@section('title', 'How It Works')

@section('content')
@php $brandName = $siteName ?? config('app.name', 'Social Growth Network'); @endphp

@include('partials.marketing.page-header', [
    'breadcrumbs' => [
        ['label' => 'Home', 'href' => route('home')],
        ['label' => 'How It Works'],
    ],
    'title' => 'How It Works',
    'subtitle' => 'A simple path from package to campaign — content for this page is coming soon.',
])

<section class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 pb-16 sm:pb-20">
    <div class="rounded-2xl border border-slate-200 bg-white p-8 sm:p-10 shadow-sm">
        <p class="text-slate-600 leading-relaxed mb-6">
            We’re preparing a full walkthrough of how {{ $brandName }} works — choosing a package, checkout, and launching your campaign.
        </p>
        <p class="text-slate-600 leading-relaxed mb-8">
            In the meantime, browse our predefined services or open the help center for answers.
        </p>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('services') }}" class="inline-flex items-center justify-center rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover transition-colors">Browse Services</a>
            <a href="{{ route('help') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50 transition-colors">Help Center</a>
        </div>
    </div>
</section>
@endsection
