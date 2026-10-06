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
    @if ($campaigns->isEmpty())
        <x-dashboard.card>
            <x-dashboard.empty-state
                icon="listings"
                title="No open campaigns"
                description="Check back soon for new tasks from creators."
            />
        </x-dashboard.card>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-5">
            @foreach ($campaigns as $campaign)
                @include('dashboard.agent.marketplace._card', ['campaign' => $campaign])
            @endforeach
        </div>
    @endif

    <div class="mt-4">{{ $campaigns->links() }}</div>
</x-layout.page>
@endsection
