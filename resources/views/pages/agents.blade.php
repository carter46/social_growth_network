@extends('layouts.marketing')

@section('title', 'For Agents')

@section('content')
@php
    $brandName = $siteName ?? config('app.name', 'Social Growth Network');
    $imgAgent = asset('assets/images/homeslider3.jpg');
    $imgMobile = asset('assets/images/homeslider2.jpg');
    $imgDash = asset('assets/images/homeslider1.jpg');
@endphp

{{-- Hero --}}
<section class="w-full bg-white pt-10 sm:pt-14 pb-20 sm:pb-28">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 items-center">
            <div class="lg:col-span-6 flex flex-col items-start gap-4 order-2 lg:order-1">
                <h1 class="font-display text-3xl sm:text-4xl lg:text-5xl font-extrabold text-slate-900 tracking-tight leading-tight">
                    Turn digital tasks into <span class="text-primary">real opportunities.</span>
                </h1>
                <p class="text-base sm:text-lg text-slate-600 max-w-xl leading-relaxed">
                    Discover available campaign tasks, complete the requirements, submit your verified proof, and earn rewards when your work is approved.
                </p>
                <div class="flex flex-wrap items-center gap-3 pt-2 w-full sm:w-auto">
                    <a href="{{ route('register.agent') }}" class="inline-flex items-center justify-center gap-1.5 font-semibold text-sm bg-primary text-white px-6 py-3 rounded-xl hover:bg-primary-hover transition-colors shadow-sm">
                        <span>Become an Agent</span>
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_forward</span>
                    </a>
                </div>
                <div class="mt-4 pt-1 bg-slate-50/80 rounded-xl px-3.5 py-2.5 w-full border border-slate-100">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-slate-500 text-xs">
                        @foreach(['Free to join', 'Clear criteria', 'Structured audit', 'Verified payouts'] as $badge)
                            <div class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px] text-emerald-600" aria-hidden="true">check_circle</span>
                                <span>{{ $badge }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="lg:col-span-6 relative order-1 lg:order-2">
                <div class="relative rounded-2xl overflow-hidden shadow-xl bg-slate-100">
                    <img src="{{ $imgAgent }}" alt="Digital agent working productively" class="w-full h-[320px] sm:h-[420px] lg:h-[460px] object-cover" loading="eager">
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 via-transparent to-transparent"></div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- What an agent does --}}
<section class="w-full bg-white py-14 sm:py-20">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 flex flex-col gap-12">
        <div class="max-w-2xl">
            <div class="inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-primary mb-2">
                Clear Purpose · Simple Flow
            </div>
            <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-slate-900">
                Your next task could be waiting.
            </h2>
            <p class="text-base sm:text-lg text-slate-600 mt-3 leading-relaxed">
                Creators launch digital campaigns on {{ $brandName }}. As an Agent, you help fulfill verified campaign activity by completing predefined requirements and submitting proof.
            </p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            @foreach([
                ['step' => 'Step 01', 'stepClass' => 'text-primary', 'icon' => 'explore', 'iconBg' => 'bg-slate-100 text-primary', 'title' => 'Discover', 'body' => 'Browse available digital campaigns and micro-tasks matched to your profile, location, and device setup.'],
                ['step' => 'Step 02', 'stepClass' => 'text-red-600', 'icon' => 'task_alt', 'iconBg' => 'bg-red-50 text-red-600', 'title' => 'Complete', 'body' => 'Follow clear step-by-step instructions (watch, review, visit, test) with no ambiguity or subjective demands.'],
                ['step' => 'Step 03', 'stepClass' => 'text-slate-600', 'icon' => 'upload_file', 'iconBg' => 'bg-slate-100 text-slate-600', 'title' => 'Submit', 'body' => 'Upload structured proof directly through the streamlined '.$brandName.' submission flow with built-in timestamping.'],
                ['step' => 'Step 04', 'stepClass' => 'text-emerald-700', 'icon' => 'account_balance_wallet', 'iconBg' => 'bg-emerald-50 text-emerald-700', 'title' => 'Earn', 'body' => 'Once submissions pass algorithmic and reviewer verification, rewards disburse smoothly to your wallet balance.'],
            ] as $card)
                <div class="bg-slate-50 rounded-xl p-5 flex flex-col gap-3.5 hover:bg-slate-100 transition-colors border border-slate-100">
                    <div class="w-12 h-12 rounded-lg {{ $card['iconBg'] }} flex items-center justify-center">
                        <span class="material-symbols-outlined text-[24px]" aria-hidden="true">{{ $card['icon'] }}</span>
                    </div>
                    <div>
                        <span class="text-[11px] font-bold uppercase {{ $card['stepClass'] }}">{{ $card['step'] }}</span>
                        <h3 class="font-display text-lg font-bold text-slate-900 mt-1">{{ $card['title'] }}</h3>
                    </div>
                    <p class="text-sm text-slate-600 leading-relaxed">{{ $card['body'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Marketplace preview --}}
<section class="w-full bg-white py-14 sm:py-20">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 items-center">
            <div class="lg:col-span-5">
                <div class="relative rounded-2xl overflow-hidden shadow-lg bg-slate-100">
                    <img src="{{ $imgMobile }}" alt="Agent evaluating campaign work on mobile" class="w-full h-[420px] sm:h-[520px] object-cover" loading="lazy">
                    <div class="absolute bottom-0 inset-x-0 p-5 bg-gradient-to-t from-slate-950/80 to-transparent text-white">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-blue-200">Active Session</span>
                        <p class="font-display text-lg font-bold mt-1">Real-time task validation interface</p>
                    </div>
                </div>
            </div>
            <div class="lg:col-span-7 flex flex-col gap-5">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-primary">Live Marketplace Preview</span>
                    <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-slate-900 mt-1">
                        Find tasks that fit you.
                    </h2>
                    <p class="text-sm sm:text-base text-slate-600 mt-2 leading-relaxed">
                        Fresh tasks are published every hour across content testing, app usability, and genuine consumer engagement.
                    </p>
                </div>
                <div class="flex flex-col gap-3.5">
                    @foreach([
                        ['icon' => 'smart_display', 'iconBg' => 'bg-red-50 text-red-600', 'label' => 'YouTube Engagement', 'badge' => '142 spots left', 'badgeClass' => 'text-emerald-700 bg-emerald-50', 'title' => 'Watch 3-minute video and share thoughtful feedback', 'reward' => '₦250', 'time' => '5 mins'],
                        ['icon' => 'smartphone', 'iconBg' => 'bg-slate-100 text-slate-600', 'label' => 'App Usability Feedback', 'badge' => 'High Priority', 'badgeClass' => 'text-primary bg-blue-50', 'title' => 'Test onboarding flow on Android/iOS & record 2 UX bug points', 'reward' => '₦850', 'time' => '12 mins'],
                        ['icon' => 'public', 'iconBg' => 'bg-emerald-50 text-emerald-700', 'label' => 'Website Visit & Dwell', 'badge' => 'Open', 'badgeClass' => 'text-slate-600 bg-slate-100', 'title' => 'Navigate landing page, browse 3 articles, log 90s dwell', 'reward' => '₦180', 'time' => '3 mins'],
                    ] as $task)
                        <div class="bg-slate-50 rounded-xl p-5 hover:shadow-md transition-all border border-slate-100">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-1">
                                <div class="flex items-center gap-2">
                                    <span class="w-7 h-7 rounded-md {{ $task['iconBg'] }} flex items-center justify-center">
                                        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">{{ $task['icon'] }}</span>
                                    </span>
                                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ $task['label'] }}</span>
                                </div>
                                <div class="inline-flex items-center gap-1.5 text-xs font-medium {{ $task['badgeClass'] }} px-2.5 py-1 rounded-full self-start">
                                    <span class="w-1.5 h-1.5 rounded-full bg-current opacity-70"></span>
                                    {{ $task['badge'] }}
                                </div>
                            </div>
                            <h4 class="font-display text-lg font-bold text-slate-900 mt-2">{{ $task['title'] }}</h4>
                            <div class="flex flex-wrap items-center justify-between gap-4 mt-4 pt-1">
                                <div class="flex items-center gap-6">
                                    <div>
                                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Reward</span>
                                        <p class="font-display text-2xl font-bold text-primary">{{ $task['reward'] }}</p>
                                    </div>
                                    <div>
                                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Est. Time</span>
                                        <p class="text-sm font-semibold text-slate-900">{{ $task['time'] }}</p>
                                    </div>
                                </div>
                                <a href="{{ route('register.agent') }}" class="inline-flex items-center gap-1 font-semibold text-sm text-primary hover:text-primary-hover px-3.5 py-2 bg-white rounded-lg shadow-sm border border-slate-100">
                                    <span>View Task</span>
                                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_forward</span>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Final CTA --}}
<section class="w-full bg-white py-14 sm:py-20">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="w-full bg-primary rounded-2xl p-8 sm:p-12 lg:p-16 text-white shadow-xl flex flex-col items-center text-center">
            <span class="text-[11px] font-bold uppercase tracking-wider text-blue-100">Start In Minutes</span>
            <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-white mt-2 max-w-2xl">
                Ready to turn your screen time into real rewards?
            </h2>
            <p class="text-base sm:text-lg text-blue-100 mt-3 max-w-xl leading-relaxed">
                Join thousands of active {{ $brandName }} Agents completing structured digital campaigns daily.
            </p>
            <div class="flex flex-wrap items-center justify-center gap-3 mt-8">
                <a href="{{ route('register.agent') }}" class="inline-flex items-center gap-1.5 font-semibold text-sm bg-white text-primary px-6 py-3 rounded-xl hover:bg-slate-50 transition-colors shadow-md">
                    <span>Become an Agent</span>
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_forward</span>
                </a>
                <a href="{{ route('help') }}" class="inline-flex items-center gap-1.5 font-semibold text-sm text-white hover:bg-primary-hover px-6 py-3 rounded-xl transition-colors">
                    <span>Visit Help Center</span>
                </a>
            </div>
            <div class="mt-6 text-blue-100 text-xs font-medium">
                100% free registration · No hidden fees · Fast verified payouts
            </div>
        </div>
    </div>
</section>
@endsection
