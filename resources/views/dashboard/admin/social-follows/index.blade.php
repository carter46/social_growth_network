@extends('layouts.dashboard-admin')

@section('title', 'Follow-us reviews')

@section('content')
<x-layout.page
    title="Follow-us reviews"
    subtitle="Agents must follow platform socials and upload screenshots before earning on marketplace tasks."
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Follow-us reviews', null],
    ]"
>
    @if (session('status'))
        <x-dashboard.alert type="success" class="mb-4">{{ session('status') }}</x-dashboard.alert>
    @endif
    @if (session('error'))
        <x-dashboard.alert type="danger" class="mb-4">{{ session('error') }}</x-dashboard.alert>
    @endif

    @if ($queue->isEmpty())
        <x-dashboard.card>
            <x-dashboard.empty-state
                icon="verified"
                title="No follow tasks waiting"
                description="Submitted follow-us proofs will show up here."
            />
        </x-dashboard.card>
    @else
        <div class="overflow-x-auto rounded-xl border border-border-default">
            <table class="min-w-full text-sm">
                <thead class="bg-elevated text-left text-text-secondary">
                    <tr>
                        <th class="px-4 py-3 font-medium">Agent</th>
                        <th class="px-4 py-3 font-medium">Accounts</th>
                        <th class="px-4 py-3 font-medium">Submitted</th>
                        <th class="px-4 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-default">
                    @foreach ($queue as $submission)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium text-text-primary">{{ $submission->user?->name }}</p>
                                <p class="text-text-muted">{{ $submission->user?->email }}</p>
                            </td>
                            <td class="px-4 py-3 text-text-secondary">{{ $submission->proofs->count() }}</td>
                            <td class="px-4 py-3 text-text-secondary">{{ $submission->submitted_at?->diffForHumans() }}</td>
                            <td class="px-4 py-3 text-right">
                                <x-dashboard.button :href="route('admin.social-follows.show', $submission)" size="sm">Review</x-dashboard.button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $queue->links() }}</div>
    @endif
</x-layout.page>
@endsection
