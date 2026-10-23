@php
    $section = $section ?? 'revenue';
    $rangeKey = $range['range'] ?? ($filters['range'] ?? '30d');
    $pulse = [];
@endphp

@if ($section === 'traffic')
    <x-dashboard.command.section-label title="Marketing — Traffic" accent="blue" />
    @if (! ($gaEnabled ?? false) || ! ($gaConnected ?? false) || ! ($data['ga_connected'] ?? false))
        <div class="rounded-2xl border border-border-default bg-elevated p-5">
            <p class="text-sm text-text-secondary">{{ $data['message'] ?? 'Connect GA in Marketing & Tracking' }}. Open <a href="{{ route('admin.tracking') }}" class="text-primary hover:underline">Marketing & Tracking</a>.</p>
        </div>
    @else
        @php
            $pulse = [[
                'label' => 'Sessions',
                'value' => number_format($data['totals']['sessions'] ?? 0),
                'accent' => 'blue',
                'hint' => 'in selected range',
                'href' => route('admin.analytics', ['section' => 'traffic', 'range' => $rangeKey]),
            ]];
        @endphp
        <x-dashboard.command.pulse-grid :items="$pulse" class="mb-6 xl:!grid-cols-3" />
        @if (! empty($marketing))
            <x-dashboard.command.tx-table
                title="GA snapshots"
                :rows="collect($marketing)->map(fn ($row) => [
                    'reference' => $row['metric'] ?? '—',
                    'user_name' => (($row['period_start'] ?? '') . ' — ' . ($row['period_end'] ?? '')),
                    'user_initials' => 'GA',
                    'amount' => (float) ($row['payload']['value'] ?? 0),
                    'status' => 'ok',
                ])->all()"
            />
        @endif
    @endif

@elseif ($section === 'revenue')
    <x-dashboard.command.section-label title="Business — Revenue" accent="primary" />
    @php
        $bd = $data['breakdown'] ?? [];
        $pulse = [
            [
                'label' => 'Creators paid in',
                'value' => money($bd['creator_spend'] ?? $data['gmv'] ?? 0, 2),
                'accent' => 'blue',
                'hint' => 'Paid campaign / service orders',
                'href' => route('admin.orders'),
            ],
            [
                'label' => 'Paid to agents',
                'value' => money($bd['agent_rewards_paid'] ?? 0, 2),
                'accent' => 'indigo',
                'hint' => 'Campaign rewards paid out',
                'href' => route('admin.transactions'),
            ],
            [
                'label' => 'Platform keep',
                'value' => money($bd['platform_share'] ?? 0, 2),
                'accent' => 'emerald',
                'hint' => 'Order total − agent budget',
            ],
            [
                'label' => 'Platform revenue',
                'value' => money($data['total'] ?? 0, 2),
                'accent' => 'emerald',
                'delta' => $data['delta_percent'] ?? null,
                'delta_label' => 'vs prior period',
                'hint' => 'Platform keep + fees',
                'href' => route('admin.transactions'),
            ],
        ];
    @endphp
    <x-dashboard.command.pulse-grid :items="$pulse" class="mb-6 xl:!grid-cols-4" />
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2">
            <x-dashboard.command.hero-chart
                title="Platform revenue"
                :subtitle="'Range · '.$rangeKey"
                :labels="$data['series']['labels'] ?? []"
                :values="$data['series']['values'] ?? []"
                id="analytics-revenue-chart"
            />
        </div>
        @php
            $byType = collect($data['by_type'] ?? []);
            $byTypeTotal = max(1.0, (float) $byType->sum('total'));
            $typeColors = ['#004AC6', '#2563EB', '#565E74', '#f59e0b', '#f97316'];
            $revenueSlices = $byType->values()->map(function ($row, $i) use ($byTypeTotal, $typeColors) {
                $val = (float) ($row['total'] ?? 0);
                return [
                    'label' => $row['type'] ?? '—',
                    'value' => $val,
                    'percent' => round(($val / $byTypeTotal) * 100) . '%',
                    'color' => $typeColors[$i % count($typeColors)],
                ];
            })->all();
        @endphp
        <x-dashboard.command.distribution-card
            title="Revenue by type"
            :center-value="money($data['total'] ?? 0, 0)"
            center-label="Total"
            :slices="$revenueSlices"
            id="analytics-revenue-donut"
        />
    </div>
    <p class="mt-3 text-xs text-text-secondary">
        <strong>Platform revenue</strong> is what the platform keeps: (creator order total − agent budget) + withdrawal/platform fees.
        It is not the full checkout amount — agent rewards are a cost, not revenue.
        @if (! empty($data['sandbox_cutover_at']))
            Sandbox-era paid orders on or before {{ $data['sandbox_cutover_at'] }} are excluded.
        @endif
        @if (! empty($data['fee_split_started_at']))
            Withdrawal fee revenue counts only the platform's own fee, from {{ \Illuminate\Support\Carbon::parse($data['fee_split_started_at'])->format('M j, Y') }}. Provider fees are pass-through.
        @else
            Withdrawal fee revenue starts after the fee split. Provider fees are pass-through.
        @endif
    </p>

@elseif ($section === 'services')
    <x-dashboard.command.section-label title="Business — Services" accent="primary" />
    @php
        $pulse = [
            ['label' => 'Service orders', 'value' => number_format($data['service_orders'] ?? 0), 'accent' => 'emerald', 'href' => route('admin.orders')],
            ['label' => 'Platform transactions', 'value' => number_format($data['platform_transactions'] ?? 0), 'accent' => 'indigo', 'href' => route('admin.transactions')],
        ];
    @endphp
    <x-dashboard.command.pulse-grid :items="$pulse" class="xl:!grid-cols-3" />
    @if (! empty($productMetrics['metrics']))
        <x-dashboard.command.hero-chart
            title="Top product metrics"
            :labels="collect($productMetrics['metrics'])->pluck('metric_key')->all()"
            :values="collect($productMetrics['metrics'])->pluck('total')->all()"
            id="analytics-services-chart"
        />
    @endif

@elseif ($section === 'users')
    <x-dashboard.command.section-label title="Business — Users" accent="blue" />
    @php
        $pulse = [
            ['label' => 'Total users', 'value' => number_format($data['total'] ?? 0), 'accent' => 'blue', 'href' => route('admin.users')],
            ['label' => 'New in range', 'value' => number_format($data['new_in_range'] ?? 0), 'accent' => 'emerald', 'href' => route('admin.users')],
            ['label' => 'Verified', 'value' => number_format($data['verified'] ?? 0), 'accent' => 'indigo', 'href' => route('admin.users')],
        ];
    @endphp
    <x-dashboard.command.pulse-grid :items="$pulse" class="mb-6 xl:!grid-cols-3" />
    @if (! empty($data['daily_signups']['labels']))
        <x-dashboard.command.hero-chart
            title="Daily signups"
            :labels="$data['daily_signups']['labels']"
            :values="$data['daily_signups']['values']"
            id="analytics-users-chart"
        />
    @endif

@elseif ($section === 'support')
    <x-dashboard.command.section-label title="Business — Support" accent="orange" />
    @php
        $pulse = [
            ['label' => 'Open / waiting', 'value' => number_format($data['open'] ?? 0), 'accent' => 'orange', 'href' => route('admin.tickets')],
            ['label' => 'Created in range', 'value' => number_format($data['created_in_range'] ?? 0), 'accent' => 'amber', 'href' => route('admin.tickets')],
        ];
        $slices = collect($data['by_status'] ?? [])->map(function ($count, $status) {
            $colors = ['open' => '#f97316', 'waiting' => '#f59e0b', 'closed' => '#006243', 'resolved' => '#004AC6'];
            $total = max(1, array_sum($data['by_status'] ?? []));
            return [
                'label' => (string) $status,
                'value' => (float) $count,
                'percent' => round(($count / $total) * 100) . '%',
                'color' => $colors[$status] ?? '#94a3b8',
            ];
        })->values()->all();
    @endphp
    <x-dashboard.command.pulse-grid :items="$pulse" class="mb-6 xl:!grid-cols-3" />
    <x-dashboard.command.distribution-card
        title="Tickets by status"
        :center-value="number_format($data['open'] ?? 0)"
        center-label="Open"
        :slices="$slices"
        id="analytics-support-donut"
    />

@elseif ($section === 'kyc')
    <x-dashboard.command.section-label title="Business — KYC" accent="amber" />
    @php
        $pulse = [
            ['label' => 'Pending', 'value' => number_format($data['pending'] ?? 0), 'accent' => 'amber', 'href' => route('admin.kyc', ['status' => 'pending'])],
            ['label' => 'Approved', 'value' => number_format($data['approved_in_range'] ?? 0), 'accent' => 'emerald', 'hint' => 'in selected range', 'href' => route('admin.kyc', ['status' => 'approved'])],
            ['label' => 'Rejected', 'value' => number_format($data['rejected_in_range'] ?? 0), 'accent' => 'red', 'hint' => 'in selected range', 'href' => route('admin.kyc', ['status' => 'rejected'])],
        ];
    @endphp
    <x-dashboard.command.pulse-grid :items="$pulse" class="xl:!grid-cols-3" />
@endif
