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
            <div class="lg:col-span-6 flex flex-col items-start gap-4">
                <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-blue-50 text-primary text-[11px] font-bold uppercase tracking-wider">
                    <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                    Task Marketplace · Earn on Your Schedule
                </div>
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
                    <a href="#how-it-works" class="inline-flex items-center justify-center gap-1.5 font-semibold text-sm bg-white text-slate-900 px-5 py-3 rounded-xl hover:bg-slate-50 transition-colors shadow-sm border border-slate-200">
                        <span class="material-symbols-outlined text-[18px] text-primary" aria-hidden="true">play_circle</span>
                        <span>See How It Works</span>
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

            <div class="lg:col-span-6 relative pb-2 sm:pb-12">
                <div class="relative rounded-2xl overflow-hidden shadow-xl bg-slate-100">
                    <img src="{{ $imgAgent }}" alt="Digital agent working productively" class="w-full h-[320px] sm:h-[420px] lg:h-[460px] object-cover" loading="eager">
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 via-transparent to-transparent"></div>
                </div>
                <div class="sm:absolute sm:-bottom-8 sm:left-0 sm:right-auto mt-4 sm:mt-0 w-full sm:max-w-md bg-white/95 backdrop-blur-md rounded-xl p-4 sm:p-5 shadow-xl border border-slate-100 z-10">
                    <div class="flex flex-wrap items-center justify-between gap-2 pb-2">
                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-800 text-[11px] font-bold uppercase tracking-wider">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 shrink-0"></span>
                            Live Campaign Task · Active
                        </div>
                        <span class="text-[11px] font-medium text-slate-500 shrink-0">Verified Pool</span>
                    </div>
                    <h3 class="font-display text-lg font-bold text-slate-900 leading-snug">YouTube Video Engagement</h3>
                    <div class="flex flex-wrap items-baseline gap-x-1.5 gap-y-0.5 mt-1">
                        <span class="font-display text-2xl font-bold text-primary">₦250</span>
                        <span class="text-sm text-slate-500">per approved review · Est. 4 mins</span>
                    </div>
                    <div class="mt-3 pt-2 flex flex-col sm:flex-row sm:items-center sm:justify-between text-slate-500 text-sm gap-2">
                        <span class="flex items-center gap-1 min-w-0">
                            <span class="material-symbols-outlined text-[16px] text-emerald-600 shrink-0" aria-hidden="true">verified_user</span>
                            <span>Remaining Spots: <strong class="text-slate-900">84 / 200</strong></span>
                        </span>
                        <span class="text-xs font-medium text-primary shrink-0">Multi-point validation</span>
                    </div>
                    <div class="mt-4">
                        <a href="{{ route('register.agent') }}" class="w-full inline-flex items-center justify-center gap-1.5 font-semibold text-sm bg-primary text-white py-2.5 rounded-lg hover:bg-primary-hover transition-colors">
                            <span>Start Task Now</span>
                            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">arrow_forward</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Metrics --}}
<section class="w-full bg-slate-50 py-8 sm:py-10">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            @foreach([
                ['v' => '₦48.2M+', 'l' => 'Disbursed to date'],
                ['v' => '120K+', 'l' => 'Completed reviews'],
                ['v' => '99.4%', 'l' => 'Payout success rate'],
                ['v' => '< 4 Hours', 'l' => 'Average audit turnaround'],
            ] as $m)
                <div class="flex flex-col">
                    <span class="font-display text-2xl sm:text-3xl font-bold text-slate-900">{{ $m['v'] }}</span>
                    <span class="text-sm text-slate-500">{{ $m['l'] }}</span>
                </div>
            @endforeach
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

{{-- How it works --}}
<section class="w-full bg-slate-50 py-14 sm:py-20 scroll-mt-28" id="how-it-works">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 flex flex-col gap-12">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-primary">Deterministic Path</span>
                <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-slate-900 mt-1">
                    From available task to approved reward
                </h2>
            </div>
            <p class="text-sm text-slate-600 max-w-md">
                A dependable 4-step framework engineered to eliminate client renegotiations and guarantee prompt payouts.
            </p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
            @foreach([
                ['n' => '01', 'icon' => 'search', 'title' => 'Find a Task', 'body' => 'Explore active campaigns across YouTube, social media, web visits, and app feedback. Filter by reward or duration.', 'foot' => 'Instant discovery', 'tone' => 'text-primary'],
                ['n' => '02', 'icon' => 'timer', 'title' => 'Start the Task', 'body' => 'Review clear time limits and completion criteria before accepting. The platform reserves your spot and holds the reward until verification.', 'foot' => 'Zero ambiguity', 'tone' => 'text-primary'],
                ['n' => '03', 'icon' => 'verified', 'title' => 'Submit Proof', 'body' => 'Provide verifiable evidence (timestamps, screenshots, URL logs) through '.$brandName.' structured audit templates.', 'foot' => 'Automated Intake', 'tone' => 'text-primary'],
                ['n' => '04', 'icon' => 'payments', 'title' => 'Get Verified & Paid', 'body' => 'Structured audit validates your work and unlocks your reward straight to your withdrawable wallet.', 'foot' => 'Payout Released', 'tone' => 'text-emerald-700'],
            ] as $step)
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex flex-col justify-between h-full">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <span class="font-display text-lg font-bold {{ $step['tone'] }}">{{ $step['n'] }}</span>
                            <span class="material-symbols-outlined {{ $step['tone'] }} text-[24px]" aria-hidden="true">{{ $step['icon'] }}</span>
                        </div>
                        <h3 class="font-display text-lg font-bold text-slate-900">{{ $step['title'] }}</h3>
                        <p class="text-sm text-slate-600 mt-1.5 leading-relaxed">{{ $step['body'] }}</p>
                    </div>
                    <div class="mt-4 pt-2 text-[11px] font-bold uppercase tracking-wider {{ $step['tone'] === 'text-emerald-700' ? 'text-emerald-700' : 'text-slate-500' }}">{{ $step['foot'] }}</div>
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

{{-- Know requirements --}}
<section class="w-full bg-slate-50 py-14 sm:py-20">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 items-center">
            <div class="lg:col-span-6 flex flex-col gap-5">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-primary">Radical Predictability</span>
                    <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-slate-900 mt-1">
                        Know what is required before you start.
                    </h2>
                    <p class="text-base sm:text-lg text-slate-600 mt-3 leading-relaxed">
                        Predefined criteria — no surprise rejections. Every campaign publishes its full submission rules upfront.
                    </p>
                </div>
                <div class="flex flex-col gap-3.5">
                    @foreach([
                        ['icon' => 'schedule', 'title' => 'Exact duration requirements displayed upfront', 'body' => 'Know down to the second how much interaction or reading time is expected prior to proof intake.'],
                        ['icon' => 'document_scanner', 'title' => 'Automated proof templates for screenshot & video hash', 'body' => 'Drag and drop proof into preformatted slots with automatic timestamp verification.'],
                        ['icon' => 'hourglass_top', 'title' => 'Real-time task reservation countdown clock', 'body' => 'Your spot is reserved while you work. Complete at your pace without fear of spots vanishing mid-task.'],
                        ['icon' => 'published_with_changes', 'title' => 'Transparent feedback if an item requires resubmission', 'body' => 'Clear algorithmic logs specify the exact missing criteria so you can adjust within 24 hours.'],
                    ] as $item)
                        <div class="flex items-start gap-3.5 p-3.5 bg-white rounded-xl shadow-sm border border-slate-100">
                            <span class="material-symbols-outlined text-primary text-[22px] shrink-0 mt-0.5" aria-hidden="true">{{ $item['icon'] }}</span>
                            <div>
                                <h4 class="font-display text-base font-bold text-slate-900">{{ $item['title'] }}</h4>
                                <p class="text-sm text-slate-600 mt-1 leading-relaxed">{{ $item['body'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="lg:col-span-6">
                <div class="relative rounded-2xl overflow-hidden shadow-lg bg-slate-100">
                    <img src="{{ $imgDash }}" alt="Agent monitoring verified task dashboard" class="w-full h-[360px] sm:h-[480px] object-cover" loading="lazy">
                    <div class="absolute top-3 right-3 max-w-[11.5rem] sm:max-w-[13rem] bg-white/95 backdrop-blur-md rounded-xl p-2.5 sm:p-3.5 shadow-md z-10">
                        <div class="flex items-start gap-1.5">
                            <span class="material-symbols-outlined text-emerald-600 text-[20px] shrink-0 mt-0.5" aria-hidden="true">fact_check</span>
                            <div class="min-w-0">
                                <span class="text-sm font-semibold text-slate-900 leading-snug block">99.8% Acceptance Rate</span>
                                <p class="text-xs sm:text-sm text-slate-500 mt-1 leading-snug">When criteria are strictly matched</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Verification chain --}}
<section class="w-full bg-white py-14 sm:py-20">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 flex flex-col items-center text-center gap-8">
        <div class="max-w-2xl">
            <span class="text-[11px] font-bold uppercase tracking-wider text-primary">Institutional Security</span>
            <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-slate-900 mt-1">
                Complete it. Submit it. Get verified.
            </h2>
            <p class="text-sm sm:text-base text-slate-600 mt-3">
                A seamless chain with payouts after verification. No client haggling, no payment delays.
            </p>
        </div>
        <div class="w-full max-w-5xl bg-slate-50 rounded-2xl p-6 sm:p-8 border border-slate-100">
            <div class="grid grid-cols-1 sm:grid-cols-7 gap-4 relative items-center">
                <div class="flex flex-col items-center gap-1.5 text-center">
                    <div class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center font-bold">1</div>
                    <span class="text-sm font-semibold text-slate-900">Task Completed</span>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">On your device</span>
                </div>
                <div class="hidden sm:flex justify-center text-slate-400">
                    <span class="material-symbols-outlined text-[24px]" aria-hidden="true">trending_flat</span>
                </div>
                <div class="flex flex-col items-center gap-1.5 text-center">
                    <div class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center font-bold">2</div>
                    <span class="text-sm font-semibold text-slate-900">Proof Submitted</span>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Uploaded to form</span>
                </div>
                <div class="hidden sm:flex justify-center text-slate-400">
                    <span class="material-symbols-outlined text-[24px]" aria-hidden="true">trending_flat</span>
                </div>
                <div class="flex flex-col items-center gap-1.5 text-center">
                    <div class="w-10 h-10 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold">3</div>
                    <span class="text-sm font-semibold text-slate-900">Verified</span>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Audit passed</span>
                </div>
                <div class="hidden sm:flex justify-center text-slate-400">
                    <span class="material-symbols-outlined text-[24px]" aria-hidden="true">trending_flat</span>
                </div>
                <div class="flex flex-col items-center gap-1.5 text-center">
                    <div class="w-10 h-10 rounded-full bg-emerald-700 text-white flex items-center justify-center font-bold">4</div>
                    <span class="text-sm font-semibold text-slate-900">Reward Settled</span>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Wallet updated</span>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Why become an agent --}}
<section class="w-full bg-slate-50 py-14 sm:py-20">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 flex flex-col gap-12">
        <div class="max-w-2xl">
            <span class="text-[11px] font-bold uppercase tracking-wider text-primary">Platform Benefits</span>
            <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-slate-900 mt-1">
                Built around fairness and certainty.
            </h2>
            <p class="text-base sm:text-lg text-slate-600 mt-3 leading-relaxed">
                We built {{ $brandName }} to treat online contributors with institutional respect, promptness, and verified rewards.
            </p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            @foreach([
                ['icon' => 'flex_direction', 'title' => 'Flexible Workload', 'body' => 'Complete tasks on mobile or desktop anytime at your convenience. No minimum hours or compulsory shifts.'],
                ['icon' => 'rule', 'title' => 'Clear Requirements', 'body' => 'Zero guessing games. Tasks follow strict, objective checklists so you always know what passes inspection.'],
                ['icon' => 'lock', 'title' => 'Verified Payouts', 'body' => 'Campaign budgets are secured at checkout before tasks go live on the marketplace.'],
                ['icon' => 'military_tech', 'title' => 'Reputation Growth', 'body' => 'Consistent accuracy unlocks higher-reward campaign tiers, specialized feedback tasks, and priority dispatch.'],
            ] as $b)
                <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-100 flex flex-col gap-3.5">
                    <div class="w-10 h-10 rounded-lg bg-slate-50 flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined text-[22px]" aria-hidden="true">{{ $b['icon'] }}</span>
                    </div>
                    <h3 class="font-display text-lg font-bold text-slate-900">{{ $b['title'] }}</h3>
                    <p class="text-sm text-slate-600 leading-relaxed">{{ $b['body'] }}</p>
                </div>
            @endforeach
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
                <a href="#how-it-works" class="inline-flex items-center gap-1.5 font-semibold text-sm text-white hover:bg-primary-hover px-6 py-3 rounded-xl transition-colors">
                    <span>Explore How It Works</span>
                </a>
            </div>
            <div class="mt-6 text-blue-100 text-xs font-medium">
                100% free registration · No hidden fees · Fast verified payouts
            </div>
        </div>
    </div>
</section>
@endsection
