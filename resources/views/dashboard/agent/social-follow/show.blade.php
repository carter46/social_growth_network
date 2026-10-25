@extends('layouts.dashboard-agent')

@section('title', 'Follow us')

@section('content')
@php
    $status = $submission?->status;
    $canEdit = $submission?->canEditProofs() ?? false;
    $allDone = $submission && $submission->proofs->isNotEmpty() && $submission->proofs->every(fn ($p) => $p->isComplete());
@endphp
<x-layout.page
    title="Follow us"
    subtitle="This is required before you can earn on any other marketplace task. Follow each account, take a clear screenshot, and upload it."
    width="full"
    :breadcrumb="[
        ['Agent', route('agent')],
        ['Marketplace', route('agent.marketplace')],
        ['Follow us', null],
    ]"
>
    @if (session('status'))
        <x-dashboard.alert type="success" class="mb-4">{{ session('status') }}</x-dashboard.alert>
    @endif
    @if (session('error'))
        <x-dashboard.alert type="danger" class="mb-4">{{ session('error') }}</x-dashboard.alert>
    @endif

    <x-dashboard.alert type="warning" class="mb-4">
        Keep following these accounts. If you unfollow later, you may lose the ability to earn on this platform.
    </x-dashboard.alert>

    @if ($status === \App\Models\AgentSocialFollowSubmission::STATUS_APPROVED)
        <x-dashboard.alert type="success" class="mb-4">
            Approved. You can start earning on the <a href="{{ route('agent.marketplace') }}" class="underline font-medium">Task Marketplace</a>.
        </x-dashboard.alert>
    @elseif ($status === \App\Models\AgentSocialFollowSubmission::STATUS_SUBMITTED)
        <x-dashboard.alert type="info" class="mb-4">
            Submitted. We are reviewing your screenshots. You will get an email when this is approved. Other earning tasks stay locked until then.
        </x-dashboard.alert>
    @elseif ($status === \App\Models\AgentSocialFollowSubmission::STATUS_REJECTED)
        <x-dashboard.alert type="danger" class="mb-4">
            Not approved{{ $submission->rejection_reason ? ': '.$submission->rejection_reason : '.' }} Upload new screenshots and submit again.
        </x-dashboard.alert>
    @endif

    <div class="space-y-4">
        @forelse (($submission?->proofs ?? collect()) as $proof)
            <x-dashboard.card>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-full border border-border-default bg-elevated">
                            @if ($proof->logoUrl())
                                <img src="{{ $proof->logoUrl() }}" alt="{{ $proof->displayName() }} logo" class="h-full w-full rounded-full object-cover">
                            @else
                                <span class="text-base font-bold text-text-primary">{{ mb_strtoupper(mb_substr($proof->platform, 0, 1)) }}</span>
                            @endif
                        </span>
                        <div class="min-w-0 space-y-0.5">
                            <p class="text-base font-semibold text-text-primary">{{ $proof->displayName() }}</p>
                            <a href="{{ $proof->url }}" target="_blank" rel="noopener noreferrer" class="block text-sm text-primary hover:underline break-all">{{ $proof->url }}</a>
                        </div>
                    </div>
                    <x-dashboard.badge :status="$proof->isComplete() ? 'success' : 'pending'">
                        {{ $proof->isComplete() ? 'Screenshot uploaded' : 'Screenshot needed' }}
                    </x-dashboard.badge>
                </div>

                <ol class="mt-4 list-decimal space-y-1.5 pl-5 text-sm text-text-secondary">
                    <li>Tap <span class="font-medium text-text-primary">Open {{ $proof->displayName() }}</span> and follow the page or account.</li>
                    <li>Take a screenshot that clearly shows you followed.</li>
                    <li>Upload that screenshot below, then continue to the next account.</li>
                </ol>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <x-dashboard.button :href="$proof->url" target="_blank" rel="noopener noreferrer" size="sm">
                        Open {{ $proof->displayName() }}
                    </x-dashboard.button>
                </div>

                @if ($proof->proof_url)
                    <div class="mt-3">
                        <a href="{{ $proof->proof_url }}" target="_blank" rel="noopener" class="inline-block overflow-hidden rounded-xl border border-border-default">
                            <img src="{{ $proof->proof_url }}" alt="Follow proof for {{ $proof->displayName() }}" class="max-h-48 w-auto object-contain">
                        </a>
                    </div>
                @endif

                @if ($canEdit)
                    <form method="POST" action="{{ route('agent.social-follow.proof', $proof) }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                        @csrf
                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-text-secondary" for="proof-{{ $proof->id }}">Follow screenshot</label>
                            <input
                                id="proof-{{ $proof->id }}"
                                type="file"
                                name="proof"
                                accept="image/jpeg,image/png,image/webp"
                                required
                                class="block w-full text-sm text-text-secondary file:mr-3 file:rounded-lg file:border-0 file:bg-elevated file:px-3 file:py-2 file:text-sm file:text-text-primary"
                            />
                            <p class="text-xs text-text-muted">JPG, PNG, or WEBP, up to 2 MB. The image must show that you followed {{ $proof->displayName() }}.</p>
                        </div>
                        <x-dashboard.button type="submit" size="sm">
                            {{ $proof->isComplete() ? 'Replace screenshot' : 'Upload screenshot' }}
                        </x-dashboard.button>
                    </form>
                @endif
            </x-dashboard.card>
        @empty
            <x-dashboard.card>
                <x-dashboard.empty-state
                    icon="listings"
                    title="No social accounts"
                    description="The platform has not published social links yet."
                />
            </x-dashboard.card>
        @endforelse
    </div>

    @if ($canEdit && ($submission?->proofs?->isNotEmpty() ?? false))
        @if ($allDone)
            <form method="POST" action="{{ route('agent.social-follow.submit') }}" class="mt-6">
                @csrf
                <x-dashboard.button type="submit">Submit for review</x-dashboard.button>
            </form>
        @else
            <p class="mt-6 text-sm text-text-muted">Upload a screenshot for every account before submitting.</p>
        @endif
    @endif
</x-layout.page>
@endsection
