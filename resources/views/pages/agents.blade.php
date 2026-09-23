@extends('layouts.marketing')

@section('title', 'For Agents')

@section('content')
@php
    $brandName = $siteName ?? config('app.name', 'Social Growth Network');
    $imgAgent = asset('assets/images/agents-hero.jpg');
    $imgMobile = asset('assets/images/campaign-workspace.jpg');
    $youtubeCatalog = $youtubeCatalog ?? ['featured' => null, 'others' => []];
    $watchHours = $youtubeCatalog['featured'] ?? null;
    $ytWord = static function (string $text, string $tone = 'red'): string {
        $class = $tone === 'white' ? 'text-white' : 'text-red-600';

        return preg_replace(
            '/\bYouTube\b/u',
            '<span class="'.$class.'">YouTube</span>',
            e($text)
        ) ?? e($text);
    };
@endphp

{{-- Hero --}}
<section class="w-full bg-white pt-10 sm:pt-14 pb-16 sm:pb-20 border-b border-slate-100">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 items-center">
            <div class="lg:col-span-6 flex flex-col items-start gap-4 order-2 lg:order-1">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $brandName }} Agents</p>
                <h1 class="font-display text-3xl sm:text-4xl lg:text-5xl font-extrabold text-slate-900 tracking-tight leading-tight">
                    {!! $ytWord('Complete YouTube Watch Hours and other social task sessions.') !!}
                </h1>
                <p class="text-base sm:text-lg text-slate-600 max-w-xl leading-relaxed">
                    Join {{ $brandName }} task sessions involving YouTube Watch Hours and other social media activities when they are available. Follow each task’s requirements, submit as instructed, and earn rewards when your work is approved.
                </p>
                <p class="text-sm text-slate-500 max-w-xl leading-relaxed">
                    Rewards depend on task requirements and approval. Completing a session does not guarantee official platform metrics, and open tasks are not always available.
                </p>
                <div class="flex flex-wrap items-center gap-3 pt-2 w-full sm:w-auto">
                    <a href="{{ route('register.agent') }}" class="inline-flex items-center justify-center gap-1.5 font-semibold text-sm bg-red-600 text-white px-6 py-3 rounded-xl hover:bg-red-700 transition-colors shadow-sm">
                        <span>Become an Agent</span>
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_forward</span>
                    </a>
                </div>
                <div class="mt-4 pt-1 bg-slate-50/80 rounded-xl px-3.5 py-2.5 w-full border border-slate-100">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-slate-500 text-xs">
                        @foreach(['Free to join', 'Clear criteria', 'Approval-based rewards', 'Structured sessions'] as $badge)
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
                    <img src="{{ $imgAgent }}" alt="Agent completing digital task sessions" class="w-full h-[320px] sm:h-[420px] lg:h-[460px] object-cover" loading="eager">
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 via-transparent to-transparent"></div>
                    @if(is_array($watchHours))
                        <div class="absolute bottom-4 left-4 right-4 text-white">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-red-200">Example activity type</p>
                            <p class="font-display text-base sm:text-lg font-bold mt-0.5">{!! $ytWord($watchHours['title'] ?? 'YouTube Watch Hours', 'white') !!}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>

{{-- What an agent does --}}
<section class="w-full bg-white py-14 sm:py-20">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 flex flex-col gap-12">
        <div class="max-w-2xl">
            <div class="inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-red-600 mb-2">
                Clear Purpose · Simple Flow
            </div>
            <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-slate-900">
                How {{ $brandName }} task sessions work
            </h2>
            <p class="text-base sm:text-lg text-slate-600 mt-3 leading-relaxed">
                Creators launch digital campaigns. As an Agent, you complete available predefined requirements—Watch Hours sessions and other social activities are examples when those campaigns are open—then submit proof for review.
            </p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            @foreach([
                ['step' => 'Step 01', 'stepClass' => 'text-red-600', 'icon' => 'explore', 'iconBg' => 'bg-red-50 text-red-600', 'title' => 'Discover', 'body' => 'Browse available digital campaigns and micro-tasks when they appear in your Agent dashboard.'],
                ['step' => 'Step 02', 'stepClass' => 'text-red-600', 'icon' => 'task_alt', 'iconBg' => 'bg-red-50 text-red-600', 'title' => 'Complete', 'body' => 'Follow clear step-by-step instructions for the session (for example Watch Hours timing or other social steps).'],
                ['step' => 'Step 03', 'stepClass' => 'text-slate-600', 'icon' => 'upload_file', 'iconBg' => 'bg-slate-100 text-slate-600', 'title' => 'Submit', 'body' => 'Upload structured proof through the '.$brandName.' submission flow when the task requires it.'],
                ['step' => 'Step 04', 'stepClass' => 'text-emerald-700', 'icon' => 'account_balance_wallet', 'iconBg' => 'bg-emerald-50 text-emerald-700', 'title' => 'Earn', 'body' => 'When submissions meet requirements and are approved, rewards credit to your wallet. Approval is required — opening or autoplaying alone is not enough.'],
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
<section class="w-full bg-slate-50 py-14 sm:py-20 border-y border-slate-100">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        @php
            $brandPaths = [
                'youtube' => 'M23.5 6.2a3 3 0 00-2.1-2.1C19.5 3.5 12 3.5 12 3.5s-7.5 0-9.4.6A3 3 0 00.5 6.2 31.5 31.5 0 000 12a31.5 31.5 0 00.5 5.8 3 3 0 002.1 2.1c1.9.6 9.4.6 9.4.6s7.5 0 9.4-.6a3 3 0 002.1-2.1A31.5 31.5 0 0024 12a31.5 31.5 0 00-.5-5.8zM9.75 15.5v-7l6.25 3.5-6.25 3.5z',
                'facebook' => 'M18.77 7.46H15.5v-1.9c0-.9.6-1.1 1-1.1h2.2V2.14S17.36 2 16.14 2C13.6 2 11.9 3.66 11.9 6.54v1.92H9.5v3.4h2.4V22h3.6v-9.14h3l.37-3.4z',
                'instagram' => 'M12 7a5 5 0 100 10 5 5 0 000-10zm0 8.2A3.2 3.2 0 1112 8.8a3.2 3.2 0 010 6.4zm6.4-8.3a1.16 1.16 0 11-2.32 0 1.16 1.16 0 012.32 0zM12 4.4c-2.07 0-2.33.01-3.14.05-.8.04-1.35.17-1.83.36a3.7 3.7 0 00-1.34.87 3.7 3.7 0 00-.87 1.34c-.19.48-.32 1.03-.36 1.83-.04.81-.05 1.07-.05 3.14s.01 2.33.05 3.14c.04.8.17 1.35.36 1.83.2.49.46.9.87 1.34.44.41.85.67 1.34.87.48.19 1.03.32 1.83.36.81.04 1.07.05 3.14.05s2.33-.01 3.14-.05c.8-.04 1.35-.17 1.83-.36a3.7 3.7 0 001.34-.87 3.7 3.7 0 00.87-1.34c.19-.48.32-1.03.36-1.83.04-.81.05-1.07.05-3.14s-.01-2.33-.05-3.14c-.04-.8-.17-1.35-.36-1.83a3.7 3.7 0 00-.87-1.34 3.7 3.7 0 00-1.34-.87c-.48-.19-1.03-.32-1.83-.36-.81-.04-1.07-.05-3.14-.05zm0 1.62c2.03 0 2.27.01 3.07.05.74.03 1.14.16 1.41.26.36.14.61.3.88.57.27.27.43.52.57.88.1.27.23.67.26 1.41.04.8.05 1.04.05 3.07s-.01 2.27-.05 3.07c-.03.74-.16 1.14-.26 1.41-.14.36-.3.61-.57.88a2.4 2.4 0 01-.88.57c-.27.1-.67.23-1.41.26-.8.04-1.04.05-3.07.05s-2.27-.01-3.07-.05c-.74-.03-1.14-.16-1.41-.26a2.4 2.4 0 01-.88-.57 2.4 2.4 0 01-.57-.88c-.1-.27-.23-.67-.26-1.41-.04-.8-.05-1.04-.05-3.07s.01-2.27.05-3.07c.03-.74.16-1.14.26-1.41.14-.36.3-.61.57-.88.27-.27.52-.43.88-.57.27-.1.67-.23 1.41-.26.8-.04 1.04-.05 3.07-.05z',
                'tiktok' => 'M19.6 7.2a5.7 5.7 0 01-3.4-1.1v7.4a5.7 5.7 0 11-5.7-5.7c.3 0 .6 0 .9.1v2.9a2.8 2.8 0 100 5.5 2.8 2.8 0 002.8-2.8V2h2.9a5.7 5.7 0 003.4 3.4v1.8z',
                'twitter' => 'M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.74l7.727-8.835L1.254 2.25H8.08l4.259 5.622L18.244 2.25zm-1.161 17.52h1.833L7.084 4.126H5.117L17.083 19.77z',
                'social' => 'M18 16.1c-.8 0-1.4.3-2 .8l-7.1-4.1c.1-.3.1-.5.1-.8s0-.5-.1-.8l7.1-4.1c.5.5 1.2.8 2 .8 1.7 0 3-1.3 3-3s-1.3-3-3-3-3 1.3-3 3c0 .3 0 .5.1.8L7.9 9.9C7.4 9.4 6.7 9.1 6 9.1c-1.7 0-3 1.3-3 3s1.3 3 3 3c.7 0 1.4-.3 2-.8l7.1 4.1c-.1.3-.1.5-.1.8 0 1.7 1.3 3 3 3s3-1.3 3-3-1.3-3-3-3z',
            ];
            $brandColors = [
                'youtube' => '#FF0000',
                'facebook' => '#1877F2',
                'instagram' => '#E4405F',
                'tiktok' => '#111827',
                'twitter' => '#111827',
                'social' => '#6366F1',
            ];
            $fallbackTasks = [
                ['brand' => 'youtube', 'iconBg' => 'bg-red-50', 'label' => 'YouTube Watch Hours', 'badge' => 'Example', 'badgeClass' => 'text-slate-600 bg-slate-100', 'title' => 'Complete a timed Watch Hours task session when available', 'reward' => 'If approved', 'time' => 'Per task', 'href' => route('register.agent')],
                ['brand' => 'instagram', 'iconBg' => 'bg-pink-50', 'label' => 'Instagram activity', 'badge' => 'Example', 'badgeClass' => 'text-slate-600 bg-slate-100', 'title' => 'Engage with posts according to campaign instructions', 'reward' => 'If approved', 'time' => 'Per task', 'href' => route('register.agent')],
                ['brand' => 'tiktok', 'iconBg' => 'bg-slate-100', 'label' => 'TikTok activity', 'badge' => 'Example', 'badgeClass' => 'text-slate-600 bg-slate-100', 'title' => 'Complete engagement steps listed in the task brief', 'reward' => 'If approved', 'time' => 'Per task', 'href' => route('register.agent')],
            ];
            $tasks = collect($marketplaceTasks ?? [])->isNotEmpty()
                ? collect($marketplaceTasks)
                : collect($fallbackTasks);
        @endphp

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10">
            <div class="lg:col-span-5">
                <div class="relative rounded-2xl overflow-hidden shadow-lg bg-slate-100 h-64 sm:h-80 lg:h-full min-h-[280px]">
                    <img src="{{ $imgMobile }}" alt="Agent reviewing a task session on mobile" class="absolute inset-0 w-full h-full object-cover" loading="lazy">
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-transparent to-transparent"></div>
                    <div class="absolute bottom-0 inset-x-0 p-4 sm:p-5 text-white">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-red-200">Task sessions</span>
                        <p class="font-display text-base sm:text-lg font-bold mt-1 leading-snug">Watch Hours and other social activities when open</p>
                    </div>
                </div>
            </div>
            <div class="lg:col-span-7 flex flex-col gap-4">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-red-600">Marketplace preview</span>
                    <h2 class="font-display text-xl sm:text-2xl font-extrabold text-slate-900 mt-1">
                        Available tasks when campaigns are live
                    </h2>
                    <p class="text-sm text-slate-600 mt-1.5 leading-relaxed">
                        Examples below. Actual open tasks vary — availability is not guaranteed.
                    </p>
                </div>
                <div class="flex flex-col gap-2.5">
                    @foreach($tasks as $task)
                        @php
                            $brand = $task['brand'] ?? 'social';
                            $path = $brandPaths[$brand] ?? $brandPaths['social'];
                            $fill = $brandColors[$brand] ?? $brandColors['social'];
                        @endphp
                        <div class="bg-white rounded-xl px-3.5 py-3 hover:shadow-sm transition-shadow border border-slate-100">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="w-7 h-7 rounded-md {{ $task['iconBg'] ?? 'bg-slate-100' }} flex items-center justify-center shrink-0">
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="{{ $fill }}" aria-hidden="true">
                                            <path d="{{ $path }}"></path>
                                        </svg>
                                    </span>
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 truncate">{{ $task['label'] }}</span>
                                </div>
                                <div class="inline-flex items-center gap-1 text-[10px] font-medium {{ $task['badgeClass'] ?? 'text-slate-600 bg-slate-100' }} px-2 py-0.5 rounded-full shrink-0">
                                    <span class="w-1.5 h-1.5 rounded-full bg-current opacity-70"></span>
                                    {{ $task['badge'] }}
                                </div>
                            </div>
                            <h4 class="font-display text-sm sm:text-base font-bold text-slate-900 mt-1.5 leading-snug">{{ $task['title'] }}</h4>
                            <div class="flex flex-wrap items-center justify-between gap-3 mt-2.5">
                                <div class="flex items-center gap-5">
                                    <div>
                                        <span class="text-[9px] font-bold uppercase tracking-wider text-slate-500">Reward</span>
                                        <p class="font-display text-base sm:text-lg font-bold text-red-600 leading-tight">{{ $task['reward'] }}</p>
                                    </div>
                                    <div>
                                        <span class="text-[9px] font-bold uppercase tracking-wider text-slate-500">Est. Time</span>
                                        <p class="text-xs sm:text-sm font-semibold text-slate-900 leading-tight">{{ $task['time'] }}</p>
                                    </div>
                                </div>
                                <a href="{{ $task['href'] ?? route('register.agent') }}" class="inline-flex items-center gap-1 font-semibold text-xs text-red-600 hover:text-red-700 px-2.5 py-1.5 bg-white rounded-lg shadow-sm border border-slate-100">
                                    <span>View Task</span>
                                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true">arrow_forward</span>
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
        <div class="w-full bg-red-600 rounded-2xl p-8 sm:p-12 lg:p-16 text-white shadow-xl flex flex-col items-center text-center">
            <span class="text-[11px] font-bold uppercase tracking-wider text-red-100">Start when you are ready</span>
            <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-white mt-2 max-w-2xl">
                Ready to join {{ $brandName }} as an Agent?
            </h2>
            <p class="text-base sm:text-lg text-red-50 mt-3 max-w-xl leading-relaxed">
                Register to access available task sessions. Rewards are paid when requirements are met and work is approved — not for simply opening or autoplaying content.
            </p>
            <div class="flex flex-wrap items-center justify-center gap-3 mt-8">
                <a href="{{ route('register.agent') }}" class="inline-flex items-center gap-1.5 font-semibold text-sm bg-white text-red-600 px-6 py-3 rounded-xl hover:bg-slate-50 transition-colors shadow-md">
                    <span>Become an Agent</span>
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_forward</span>
                </a>
                <a href="{{ route('help') }}" class="inline-flex items-center gap-1.5 font-semibold text-sm text-white hover:bg-red-700 px-6 py-3 rounded-xl transition-colors">
                    <span>Visit Help Center</span>
                </a>
            </div>
            <div class="mt-6 text-red-100 text-xs font-medium">
                Free registration · Approval-based rewards · No guaranteed earnings or task availability
            </div>
        </div>
    </div>
</section>
@endsection
