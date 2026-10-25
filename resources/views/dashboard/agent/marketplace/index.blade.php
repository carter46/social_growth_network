@extends('layouts.dashboard-agent')

@section('title', 'Task Marketplace')

@section('content')
<x-layout.page
    title="Task Marketplace"
    width="full"
    :breadcrumb="[
        ['Agent', route('agent')],
        ['Marketplace', null],
    ]"
>
    @if (! ($followCleared ?? true) && ($followSocialCount ?? 0) > 0)
        <x-dashboard.card class="mb-4 border-primary/30 bg-primary/5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-text-primary">Required first: Follow us</p>
                    <p class="mt-1 text-sm text-text-secondary">
                        @if (($followSubmission?->status ?? null) === \App\Models\AgentSocialFollowSubmission::STATUS_SUBMITTED)
                            Your follow screenshots are under review. Paid marketplace tasks stay hidden until this is approved.
                        @elseif (($followSubmission?->status ?? null) === \App\Models\AgentSocialFollowSubmission::STATUS_REJECTED)
                            Your follow task was rejected. Upload new screenshots and submit again before any earning tasks appear.
                        @else
                            Follow our {{ (int) $followSocialCount }} social {{ \Illuminate\Support\Str::plural('account', (int) $followSocialCount) }}, upload a screenshot for each, and get approved. Until then, no other marketplace tasks are shown.
                        @endif
                    </p>
                </div>
                <x-dashboard.button :href="route('agent.social-follow')" size="sm">
                    {{ ($followSubmission?->status ?? null) === \App\Models\AgentSocialFollowSubmission::STATUS_SUBMITTED ? 'View status' : 'Start follow task' }}
                </x-dashboard.button>
            </div>
        </x-dashboard.card>
        <x-dashboard.card>
            <x-dashboard.empty-state
                icon="listings"
                title="Earning tasks locked"
                description="Complete and get approved on the follow-us task above. After that, marketplace campaigns will appear here."
            />
        </x-dashboard.card>
    @elseif ($campaigns->isEmpty())
        <x-dashboard.card>
            <x-dashboard.empty-state
                icon="listings"
                title="No open campaigns"
                description="Check back soon for new tasks from creators."
            />
        </x-dashboard.card>
    @else
        <div class="flex flex-col gap-3 sm:gap-4" data-marketplace-list>
            @foreach ($campaigns as $campaign)
                @include('dashboard.agent.marketplace._card', ['campaign' => $campaign])
            @endforeach
        </div>
        <div class="mt-4">{{ $campaigns->links() }}</div>
    @endif
</x-layout.page>
@endsection
