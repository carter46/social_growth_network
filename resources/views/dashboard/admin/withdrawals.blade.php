@extends('layouts.dashboard-admin')

@section('title', 'Withdrawals')

@section('content')
<x-layout.page
    title="Withdrawals"
    subtitle="Review withdrawal requests, then approve and send or reject with a reason."
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Withdrawals', null],
    ]"
>
    @if ($errors->any())
        <x-dashboard.alert type="danger">{{ $errors->first() }}</x-dashboard.alert>
    @endif
    @if (session('status'))
        <x-dashboard.alert type="success">{{ session('status') }}</x-dashboard.alert>
    @endif
    @if (session('error'))
        <x-dashboard.alert type="danger">{{ session('error') }}</x-dashboard.alert>
    @endif

    @php
        $bachsBalance = $balances['bachs']['balance'] ?? null;
        $bachsEmpty = $bachsBalance !== null && bccomp((string) $bachsBalance, '0', 2) <= 0;
    @endphp
    @if (! $gatewaysConfigured)
        <x-dashboard.alert type="warning" class="mb-4">No payout gateway is configured. Approving a withdrawal marks it paid without sending money; pay it manually first.</x-dashboard.alert>
    @elseif ($balances !== [])
        <x-dashboard.alert :type="$bachsEmpty ? 'warning' : 'info'" class="mb-4">
            @foreach ($balances as $b)
                <strong>{{ $b['name'] }}</strong> {{ $b['balance'] !== null ? money($b['balance'], 2) : '—' }}@if (! $loop->last) · @endif
            @endforeach
        </x-dashboard.alert>
    @endif

    <x-dashboard.table :empty="$withdrawals->isEmpty()" empty-title="No withdrawals" empty-description="Withdrawal requests will appear here." empty-icon="withdraw" striped :min-height="false">
        <x-slot:head>
            <x-dashboard.th>User</x-dashboard.th>
            <x-dashboard.th>Amount</x-dashboard.th>
            <x-dashboard.th class="hidden sm:table-cell">Destination</x-dashboard.th>
            <x-dashboard.th>Status</x-dashboard.th>
            <x-dashboard.th>Actions</x-dashboard.th>
        </x-slot:head>
        @foreach ($withdrawals as $w)
            <tr>
                <x-dashboard.td class="min-w-0 max-w-[9rem] sm:max-w-none">
                    <div class="font-medium text-text-primary">{{ \App\Models\User::labelFor($w->user) }}</div>
                    <div class="mt-0.5 font-mono text-[10px] leading-snug text-text-muted break-all">
                        <a href="{{ route('admin.withdrawals.show', $w) }}" class="underline">{{ $w->reference }}</a>
                    </div>
                </x-dashboard.td>
                <x-dashboard.td class="whitespace-nowrap">
                    {{ money($w->amount, 2) }}
                    @if (bccomp((string) $w->fee_amount, '0', 2) > 0)
                        <div class="text-xs text-text-muted">net {{ money($w->netPayoutAmount(), 2) }}</div>
                    @endif
                </x-dashboard.td>
                <x-dashboard.td class="hidden text-sm sm:table-cell">
                    {{ $w->destinationLabel() }}<br>
                    <span class="text-text-muted">{{ $w->bank_name }} · {{ $w->maskedAccountNumber() }} · {{ $w->account_name }}</span>
                    @if ($w->payoutAccount && ! $w->payoutAccount->isResolved())
                        <div class="text-xs font-medium text-warning">Account not verified by gateway</div>
                    @endif
                </x-dashboard.td>
                <x-dashboard.td>
                    <x-dashboard.badge :status="$w->status" />
                    @if ($w->internal_status && $w->internal_status !== $w->status)
                        <div class="mt-1 text-xs text-text-muted">{{ str_replace('_', ' ', $w->internal_status) }}</div>
                    @endif
                    @if ($w->latestAttempt)
                        <div class="mt-1 text-xs text-text-muted">{{ $w->latestAttempt->gateway_key }}: {{ $w->latestAttempt->status }}</div>
                    @endif
                </x-dashboard.td>
                <x-dashboard.td>
                    @if ($w->status === 'pending' || $w->internal_status === 'pending_review')
                        <div
                            x-data="{
                                open: false,
                                mode: 'review',
                                reason: '',
                                submitting: false,
                                previouslyFocused: null,
                                openModal() {
                                    this.previouslyFocused = document.activeElement;
                                    this.mode = 'review';
                                    this.reason = '';
                                    this.submitting = false;
                                    this.open = true;
                                    this.$nextTick(() => this.$refs.panel?.focus?.());
                                },
                                closeModal() {
                                    if (! this.open || this.submitting) return;
                                    this.open = false;
                                    this.mode = 'review';
                                    this.reason = '';
                                    this.$nextTick(() => this.previouslyFocused?.focus?.());
                                },
                                startReject() {
                                    this.mode = 'reject';
                                    this.$nextTick(() => this.$refs.reason?.focus?.());
                                },
                                cancelReject() {
                                    this.mode = 'review';
                                    this.reason = '';
                                },
                            }"
                            @keydown.escape.window="if (open) closeModal()"
                        >
                            <x-dashboard.button type="button" size="sm" variant="secondary" @click="openModal()">View</x-dashboard.button>

                            <template x-teleport="body">
                                <div
                                    x-show="open"
                                    x-cloak
                                    class="fixed inset-0 z-[90] flex items-end justify-center p-3 sm:items-center sm:p-4"
                                    style="overflow-y: auto; -webkit-overflow-scrolling: touch;"
                                    role="dialog"
                                    aria-modal="true"
                                    :aria-labelledby="'withdrawal-review-title-{{ $w->id }}'"
                                >
                                    <div
                                        class="absolute inset-0 bg-overlay backdrop-blur-[2px]"
                                        @click="closeModal()"
                                        x-transition.opacity
                                    ></div>

                                    <div
                                        x-ref="panel"
                                        x-show="open"
                                        tabindex="-1"
                                        x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none"
                                        x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                                        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                        x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none"
                                        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                        x-transition:leave-end="opacity-0 translate-y-2 scale-95"
                                        class="relative my-auto flex w-full max-w-xl flex-col rounded-2xl border border-border-default bg-elevated shadow-panel outline-none"
                                        style="max-height: min(92vh, 92dvh); overflow: hidden;"
                                        @click.stop
                                    >
                                        <div class="flex shrink-0 items-start justify-between gap-3 border-b border-border-default px-4 py-4 sm:px-5">
                                            <div class="min-w-0">
                                                <h2 id="withdrawal-review-title-{{ $w->id }}" class="text-lg font-semibold text-text-primary">
                                                    <span x-show="mode === 'review'">Review withdrawal</span>
                                                    <span x-show="mode === 'reject'" x-cloak>Reject withdrawal</span>
                                                </h2>
                                                <p class="mt-1 font-mono text-xs text-text-muted break-all">{{ $w->reference }}</p>
                                            </div>
                                            <button
                                                type="button"
                                                class="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-lg p-2 text-text-muted hover:bg-surface-muted hover:text-text-primary transition-colors"
                                                @click="closeModal()"
                                                aria-label="Close"
                                            >
                                                <x-ui.icon name="x" class="w-4 h-4" />
                                            </button>
                                        </div>

                                        <div class="min-h-0 flex-1 px-4 py-4 sm:px-5" style="overflow-y: auto; -webkit-overflow-scrolling: touch;">
                                            <div x-show="mode === 'review'" class="space-y-4 text-sm">
                                                <dl class="grid gap-3 sm:grid-cols-2">
                                                    <div>
                                                        <dt class="text-xs text-text-muted">Full name</dt>
                                                        <dd class="mt-0.5 font-medium text-text-primary break-words">{{ $w->user?->name ?: '—' }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-xs text-text-muted">Email</dt>
                                                        <dd class="mt-0.5 font-medium text-text-primary break-all">{{ $w->user?->email ?: '—' }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-xs text-text-muted">Requested</dt>
                                                        <dd class="mt-0.5 font-semibold text-text-primary">{{ money($w->amount, 2) }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-xs text-text-muted">Net to send</dt>
                                                        <dd class="mt-0.5 font-semibold text-text-primary">{{ money($w->netPayoutAmount(), 2) }}</dd>
                                                    </div>
                                                    @if (bccomp((string) $w->fee_amount, '0', 2) > 0)
                                                        <div>
                                                            <dt class="text-xs text-text-muted">Fee</dt>
                                                            <dd class="mt-0.5 text-text-primary">{{ money($w->fee_amount, 2) }}</dd>
                                                        </div>
                                                    @endif
                                                    <div>
                                                        <dt class="text-xs text-text-muted">Destination</dt>
                                                        <dd class="mt-0.5 text-text-primary">{{ $w->destinationLabel() }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-xs text-text-muted">{{ $w->method === 'mobile_money' ? 'Network' : 'Bank' }}</dt>
                                                        <dd class="mt-0.5 text-text-primary break-words">{{ $w->bank_name ?: '—' }}</dd>
                                                    </div>
                                                    <div class="sm:col-span-2">
                                                        <dt class="text-xs text-text-muted">{{ $w->method === 'mobile_money' ? 'Phone / wallet number' : 'Account number' }}</dt>
                                                        <dd class="mt-0.5 font-mono text-base font-semibold tracking-wide text-text-primary break-all">{{ $w->accountNumber() ?: '—' }}</dd>
                                                    </div>
                                                    <div class="sm:col-span-2">
                                                        <dt class="text-xs text-text-muted">Account holder name</dt>
                                                        <dd class="mt-0.5 font-medium text-text-primary break-words">{{ $w->account_name ?: '—' }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-xs text-text-muted">Requested at</dt>
                                                        <dd class="mt-0.5 text-text-primary">{{ $w->created_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-xs text-text-muted">Status</dt>
                                                        <dd class="mt-0.5"><x-dashboard.badge :status="$w->status" /></dd>
                                                    </div>
                                                </dl>

                                                @if ($w->payoutAccount && ! $w->payoutAccount->isResolved())
                                                    <x-dashboard.alert type="warning">Payout account is not verified by the gateway.</x-dashboard.alert>
                                                @endif

                                                @if ($balances !== [])
                                                    <p class="text-xs text-text-muted">
                                                        @foreach ($balances as $b)
                                                            {{ $b['name'] }} {{ $b['balance'] !== null ? money($b['balance'], 2) : '—' }}@if (! $loop->last) · @endif
                                                        @endforeach
                                                    </p>
                                                @endif

                                                <p class="text-xs text-text-muted">
                                                    <a href="{{ route('admin.withdrawals.show', $w) }}" class="underline hover:text-text-primary">Open full withdrawal page</a>
                                                </p>
                                            </div>

                                            <div x-show="mode === 'reject'" x-cloak class="space-y-3 text-sm">
                                                <p class="text-text-secondary">
                                                    Rejecting returns <strong class="text-text-primary">{{ money($w->amount, 2) }}</strong> to
                                                    <strong class="text-text-primary">{{ $w->user?->name ?: 'this member' }}</strong>.
                                                    Add a reason — they will see it in their rejection email.
                                                </p>
                                                <label class="block">
                                                    <span class="mb-1.5 block text-xs font-medium text-text-muted">Rejection reason</span>
                                                    <textarea
                                                        x-ref="reason"
                                                        x-model="reason"
                                                        rows="4"
                                                        maxlength="1000"
                                                        class="w-full rounded-lg border border-border-subtle bg-surface px-3 py-2 text-sm text-text-primary outline-none focus:border-accent focus:ring-1 focus:ring-accent"
                                                        placeholder="Why is this withdrawal being rejected?"
                                                        required
                                                    ></textarea>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="flex shrink-0 flex-col-reverse gap-2.5 border-t border-border-default px-4 py-4 sm:flex-row sm:items-center sm:justify-end sm:px-5">
                                            <template x-if="mode === 'review'">
                                                <div class="flex w-full flex-col-reverse gap-2.5 sm:w-auto sm:flex-row">
                                                    <x-dashboard.button type="button" variant="secondary" @click="closeModal()" x-bind:disabled="submitting">Close</x-dashboard.button>
                                                    <x-dashboard.button type="button" variant="danger" @click="startReject()" x-bind:disabled="submitting">Reject</x-dashboard.button>
                                                    <form
                                                        method="POST"
                                                        action="{{ route('admin.withdrawals.approve', $w) }}"
                                                        class="contents"
                                                        @submit="if (submitting) { $event.preventDefault(); return; } submitting = true"
                                                    >
                                                        @csrf
                                                        <input type="hidden" name="confirm_send" value="1">
                                                        <x-dashboard.button type="submit" variant="primary" x-bind:disabled="submitting">
                                                            <span class="inline-flex items-center gap-2">
                                                                <span x-show="submitting" x-cloak><x-ui.icon name="spinner" class="h-4 w-4 animate-spin" /></span>
                                                                Approve &amp; send
                                                            </span>
                                                        </x-dashboard.button>
                                                    </form>
                                                </div>
                                            </template>
                                            <template x-if="mode === 'reject'">
                                                <div class="flex w-full flex-col-reverse gap-2.5 sm:w-auto sm:flex-row">
                                                    <x-dashboard.button type="button" variant="secondary" @click="cancelReject()" x-bind:disabled="submitting">Back</x-dashboard.button>
                                                    <form
                                                        method="POST"
                                                        action="{{ route('admin.withdrawals.reject', $w) }}"
                                                        class="contents"
                                                        @submit="if (submitting || reason.trim().length < 3) { $event.preventDefault(); return; } submitting = true"
                                                    >
                                                        @csrf
                                                        <input type="hidden" name="notes" :value="reason">
                                                        <x-dashboard.button type="submit" variant="danger" x-bind:disabled="submitting || reason.trim().length < 3">
                                                            <span class="inline-flex items-center gap-2">
                                                                <span x-show="submitting" x-cloak><x-ui.icon name="spinner" class="h-4 w-4 animate-spin" /></span>
                                                                Confirm reject
                                                            </span>
                                                        </x-dashboard.button>
                                                    </form>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    @elseif ($w->needsAdminDecision())
                        <x-dashboard.button :href="route('admin.withdrawals.show', $w)" size="sm" variant="primary">Decide</x-dashboard.button>
                    @else
                        <x-dashboard.button :href="route('admin.withdrawals.show', $w)" size="sm" variant="secondary">View</x-dashboard.button>
                    @endif
                </x-dashboard.td>
            </tr>
        @endforeach
    </x-dashboard.table>

    <x-slot:pagination>
        <x-dashboard.pagination :paginator="$withdrawals" />
    </x-slot:pagination>
</x-layout.page>
@endsection
