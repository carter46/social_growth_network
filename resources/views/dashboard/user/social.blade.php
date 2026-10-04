@extends('layouts.dashboard-user')

@section('title', 'Social Services')

@section('content')
<x-layout.page
    title="Social Services"
    width="full"
    :breadcrumb="[
        ['Dashboard', route('dashboard')],
        ['Social Services', null],
    ]"
>
    <x-dashboard.card :padding="false">
        @if (isset($items) && $items->isNotEmpty())
            <ul class="divide-y divide-border-default">
                @foreach ($items as $item)
                    <li class="px-6 py-4 text-text-primary">{{ $item->name ?? $item['name'] ?? '-' }}</li>
                @endforeach
            </ul>
        @else
            <x-dashboard.empty
                icon="group"
                title="No social services linked yet"
                description="When this feature is enabled, your connected accounts will appear here."
            />
        @endif
    </x-dashboard.card>
</x-layout.page>
@endsection
