@extends('layouts.marketing')

@section('title', 'For Agents')

@section('content')
@php
    $brandName = $siteName ?? config('app.name', 'Social Growth Network');
    $imgAgent = asset('assets/images/task_listers.jpg');
    $imgMarketplace = asset('assets/images/undraw_wallet_diag.png');
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
                    {!! $ytWord('Complete YouTube Watch Hours and other YouTube tasks.') !!}
                </h1>
                <p class="text-base sm:text-lg text-slate-600 max-w-xl leading-relaxed">
                    Take on YouTube Watch Hours, views, likes, comments, and subscriber tasks when campaigns are open. Follow each task’s instructions, submit your proof, and earn a reward when your work is approved.
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
                        @foreach(['Free to join', 'Clear criteria', 'Rewards after approval', 'Step-by-step instructions'] as $badge)
                            <div class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px] text-emerald-600" aria-hidden="true">check_circle</span>
                                <span>{{ $badge }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="lg:col-span-6 relative order-1 lg:order-2">
                <div class="rounded-2xl overflow-hidden shadow-xl bg-slate-100 max-w-md sm:max-w-lg mx-auto lg:max-w-none">
                    <img src="{{ $imgAgent }}" alt="Agent checking off YouTube tasks on a task list" width="1188" height="1181" class="w-full h-auto aspect-square object-cover" loading="eager">
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
                How {{ $brandName }} tasks work
            </h2>
            <p class="text-base sm:text-lg text-slate-600 mt-3 leading-relaxed">
                Creators launch campaigns, such as YouTube Watch Hours, likes, or comments. When a campaign is open, you can take one of its tasks, complete it by following the instructions, and submit proof for review.
            </p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            @foreach([
                ['step' => 'Step 01', 'stepClass' => 'text-red-600', 'icon' => 'explore', 'iconBg' => 'bg-red-50 text-red-600', 'title' => 'Discover', 'body' => 'Browse available digital campaigns and micro-tasks when they appear in your Agent dashboard.'],
                ['step' => 'Step 02', 'stepClass' => 'text-red-600', 'icon' => 'task_alt', 'iconBg' => 'bg-red-50 text-red-600', 'title' => 'Complete', 'body' => 'Follow the step-by-step instructions for the task, for example watching a video for the required time.'],
                ['step' => 'Step 03', 'stepClass' => 'text-slate-600', 'icon' => 'upload_file', 'iconBg' => 'bg-slate-100 text-slate-600', 'title' => 'Submit', 'body' => 'Complete the task, then submit the required proof from your dashboard.'],
                ['step' => 'Step 04', 'stepClass' => 'text-emerald-700', 'icon' => 'account_balance_wallet', 'iconBg' => 'bg-emerald-50 text-emerald-700', 'title' => 'Earn', 'body' => 'Once your work is reviewed and approved, your reward is added to your wallet. Just opening or autoplaying a video is not enough.'],
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
                'social' => 'M18 16.1c-.8 0-1.4.3-2 .8l-7.1-4.1c.1-.3.1-.5.1-.8s0-.5-.1-.8l7.1-4.1c.5.5 1.2.8 2 .8 1.7 0 3-1.3 3-3s-1.3-3-3-3-3 1.3-3 3c0 .3 0 .5.1.8L7.9 9.9C7.4 9.4 6.7 9.1 6 9.1c-1.7 0-3 1.3-3 3s1.3 3 3 3c.7 0 1.4-.3 2-.8l7.1 4.1c-.1.3-.1.5-.1.8 0 1.7 1.3 3 3 3s3-1.3 3-3-1.3-3-3-3z',
            ];
            $brandColors = [
                'youtube' => '#FF0000',
                'social' => '#6366F1',
            ];
            $tasks = collect($marketplaceTasks ?? []);
        @endphp

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10">
            <div class="lg:col-span-5">
                <div class="rounded-2xl overflow-hidden shadow-lg bg-white border border-slate-100 h-full flex flex-col">
                    <div class="flex-1 flex items-center justify-center p-6 sm:p-8">
                        <img src="{{ $imgMarketplace }}" alt="Agents collecting rewards into a wallet" width="1600" height="1189" class="w-full h-auto max-h-80 object-contain" loading="lazy">
                    </div>
                    <div class="border-t border-slate-100 px-4 py-4 sm:px-5">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-red-600">Tasks</span>
                        <p class="font-display text-base sm:text-lg font-bold text-slate-900 mt-1 leading-snug">Watch Hours and other YouTube tasks when open</p>
                    </div>
                </div>
            </div>
            <div class="lg:col-span-7 flex flex-col gap-4">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-red-600">Marketplace preview</span>
                    <h2 class="font-display text-xl sm:text-2xl font-extrabold text-slate-900 mt-1">
                        Campaigns open right now
                    </h2>
                    <p class="text-sm text-slate-600 mt-1.5 leading-relaxed">
                        Live campaigns from the marketplace. Open tasks change often, and availability is not guaranteed.
                    </p>
                </div>
                <div class="flex flex-col gap-2.5">
                    @forelse($tasks as $task)
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
                    @empty
                        <div class="bg-white rounded-xl px-5 py-10 border border-dashed border-slate-200 text-center">
                            <span class="material-symbols-outlined text-3xl text-slate-300" aria-hidden="true">campaign</span>
                            <p class="font-display text-base font-bold text-slate-900 mt-2">No active campaigns yet</p>
                            <p class="text-sm text-slate-500 mt-1">New tasks will show here as soon as a campaign goes live.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Final CTA --}}
<x-marketing.cta-card eyebrow="Start when you are ready">
    <x-slot:title>Ready to join {{ $brandName }} as an Agent?</x-slot:title>
    <x-slot:description>Register to see available tasks. Rewards are paid when your work meets the instructions and is approved, not for simply opening or autoplaying content.</x-slot:description>
    <x-slot:actions>
        <a href="{{ route('register.agent') }}" class="inline-flex items-center justify-center gap-1.5 font-semibold text-sm bg-white text-red-700 hover:bg-red-50 px-6 py-3 rounded-lg shadow-md transition-colors">
            <span>Become an Agent</span>
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_forward</span>
        </a>
        <a href="{{ route('help') }}" class="inline-flex items-center justify-center font-semibold text-sm bg-white/10 text-white hover:bg-white/20 border border-white/30 px-6 py-3 rounded-lg transition-colors">
            Visit Help Center
        </a>
    </x-slot:actions>
    <x-slot:note>Free registration · Rewards after approval · No guaranteed earnings or task availability</x-slot:note>
</x-marketing.cta-card>
@endsection
