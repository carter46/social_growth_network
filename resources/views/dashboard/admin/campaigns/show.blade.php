@extends('layouts.dashboard-admin')

@section('title', 'Campaign')

@php
    $naira = fn ($amount) => '₦'.number_format((float) $amount, 2);
    $nairaUnit = function ($amount) {
        $value = (float) $amount;
        $decimals = round($value, 2) == $value ? 2 : 4;

        return '₦'.number_format($value, $decimals);
    };
    $tile = 'rounded-xl bg-muted/40 px-4 py-3';
    $order = $campaign->order;
    $pricingVariant = $campaign->variant;
    $statusHelp = [
        'draft' => 'Hidden. Agents cannot join, submit or be paid.',
        'pending_review' => 'Hidden while you review it. Agents cannot join, submit or be paid.',
        'active' => 'Live. New agents can join, and submissions are verified and paid.',
        'paused' => 'No new agents can join. Agents who already started can still finish and be paid.',
        'completed' => 'Closed. Set automatically when all units are delivered.',
        'rejected' => 'Closed. Agents cannot join, submit or be paid.',
        'suspended' => 'Frozen. Agents cannot join, submit or be paid until you change the status.',
        'cancelled' => $budget
            ? 'Closed to new agents. Tasks already in progress can finish or expire, then the creator is refunded the price of every unit never completed.'
            : 'Closed. Agents cannot join, submit or be paid. This older campaign has no held budget, so no refund is sent automatically.',
    ];
@endphp

@section('content')
<x-layout.page
    title="{{ $campaign->title }}"
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Campaigns', route('admin.campaigns')],
        [$campaign->title, null],
    ]"
>
    @if (session('status'))
        <x-dashboard.alert type="success" class="mb-4">{{ session('status') }}</x-dashboard.alert>
    @endif
    @if (session('error'))
        <x-dashboard.alert type="error" class="mb-4">{{ session('error') }}</x-dashboard.alert>
    @endif
    @if (! $budget && \App\Services\Campaigns\CampaignBudgetService::isUnfunded($campaign))
        <x-dashboard.alert type="warning" class="mb-4" title="Agent budget not held">
            The platform wallet could not cover {{ $naira($campaign->meta['funding_shortfall'] ?? 0) }} when this campaign went live, so rewards are paid from platform revenue. Top up the platform wallet, then run <code>php artisan campaigns:backfill-budgets --dry-run</code> to review.
        </x-dashboard.alert>
    @endif

    <x-dashboard.card class="mb-4">
        <dl class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div class="{{ $tile }}">
                <dt class="text-text-muted">Creator</dt>
                <dd class="mt-1 font-medium text-text-primary break-all">
                    @if ($campaign->creator)
                        <a href="{{ route('admin.users.show', $campaign->creator) }}" class="text-primary hover:underline">{{ $campaign->creator->email }}</a>
                    @else
                        -
                    @endif
                </dd>
            </div>
            <div class="{{ $tile }}">
                <dt class="text-text-muted">Status</dt>
                <dd class="mt-1"><x-dashboard.badge :status="$campaign->status">{{ $campaign->statusLabel() }}</x-dashboard.badge></dd>
            </div>
            <div class="{{ $tile }}">
                <dt class="text-text-muted">Progress</dt>
                <dd class="mt-1 font-medium text-text-primary">{{ number_format($campaign->completed_count) }} / {{ number_format($campaign->quantity) }} {{ $unitLabel }}</dd>
            </div>
            <div class="{{ $tile }}">
                <dt class="text-text-muted">Created</dt>
                <dd class="mt-1 font-medium text-text-primary">{{ $campaign->created_at?->format('M j, Y g:i A') }}</dd>
            </div>
        </dl>
    </x-dashboard.card>

    <div class="mb-4 grid gap-4 lg:grid-cols-2">
        <x-dashboard.card>
            <h2 class="mb-3 text-sm font-semibold">What the creator bought</h2>
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div class="{{ $tile }}">
                    <dt class="text-text-muted">Product</dt>
                    <dd class="mt-1 font-medium text-text-primary">{{ $campaign->product?->title ?? $campaign->title }}</dd>
                </div>
                <div class="{{ $tile }}">
                    <dt class="text-text-muted">Engagement</dt>
                    <dd class="mt-1 font-medium text-text-primary">{{ $profile->isMultiTask() ? $profile->sequenceLabel(' + ') : ($metric?->label() ?? '-') }}</dd>
                </div>
                <div class="{{ $tile }}">
                    <dt class="text-text-muted">Units bought</dt>
                    <dd class="mt-1 font-medium text-text-primary">{{ number_format($campaign->quantity) }} {{ $unitLabel }}</dd>
                </div>
                <div class="{{ $tile }}">
                    <dt class="text-text-muted">Price per unit</dt>
                    <dd class="mt-1 font-medium text-text-primary">{{ $nairaUnit($finance['unit_cost']) }}</dd>
                    @if (($campaign->orderItem?->options['pricing_units'] ?? null) && ($campaign->orderItem?->options['pricing_price'] ?? null))
                        <p class="mt-0.5 text-xs text-text-muted">
                            Priced at ₦{{ number_format((float) $campaign->orderItem->options['pricing_price'], 2) }} per {{ number_format((int) $campaign->orderItem->options['pricing_units']) }}
                        </p>
                    @endif
                </div>
                <div class="{{ $tile }}">
                    <dt class="text-text-muted">Total cost</dt>
                    <dd class="mt-1 text-base font-semibold text-text-primary">{{ $naira($finance['total_cost']) }}</dd>
                </div>
                <div class="{{ $tile }}">
                    <dt class="text-text-muted">Order</dt>
                    <dd class="mt-1 font-medium text-text-primary">
                        @if ($order)
                            <a href="{{ route('admin.orders.show', $order) }}" class="text-primary hover:underline">{{ $order->reference ?? '#'.$order->id }}</a>
                            <span class="block text-xs text-text-muted">
                                {{ ucfirst(str_replace('_', ' ', (string) $order->status)) }}@if ($order->payment_method) · {{ ucfirst(str_replace('_', ' ', (string) $order->payment_method)) }}@endif
                            </span>
                        @else
                            -
                        @endif
                    </dd>
                </div>
                <div class="{{ $tile }} sm:col-span-2">
                    <dt class="text-text-muted">Campaign link</dt>
                    <dd class="mt-1 font-medium text-text-primary break-all">
                        @if ($campaign->target_url)
                            <a href="{{ $campaign->target_url }}" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline">{{ $campaign->target_url }}</a>
                        @else
                            -
                        @endif
                    </dd>
                </div>
            </dl>
        </x-dashboard.card>

        <x-dashboard.card>
            <h2 class="mb-3 text-sm font-semibold">Agent payouts</h2>
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div class="{{ $tile }}">
                    <dt class="text-text-muted">Agent reward per unit</dt>
                    <dd class="mt-1 font-medium text-text-primary">{{ $naira($finance['agent_reward']) }}</dd>
                    @if ($campaign->product?->agent_reward_percent)
                        <p class="mt-0.5 text-xs text-text-muted">{{ rtrim(rtrim(number_format((float) $campaign->product->agent_reward_percent, 2), '0'), '.') }}% of the unit price</p>
                    @endif
                </div>
                <div class="{{ $tile }}">
                    <dt class="text-text-muted">Total agent budget</dt>
                    <dd class="mt-1 font-medium text-text-primary">{{ $naira($finance['agent_budget']) }}</dd>
                    <p class="mt-0.5 text-xs text-text-muted">{{ number_format($campaign->quantity) }} × {{ $naira($finance['agent_reward']) }}</p>
                </div>
                <div class="{{ $tile }}">
                    <dt class="text-text-muted">Paid to agents so far</dt>
                    <dd class="mt-1 text-base font-semibold text-success">{{ $naira($finance['paid_to_agents']) }}</dd>
                    <p class="mt-0.5 text-xs text-text-muted">{{ number_format($counts['paid']) }} {{ $counts['paid'] === 1 ? 'agent' : 'agents' }} paid</p>
                </div>
                <div class="{{ $tile }}">
                    <dt class="text-text-muted">Awaiting review</dt>
                    <dd class="mt-1 font-medium text-text-primary">{{ $naira($finance['awaiting_review']) }}</dd>
                    <p class="mt-0.5 text-xs text-text-muted">{{ number_format($counts['awaiting_review']) }} submitted, {{ number_format($counts['in_progress']) }} in progress</p>
                </div>
                <div class="{{ $tile }}">
                    <dt class="text-text-muted">Agent budget left</dt>
                    <dd class="mt-1 font-medium text-text-primary">{{ $naira($finance['agent_budget_left']) }}</dd>
                </div>
                <div class="{{ $tile }}">
                    <dt class="text-text-muted">Platform share</dt>
                    <dd class="mt-1 font-medium text-text-primary">{{ $naira($finance['platform_share']) }}</dd>
                    <p class="mt-0.5 text-xs text-text-muted">Total cost minus total agent budget</p>
                </div>
            </dl>

            <h3 class="mb-2 mt-4 text-xs font-semibold uppercase tracking-wide text-text-muted">Held budget</h3>
            @if ($budget)
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div class="{{ $tile }}">
                        <dt class="text-text-muted">Held</dt>
                        <dd class="mt-1 font-medium text-text-primary">{{ $naira($budget->funded_amount) }}</dd>
                        <p class="mt-0.5 text-xs text-text-muted">{{ ucfirst($budget->status) }}</p>
                    </div>
                    <div class="{{ $tile }}">
                        <dt class="text-text-muted">Paid from hold</dt>
                        <dd class="mt-1 font-medium text-success">{{ $naira($budget->paid_amount) }}</dd>
                    </div>
                    <div class="{{ $tile }}">
                        <dt class="text-text-muted">Refunded to creator</dt>
                        <dd class="mt-1 font-medium text-text-primary">{{ $naira($budget->refunded_amount) }}</dd>
                    </div>
                    <div class="{{ $tile }}">
                        <dt class="text-text-muted">Remaining in hold</dt>
                        <dd class="mt-1 font-medium text-text-primary">{{ $naira($budget->remaining()) }}</dd>
                        @if ((float) $budget->released_amount > 0)
                            <p class="mt-0.5 text-xs text-text-muted">{{ $naira($budget->released_amount) }} released to revenue</p>
                        @endif
                    </div>
                </dl>
            @else
                <p class="text-xs text-text-muted">No budget is held for this campaign (created before budget holds, or the platform wallet could not cover it). Rewards use the older payout path. Run <code>php artisan campaigns:backfill-budgets --dry-run</code> to review.</p>
            @endif
        </x-dashboard.card>
    </div>

    <x-dashboard.card class="mb-4">
        <h2 class="mb-3 text-sm font-semibold">What agents do (snapshot)</h2>
        <dl class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div class="{{ $tile }} sm:col-span-2">
                <dt class="text-text-muted">Tasks, in order</dt>
                <dd class="mt-1 font-medium text-text-primary">
                    @foreach ($profile->tasks() as $i => $task)
                        <span class="mr-1 inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary">{{ $i + 1 }}. {{ $task->shortLabel() }}</span>
                    @endforeach
                    @unless ($profile->usesStepFlow())
                        <span class="block text-xs text-text-muted">Single-task flow</span>
                    @endunless
                </dd>
            </div>
            <div class="{{ $tile }}">
                <dt class="text-text-muted">Time to complete</dt>
                <dd class="mt-1 font-medium text-text-primary">{{ $campaign->completion_minutes ? $campaign->completion_minutes.' min' : 'No deadline' }}</dd>
                <p class="mt-0.5 text-xs text-text-muted">{{ number_format($counts['expired']) }} expired</p>
            </div>
            <div class="{{ $tile }}">
                <dt class="text-text-muted">Watch time</dt>
                <dd class="mt-1 font-medium text-text-primary">{{ $campaign->estimated_minutes ? $campaign->estimated_minutes.' min' : '-' }}</dd>
            </div>
            @if ($campaign->video_id)
                <div class="{{ $tile }}">
                    <dt class="text-text-muted">Video ID</dt>
                    <dd class="mt-1 font-mono text-xs text-text-primary break-all">{{ $campaign->video_id }}</dd>
                </div>
            @endif
            @if ($campaign->channel_url || $campaign->channel_id)
                <div class="{{ $tile }}">
                    <dt class="text-text-muted">Channel</dt>
                    <dd class="mt-1 text-text-primary break-all">
                        @if ($campaign->channel_url)
                            <a href="{{ $campaign->channel_url }}" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline">{{ $campaign->channel_url }}</a>
                        @endif
                        @if ($campaign->channel_id)
                            <span class="block font-mono text-xs text-text-muted">{{ $campaign->channel_id }}</span>
                        @endif
                    </dd>
                </div>
            @endif
            @if ($profile->usesStepFlow())
                <div class="{{ $tile }}">
                    <dt class="text-text-muted">Video and channel match</dt>
                    <dd class="mt-1 font-medium {{ ($campaign->meta['match_verified'] ?? false) ? 'text-success' : 'text-warning' }}">
                        {{ ($campaign->meta['match_verified'] ?? false) ? 'Verified with the YouTube API' : 'Unverified match' }}
                    </dd>
                </div>
            @endif
            @if ($campaign->comment_instructions)
                <div class="{{ $tile }} sm:col-span-2 lg:col-span-4">
                    <dt class="text-text-muted">Comment instructions</dt>
                    <dd class="mt-1 whitespace-pre-line text-text-primary">{{ $campaign->comment_instructions }}</dd>
                </div>
            @endif
        </dl>
    </x-dashboard.card>

    <x-dashboard.card class="mb-4">
        <div x-data="{ selected: @js($campaign->status), help: @js($statusHelp) }">
        <form method="POST" action="{{ route('admin.campaigns.status', $campaign) }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <label class="mb-1 block text-xs font-medium text-text-secondary" for="status">Update status</label>
                <select id="status" name="status" x-model="selected" class="rounded-lg border border-border-default bg-surface px-3 py-2 text-sm">
                    @foreach (\App\Models\Campaign::STATUS_LABELS as $value => $label)
                        <option value="{{ $value }}" @selected($campaign->status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <x-dashboard.button type="submit" size="sm">Save</x-dashboard.button>
        </form>
        <p class="mt-2 text-xs text-text-muted" x-text="help[selected] ?? ''">{{ $statusHelp[$campaign->status] ?? '' }}</p>
        </div>
    </x-dashboard.card>

    <h2 class="mb-2 text-sm font-semibold">Participations</h2>
    <x-dashboard.table :empty="$campaign->participations->isEmpty()" empty-title="None yet" empty-icon="users" striped>
        <x-slot:head>
            <x-dashboard.th>Agent</x-dashboard.th>
            <x-dashboard.th>Status</x-dashboard.th>
            <x-dashboard.th>Reward</x-dashboard.th>
            <x-dashboard.th>Started</x-dashboard.th>
            <x-dashboard.th>Paid</x-dashboard.th>
            <x-dashboard.th></x-dashboard.th>
        </x-slot:head>
        @foreach ($campaign->participations as $participation)
            <tr>
                <x-dashboard.td>{{ $participation->agent?->email }}</x-dashboard.td>
                <x-dashboard.td><x-dashboard.badge :status="$participation->status" /></x-dashboard.td>
                <x-dashboard.td>{{ $naira($participation->reward_amount) }}</x-dashboard.td>
                <x-dashboard.td>{{ $participation->started_at?->format('M j, Y') ?? '-' }}</x-dashboard.td>
                <x-dashboard.td>{{ $participation->paid_at?->format('M j, Y') ?? '-' }}</x-dashboard.td>
                <x-dashboard.td>
                    @if (in_array($participation->status, ['submitted', 'under_review', 'verifying'], true))
                        <x-dashboard.button :href="route('admin.verifications.show', $participation)" variant="link" size="xs">Review</x-dashboard.button>
                    @elseif ($participation->status === 'started')
                        <form method="POST" action="{{ route('admin.campaigns.participations.release', [$campaign, $participation]) }}"
                              onsubmit="return confirm('Release this task? The agent loses it, nothing is paid, and the slot opens for other agents.');">
                            @csrf
                            <x-dashboard.button type="submit" variant="link" size="xs">Release</x-dashboard.button>
                        </form>
                    @endif
                </x-dashboard.td>
            </tr>
        @endforeach
    </x-dashboard.table>
</x-layout.page>
@endsection
