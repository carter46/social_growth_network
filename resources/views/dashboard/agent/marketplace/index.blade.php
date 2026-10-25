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
        <div class="flex flex-col gap-3 sm:gap-4" data-marketplace-list>
            @include('dashboard.agent.marketplace._follow_card', [
                'followSubmission' => $followSubmission ?? null,
                'followSocialCount' => $followSocialCount ?? 0,
            ])
        </div>
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
