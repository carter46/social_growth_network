@extends('layouts.dashboard-admin')

@section('title', 'Review follow task')

@section('content')
<x-layout.page
    title="Review follow task"
    :subtitle="$submission->user?->name.' · '.$submission->user?->email"
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Follow-us reviews', route('admin.social-follows')],
        ['Review', null],
    ]"
>
    @if (session('error'))
        <x-dashboard.alert type="danger" class="mb-4">{{ session('error') }}</x-dashboard.alert>
    @endif

    <x-dashboard.card class="mb-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm text-text-secondary">Status</p>
                <p class="font-semibold text-text-primary">{{ $submission->label() }}</p>
            </div>
            <div>
                <p class="text-sm text-text-secondary">Submitted</p>
                <p class="font-medium text-text-primary">{{ $submission->submitted_at?->toDayDateTimeString() ?? '—' }}</p>
            </div>
        </div>
    </x-dashboard.card>

    <div class="space-y-4">
        @foreach ($submission->proofs as $proof)
            <x-dashboard.card>
                <div class="flex flex-wrap items-center gap-3 mb-3">
                    <span class="inline-flex h-10 w-10 items-center justify-center overflow-hidden rounded-full border border-border-default bg-elevated">
                        @if ($proof->logoUrl())
                            <img src="{{ $proof->logoUrl() }}" alt="" class="h-full w-full rounded-full object-cover">
                        @else
                            <span class="text-xs font-bold">{{ mb_strtoupper(mb_substr($proof->platform, 0, 1)) }}</span>
                        @endif
                    </span>
                    <div>
                        <p class="font-semibold text-text-primary">{{ $proof->displayName() }}</p>
                        <a href="{{ $proof->url }}" target="_blank" rel="noopener" class="text-sm text-primary hover:underline">{{ $proof->url }}</a>
                    </div>
                </div>
                @if ($proof->proof_url)
                    <a href="{{ $proof->proof_url }}" target="_blank" rel="noopener" class="inline-block overflow-hidden rounded-xl border border-border-default">
                        <img src="{{ $proof->proof_url }}" alt="Proof" class="max-h-80 w-auto object-contain">
                    </a>
                @else
                    <p class="text-sm text-danger">No screenshot uploaded.</p>
                @endif
            </x-dashboard.card>
        @endforeach
    </div>

    @if ($submission->isSubmitted())
        <div class="mt-6 flex flex-wrap items-start gap-4">
            <form method="POST" action="{{ route('admin.social-follows.approve', $submission) }}">
                @csrf
                <x-dashboard.button type="submit" variant="primary">Approve</x-dashboard.button>
            </form>
            <form method="POST" action="{{ route('admin.social-follows.reject', $submission) }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <div class="space-y-1">
                    <label for="reason" class="block text-sm font-medium text-text-secondary">Reject reason</label>
                    <input id="reason" name="reason" required maxlength="500" class="rounded-lg border border-border-default bg-elevated px-3 py-2 text-sm min-w-[16rem]" placeholder="Why was this rejected?" />
                </div>
                <x-dashboard.button type="submit" variant="danger">Reject</x-dashboard.button>
            </form>
        </div>
    @endif
</x-layout.page>
@endsection
