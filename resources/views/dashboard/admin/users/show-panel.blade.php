<div class="space-y-4">
    @if ($activeTab === 'overview')
        <div class="grid gap-4 md:grid-cols-2">
            <x-dashboard.stats-card label="Orders" :value="number_format($orderCount ?? 0)" icon="orders" />
            <x-dashboard.stats-card label="Tickets" :value="number_format($ticketCount ?? 0)" icon="support" />
        </div>
        <x-dashboard.card>
            <h3 class="mb-3 text-sm font-semibold text-text-primary">Wallet</h3>
            <p class="text-text-secondary">
                @if ($wallet ?? null)
                    Balance: ₦{{ number_format($wallet->balance ?? 0, 2) }}
                @else
                    No wallet provisioned.
                @endif
            </p>
        </x-dashboard.card>
        <x-dashboard.card>
            <h3 class="mb-3 text-sm font-semibold text-text-primary">Recent transactions</h3>
            @forelse (($recentTransactions ?? collect()) as $tx)
                <div class="flex items-center justify-between border-b border-border-default py-2 text-sm last:border-0">
                    <span class="font-mono text-xs">{{ $tx->reference ?? $tx->id }}</span>
                    <span>{{ number_format($tx->amount, 2) }} {{ $tx->currency }}</span>
                    <x-dashboard.badge :status="$tx->status" />
                </div>
            @empty
                <p class="text-sm text-text-muted">No transactions.</p>
            @endforelse
        </x-dashboard.card>
    @elseif ($activeTab === 'wallet')
        <x-dashboard.card>
            @if ($wallet ?? null)
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-text-muted">Balance</dt>
                        <dd class="text-lg font-semibold text-text-primary">₦{{ number_format($wallet->balance ?? 0, 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-text-muted">Status</dt>
                        <dd class="text-text-primary">{{ $wallet->status ?? 'active' }}</dd>
                    </div>
                </dl>
            @else
                <p class="text-sm text-text-muted">No wallet.</p>
            @endif
        </x-dashboard.card>
    @elseif ($activeTab === 'transactions')
        <x-dashboard.table :empty="($transactions ?? collect())->isEmpty()" empty-title="No transactions" striped>
            <x-slot:head>
                <x-dashboard.th>Reference</x-dashboard.th>
                <x-dashboard.th>Amount</x-dashboard.th>
                <x-dashboard.th>Status</x-dashboard.th>
                <x-dashboard.th>Date</x-dashboard.th>
            </x-slot:head>
            @foreach ($transactions as $tx)
                <tr>
                    <x-dashboard.td class="font-mono text-xs">{{ $tx->reference ?? $tx->id }}</x-dashboard.td>
                    <x-dashboard.td>{{ number_format($tx->amount, 2) }} {{ $tx->currency }}</x-dashboard.td>
                    <x-dashboard.td><x-dashboard.badge :status="$tx->status" /></x-dashboard.td>
                    <x-dashboard.td class="text-xs text-text-muted">{{ $tx->created_at->format('j M Y') }}</x-dashboard.td>
                </tr>
            @endforeach
        </x-dashboard.table>
        <x-dashboard.pagination :paginator="$transactions" />
    @elseif ($activeTab === 'orders')
        <x-dashboard.table :empty="($orders ?? collect())->isEmpty()" empty-title="No orders" striped>
            <x-slot:head>
                <x-dashboard.th>ID</x-dashboard.th>
                <x-dashboard.th>Status</x-dashboard.th>
                <x-dashboard.th>Date</x-dashboard.th>
            </x-slot:head>
            @foreach ($orders as $order)
                <tr>
                    <x-dashboard.td>#{{ $order->id }}</x-dashboard.td>
                    <x-dashboard.td><x-dashboard.badge :status="$order->status" /></x-dashboard.td>
                    <x-dashboard.td class="text-xs text-text-muted">{{ $order->created_at->format('j M Y') }}</x-dashboard.td>
                </tr>
            @endforeach
        </x-dashboard.table>
        <x-dashboard.pagination :paginator="$orders" />
    @elseif ($activeTab === 'tools')
        <x-dashboard.table :empty="($tools ?? collect())->isEmpty()" empty-title="No tools" empty-description="Purchased internal catalog services appear here after checkout." striped>
            <x-slot:head>
                <x-dashboard.th>Tool</x-dashboard.th>
                <x-dashboard.th>Status</x-dashboard.th>
                <x-dashboard.th>Expires</x-dashboard.th>
                <x-dashboard.th>Connection</x-dashboard.th>
                <x-dashboard.th></x-dashboard.th>
            </x-slot:head>
            @foreach (($tools ?? collect()) as $tool)
                @php $isPendingSetup = $tool->status->value === 'pending_setup'; @endphp
                <tr>
                    <x-dashboard.td>
                        <div class="font-medium text-text-primary">{{ $tool->resolvedDisplayName() }}</div>
                        <div class="text-xs text-text-muted">#{{ $tool->id }} · {{ $tool->public_id }}</div>
                    </x-dashboard.td>
                    <x-dashboard.td>
                        <x-dashboard.badge :status="$tool->status->value" />
                        @if ($tool->isExpiringSoon())
                            <span class="ml-1 text-xs text-amber-600">Expiring soon</span>
                        @endif
                    </x-dashboard.td>
                    <x-dashboard.td class="text-xs text-text-muted">{{ $tool->expires_at?->format('j M Y') ?? '—' }}</x-dashboard.td>
                    <x-dashboard.td class="text-xs text-text-muted">{{ $tool->integration?->connection_status ?? '—' }}</x-dashboard.td>
                    <x-dashboard.td>
                        <x-dashboard.button
                            :href="route('admin.users.tools.show', [$user, $tool])"
                            size="sm"
                            variant="secondary"
                        >
                            {{ $isPendingSetup ? 'Setup' : 'Manage' }}
                        </x-dashboard.button>
                    </x-dashboard.td>
                </tr>
            @endforeach
        </x-dashboard.table>
        @if (($tools ?? null) instanceof \Illuminate\Contracts\Pagination\Paginator)
            <x-dashboard.pagination :paginator="$tools" />
        @endif
    @elseif ($activeTab === 'tickets')
        <x-dashboard.table :empty="($tickets ?? collect())->isEmpty()" empty-title="No tickets" striped>
            <x-slot:head>
                <x-dashboard.th>Subject</x-dashboard.th>
                <x-dashboard.th>Status</x-dashboard.th>
                <x-dashboard.th>Date</x-dashboard.th>
            </x-slot:head>
            @foreach ($tickets as $ticket)
                <tr>
                    <x-dashboard.td>{{ $ticket->subject ?? ('Ticket #'.$ticket->id) }}</x-dashboard.td>
                    <x-dashboard.td><x-dashboard.badge :status="$ticket->status" /></x-dashboard.td>
                    <x-dashboard.td class="text-xs text-text-muted">{{ $ticket->created_at->format('j M Y') }}</x-dashboard.td>
                </tr>
            @endforeach
        </x-dashboard.table>
        <x-dashboard.pagination :paginator="$tickets" />
    @elseif ($activeTab === 'activity')
        <x-dashboard.table :empty="($activity ?? collect())->isEmpty()" empty-title="No activity" striped>
            <x-slot:head>
                <x-dashboard.th>Action</x-dashboard.th>
                <x-dashboard.th>Date</x-dashboard.th>
            </x-slot:head>
            @foreach ($activity as $row)
                <tr>
                    <x-dashboard.td>{{ $row->action ?? $row->event ?? '—' }}</x-dashboard.td>
                    <x-dashboard.td class="text-xs text-text-muted">{{ $row->created_at->format('j M Y H:i') }}</x-dashboard.td>
                </tr>
            @endforeach
        </x-dashboard.table>
        <x-dashboard.pagination :paginator="$activity" />
    @elseif ($activeTab === 'security')
        <x-dashboard.card class="space-y-2 text-sm text-text-secondary">
            <p>Suspended: {{ $user->is_suspended ? 'Yes' : 'No' }}</p>
            <p>Anonymized: {{ $user->anonymized_at ? $user->anonymized_at->format('j M Y H:i') : 'No' }}</p>
            <p>Email verified: {{ $user->email_verified_at ? 'Yes' : 'No' }}</p>
            <p>KYC level: {{ $user->kyc_level }}</p>
        </x-dashboard.card>
    @endif
</div>
