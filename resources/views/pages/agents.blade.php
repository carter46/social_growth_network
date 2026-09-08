@extends('layouts.marketing')

@section('title', 'For Agents')

@section('content')
@php $brandName = $siteName ?? config('app.name', 'Social Growth Network'); @endphp

@include('partials.marketing.page-header', [
    'breadcrumbs' => [
        ['label' => 'Home', 'href' => route('home')],
        ['label' => 'For Agents'],
    ],
    'title' => 'For Agents',
    'subtitle' => 'Earn by completing verified digital tasks — agent guide content coming soon.',
])

<section class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 pb-16 sm:pb-20">
    <div class="rounded-2xl border border-slate-200 bg-white p-8 sm:p-10 shadow-sm">
        <p class="text-slate-600 leading-relaxed mb-6">
            This page will cover how agents join {{ $brandName }}, pick tasks, and get paid for verified completions.
        </p>
        <p class="text-slate-600 leading-relaxed mb-8">
            Content is on the way. You can register as an agent now or visit the help center for account questions.
        </p>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('register.agent') }}" class="inline-flex items-center justify-center rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover transition-colors">Become an agent</a>
            <a href="{{ route('help') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50 transition-colors">Help Center</a>
        </div>
    </div>
</section>
@endsection
