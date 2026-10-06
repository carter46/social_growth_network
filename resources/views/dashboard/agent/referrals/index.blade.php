@extends('layouts.dashboard-agent')

@section('title', 'Referrals')

@section('content')
@php
    $tile = 'rounded-xl bg-muted/40 px-4 py-3';
    $formatPercent = fn (float $value) => rtrim(rtrim(number_format($value, 2), '0'), '.').'%';
@endphp
<x-layout.page
    title="Referrals"
    width="full"
    :breadcrumb="[
        ['Agent', route('agent')],
        ['Referrals', null],
    ]"
>
    @unless ($settings['enabled'])
        <x-dashboard.alert type="info" class="mb-4">
            The referral program is paused right now. You can still share your link, and people who sign up with it stay linked to you.
        </x-dashboard.alert>
    @endunless

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-dashboard.card class="lg:col-span-2 space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-text-primary">Invite people and earn</h2>
                <p class="mt-1 text-sm text-text-secondary">
                    Share your link or code. Anyone can sign up with it as an agent or a creator, and you keep earning from them for as long as their account is active.
                </p>
            </div>

            <div
                class="space-y-3"
                x-data="{
                    link: @js($link),
                    code: @js($code),
                    copied: null,
                    async copy(text, key) {
                        const copyFn = window.copyToClipboard;
                        const ok = typeof copyFn === 'function' ? await copyFn(text || '') : false;
                        if (ok) {
                            this.copied = key;
                            setTimeout(() => { if (this.copied === key) this.copied = null; }, 1600);
                            return;
                        }
                        alert(window.copyFailedMessage?.() || 'Unable to copy.');
                    },
                }"
            >
                <div class="{{ $tile }}">
                    <p class="text-xs font-medium uppercase tracking-wide text-text-muted">Referral link</p>
                    <div class="mt-1.5 flex flex-col gap-2 sm:flex-row sm:items-center">
                        <p class="min-w-0 flex-1 break-all text-sm font-medium text-text-primary">{{ $link }}</p>
                        <x-dashboard.button
                            type="button"
                            variant="secondary"
                            size="sm"
                            x-on:click="copy(link, 'link')"
                        >
                            <span x-text="copied === 'link' ? 'Copied' : 'Copy link'">Copy link</span>
                        </x-dashboard.button>
                    </div>
                </div>

                <div class="{{ $tile }}">
                    <p class="text-xs font-medium uppercase tracking-wide text-text-muted">Referral code</p>
                    <div class="mt-1.5 flex flex-col gap-2 sm:flex-row sm:items-center">
                        <p class="flex-1 font-mono text-xl font-bold tracking-widest text-text-primary">{{ $code }}</p>
                        <x-dashboard.button
                            type="button"
                            variant="secondary"
                            size="sm"
                            x-on:click="copy(code, 'code')"
                        >
                            <span x-text="copied === 'code' ? 'Copied' : 'Copy code'">Copy code</span>
                        </x-dashboard.button>
                    </div>
                </div>
            </div>
        </x-dashboard.card>

        <x-dashboard.card class="space-y-3">
            <h2 class="text-lg font-semibold text-text-primary">How you earn</h2>
            <div class="{{ $tile }}">
                <p class="text-xs text-text-muted">When you refer an agent</p>
                <p class="mt-1 text-sm text-text-primary">
                    @if ($settings['enabled'])
                        <span class="font-semibold">{{ $formatPercent($settings['agent_percent']) }}</span> of every task reward they earn.
                    @else
                        A share of every task reward they earn.
                    @endif
                </p>
            </div>
            <div class="{{ $tile }}">
                <p class="text-xs text-text-muted">When you refer a creator</p>
                <p class="mt-1 text-sm text-text-primary">
                    @if ($settings['enabled'])
                        <span class="font-semibold">{{ $formatPercent($settings['creator_percent']) }}</span> of every order they pay for.
                    @else
                        A share of every order they pay for.
                    @endif
                </p>
            </div>
            <p class="text-xs text-text-secondary">Earnings are added to your wallet as soon as they happen.</p>
        </x-dashboard.card>
    </div>

    <div class="mt-4 grid grid-cols-2 gap-3 {{ $stats['pending'] > 0 ? 'lg:grid-cols-5' : 'lg:grid-cols-4' }}">
        <div class="{{ $tile }}">
            <p class="text-xs text-text-muted">Total referred</p>
            <p class="mt-1 text-xl font-bold text-text-primary">{{ number_format($stats['total']) }}</p>
        </div>
        <div class="{{ $tile }}">
            <p class="text-xs text-text-muted">Agents referred</p>
            <p class="mt-1 text-xl font-bold text-text-primary">{{ number_format($stats['agents']) }}</p>
        </div>
        <div class="{{ $tile }}">
            <p class="text-xs text-text-muted">Creators referred</p>
            <p class="mt-1 text-xl font-bold text-text-primary">{{ number_format($stats['creators']) }}</p>
        </div>
        <div class="{{ $tile }}">
            <p class="text-xs text-text-muted">Total referral earnings</p>
            <p class="mt-1 text-xl font-bold text-text-primary">₦{{ number_format($stats['earned'], 2) }}</p>
        </div>
        @if ($stats['pending'] > 0)
            <div class="{{ $tile }} col-span-2 lg:col-span-1">
                <p class="text-xs text-text-muted">Pending earnings</p>
                <p class="mt-1 text-xl font-bold text-text-primary">₦{{ number_format($stats['pending'], 2) }}</p>
                <p class="mt-1 text-xs text-text-secondary">
                    Added to your wallet once you complete <a href="{{ route('agent.account.kyc') }}" class="font-medium text-primary hover:underline">identity verification</a> and create your wallet.
                </p>
            </div>
        @endif
    </div>

    <x-dashboard.card class="mt-4">
        <h2 class="mb-3 text-lg font-semibold text-text-primary">Referral history</h2>

        @if ($referrals->isEmpty())
            <x-dashboard.empty-state
                icon="users"
                title="No referrals yet"
                description="Share your referral link or code. People who sign up with it will show up here."
            />
        @else
            <div class="hidden sm:grid sm:grid-cols-4 gap-3 px-3 pb-2 text-xs font-medium uppercase tracking-wide text-text-muted">
                <span>User</span>
                <span>Type</span>
                <span>Joined</span>
                <span class="text-right">You earned</span>
            </div>
            <ul class="space-y-2">
                @foreach ($referrals as $referral)
                    @php $isAgent = $referral->roles->contains('name', 'agent'); @endphp
                    <li class="{{ $tile }} grid grid-cols-2 gap-x-3 gap-y-1 sm:grid-cols-4 sm:items-center">
                        <span class="min-w-0 truncate text-sm font-semibold text-text-primary">
                            {{ $referral->isAnonymized() ? __('Deleted User') : '@'.$referral->username }}
                        </span>
                        <span class="text-right sm:text-left">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $isAgent ? 'bg-primary/10 text-primary' : 'bg-emerald-500/10 text-emerald-600' }}">
                                {{ $isAgent ? 'Agent' : 'Creator' }}
                            </span>
                        </span>
                        <span class="text-xs text-text-secondary sm:text-sm">
                            <span class="sm:hidden">Joined </span>{{ $referral->created_at?->format('M j, Y') }}
                        </span>
                        <span class="text-right text-sm font-semibold text-text-primary">₦{{ number_format((float) $referral->referral_earned, 2) }}</span>
                    </li>
                @endforeach
            </ul>

            <div class="mt-4">{{ $referrals->links() }}</div>
        @endif
    </x-dashboard.card>
</x-layout.page>
@endsection
