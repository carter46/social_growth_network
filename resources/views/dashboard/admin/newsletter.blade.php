@extends('layouts.dashboard-admin')

@section('title', 'Newsletter')

@section('content')
<x-layout.page
    title="Newsletter"
    subtitle="People who joined the newsletter from the website. {{ number_format($activeCount) }} subscribed."
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Newsletter', null],
    ]"
>
    <x-slot:actions>
        <x-dashboard.button variant="secondary" size="md" :href="route('admin.newsletter.export', array_filter($filters))">Export CSV</x-dashboard.button>
    </x-slot:actions>

    <x-dashboard.table
        :empty="$subscribers->isEmpty()"
        empty-title="No subscribers yet"
        empty-description="Emails submitted through the newsletter form will appear here."
        empty-icon="mail"
        striped
    >
        <x-slot:filters>
            <x-dashboard.filter-bar>
                <form method="GET" class="contents flex flex-wrap gap-3 items-end">
                    <div class="min-w-[14rem] flex-1">
                        <x-dashboard.input name="q" type="text" label="Search email" :value="$filters['q'] ?? ''" placeholder="name@example.com" />
                    </div>
                    <x-dashboard.button type="submit" variant="secondary" size="md">Search</x-dashboard.button>
                </form>
            </x-dashboard.filter-bar>
        </x-slot:filters>

        <x-slot:head>
            <x-dashboard.th>Email</x-dashboard.th>
            <x-dashboard.th>Status</x-dashboard.th>
            <x-dashboard.th>Source</x-dashboard.th>
            <x-dashboard.th>Subscribed</x-dashboard.th>
        </x-slot:head>

        @foreach ($subscribers as $subscriber)
            <tr class="hover:bg-muted/50 align-top border-b border-border-default">
                <x-dashboard.td>
                    <div class="text-sm text-text-primary break-all">{{ $subscriber->email }}</div>
                </x-dashboard.td>
                <x-dashboard.td>
                    <span class="text-sm font-medium {{ $subscriber->isActive() ? 'text-success' : 'text-text-muted' }}">
                        {{ $subscriber->isActive() ? 'Subscribed' : 'Unsubscribed' }}
                    </span>
                </x-dashboard.td>
                <x-dashboard.td class="text-sm text-text-muted">{{ $subscriber->source ?: 'website' }}</x-dashboard.td>
                <x-dashboard.td>
                    <div class="text-sm text-text-primary whitespace-nowrap">{{ $subscriber->subscribed_at?->format('M j, Y') ?? '-' }}</div>
                    <div class="text-xs text-text-muted">{{ $subscriber->subscribed_at?->format('H:i') }}</div>
                </x-dashboard.td>
            </tr>
        @endforeach
    </x-dashboard.table>

    <x-slot:pagination>
        <x-dashboard.pagination :paginator="$subscribers" />
    </x-slot:pagination>
</x-layout.page>
@endsection
