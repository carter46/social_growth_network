<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsGaSnapshot;
use App\Models\AnalyticsKpiSnapshot;
use App\Models\AnalyticsProvider;
use App\Models\KycSubmission;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletFunding;
use Carbon\Carbon;

/**
 * Business KPIs are read from analytics_kpi_snapshots.
 * Overview / command-center paths never fall back to live SUM/COUNT on fact tables.
 * Drill-down report methods may still run limited live queries.
 */
class InternalBusinessProvider
{
    /**
     * @return array<string, mixed>
     */
    public function kpis(string $period = 'current'): array
    {
        return [
            'users_total' => $this->snapshotValue('users.total', $period),
            'sales_total_ngn' => $this->snapshotValue('sales.total_ngn', $period),
            'transactions_total' => $this->snapshotValue('transactions.total', $period),
            'tickets_total' => $this->snapshotValue('tickets.total', $period),
            'tickets_open' => $this->snapshotValue('tickets.open', $period),
            'orders_by_status' => $this->ordersByStatus($period),
        ];
    }

    /**
     * Alert KPIs for the admin command center overview (snapshots only).
     *
     * @return array<string, mixed>
     */
    public function commandCenterAlerts(): array
    {
        return [
            'revenue_today' => $this->snapshotValue('revenue.today', 'today'),
            'visitors_today' => $this->visitorsToday(),
            'wallets_funded_today' => $this->snapshotValue('fundings.today', 'today'),
            'pending_kyc' => $this->snapshotValue('kyc.pending', 'current'),
            'pending_escrows' => $this->snapshotValue('escrows.pending', 'current'),
            'support_waiting' => $this->snapshotValue('support.waiting', 'current'),
            'pending_listings' => $this->snapshotValue('listings.pending_review', 'current'),
        ];
    }

    /**
     * @return array{enabled: bool, connected: bool, value: int|float|null, message: string|null}
     */
    public function visitorsToday(): array
    {
        $provider = AnalyticsProvider::forProvider(AnalyticsProvider::PROVIDER_GOOGLE_ANALYTICS);

        if (! $provider->enabled) {
            return [
                'enabled' => false,
                'connected' => false,
                'value' => null,
                'message' => 'Google Analytics disabled',
            ];
        }

        if ($provider->status !== 'connected') {
            return [
                'enabled' => true,
                'connected' => false,
                'value' => null,
                'message' => 'Connect GA in Settings',
            ];
        }

        $snapshot = AnalyticsGaSnapshot::query()
            ->where('metric', 'sessions')
            ->where('period_start', '<=', today())
            ->where('period_end', '>=', today())
            ->orderByDesc('fetched_at')
            ->first();

        $source = $snapshot?->payload['source'] ?? null;
        if (! $snapshot || $source === 'stub') {
            return [
                'enabled' => true,
                'connected' => false,
                'value' => null,
                'message' => 'Connect GA in Settings',
            ];
        }

        $value = $snapshot->payload['value'] ?? null;

        return [
            'enabled' => true,
            'connected' => true,
            'value' => is_numeric($value) ? (float) $value : null,
            'message' => null,
        ];
    }

    /**
     * Compact chart strip datasets from KPI snapshots only.
     *
     * @return list<array{key: string, label: string, labels: list<string>, values: list<float|null>}>
     */
    public function chartStrip(int $days = 7): array
    {
        $keys = [
            'revenue.daily' => 'Revenue (NGN)',
            'users.daily' => 'New users',
            'fundings.daily' => 'Wallet fundings',
        ];

        $result = [];
        foreach ($keys as $key => $label) {
            $series = $this->dailyKpiSeries($key, $days);
            $result[] = [
                'key' => $key,
                'label' => $label,
                'labels' => $series['labels'],
                'values' => $series['values'],
            ];
        }

        return $result;
    }

    /**
     * @return array{labels: list<string>, values: list<float|null>}
     */
    public function dailyKpiSeries(string $kpiKey, int $days = 7, ?Carbon $from = null, ?Carbon $to = null): array
    {
        if ($from && $to) {
            $start = $from->copy()->startOfDay();
            $end = $to->copy()->startOfDay();
            $days = max(1, $start->diffInDays($end) + 1);
            $since = $start;
        } else {
            $since = now()->subDays($days - 1)->startOfDay();
        }

        $labels = [];
        $values = [];

        $snapshots = AnalyticsKpiSnapshot::query()
            ->where('kpi_key', $kpiKey)
            ->where('period', 'daily')
            ->where('captured_at', '>=', $since)
            ->orderBy('captured_at')
            ->get()
            ->groupBy(fn (AnalyticsKpiSnapshot $row) => $row->meta['day'] ?? $row->captured_at->toDateString());

        for ($i = 0; $i < $days; $i++) {
            $day = $since->copy()->addDays($i);
            $dateKey = $day->toDateString();
            $labels[] = $day->format('M j');

            if ($snapshots->has($dateKey)) {
                $values[] = (float) $snapshots->get($dateKey)->last()->value;
            } else {
                $values[] = null;
            }
        }

        return compact('labels', 'values');
    }

    /**
     * @return array<string, mixed>
     */
    public function marketingSnapshots(int $days = 7): array
    {
        $since = now()->subDays($days)->toDateString();

        return AnalyticsGaSnapshot::query()
            ->where('period_end', '>=', $since)
            ->orderByDesc('fetched_at')
            ->limit(20)
            ->get()
            ->map(fn (AnalyticsGaSnapshot $row) => [
                'metric' => $row->metric,
                'dimension' => $row->dimension,
                'period_start' => $row->period_start?->toDateString(),
                'period_end' => $row->period_end?->toDateString(),
                'payload' => $row->payload,
                'fetched_at' => $row->fetched_at?->toDateTimeString(),
            ])
            ->all();
    }

    /**
     * Section report for analytics drill-down.
     *
     * @param  array<string, mixed>  $range
     * @return array<string, mixed>
     */
    public function sectionReport(string $section, array $range): array
    {
        $from = isset($range['_from'])
            ? $range['_from']->copy()
            : Carbon::parse($range['from'])->startOfDay();
        $to = isset($range['_to'])
            ? $range['_to']->copy()
            : Carbon::parse($range['to'])->endOfDay();

        return match ($section) {
            'traffic' => $this->trafficReport($from, $to),
            'revenue' => $this->revenueReport($from, $to),
            'services' => $this->servicesReport($from, $to),
            'users' => $this->usersReport($from, $to),
            'support' => $this->supportReport($from, $to),
            'kyc' => $this->kycReport($from, $to),
            default => ['error' => 'Unknown section'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function trafficReport(Carbon $from, Carbon $to): array
    {
        $provider = AnalyticsProvider::forProvider(AnalyticsProvider::PROVIDER_GOOGLE_ANALYTICS);
        $connected = $provider->enabled && $provider->status === 'connected';

        $snapshots = $connected
            ? AnalyticsGaSnapshot::query()
                ->whereBetween('period_end', [$from->toDateString(), $to->toDateString()])
                ->orderByDesc('fetched_at')
                ->limit(50)
                ->get()
                ->filter(fn ($row) => ($row->payload['source'] ?? null) !== 'stub')
            : collect();

        return [
            'ga_enabled' => (bool) $provider->enabled,
            'ga_connected' => $connected,
            'message' => $connected ? null : 'Connect GA in Settings',
            'snapshots' => $snapshots->values(),
            'totals' => [
                'sessions' => $snapshots->where('metric', 'sessions')->sum(fn ($row) => (float) ($row->payload['value'] ?? 0)),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function revenueReport(Carbon $from, Carbon $to): array
    {
        $range = new \App\Services\Reporting\ReportingRange('custom', $from, $to);

        return app(\App\Services\Reporting\ReportingService::class)->revenueSection($range);
    }

    /**
     * @return array<string, mixed>
     */
    private function servicesReport(Carbon $from, Carbon $to): array
    {
        $platformOrders = Order::query()
            ->where('source', 'platform')
            ->whereBetween('created_at', [$from, $to]);

        $serviceOrders = Order::query()
            ->where(function ($q) {
                $q->where('source', 'platform')
                    ->orWhereHas('items', fn ($items) => $items->where('item_type', 'platform_product'));
            })
            ->whereBetween('created_at', [$from, $to])
            ->count();

        return [
            'service_orders' => $serviceOrders,
            'platform_orders' => (clone $platformOrders)->count(),
            'platform_transactions' => Transaction::query()
                ->where('type', 'purchase')
                ->whereBetween('created_at', [$from, $to])
                ->count(),
            'platform_gmv_ngn' => (float) (clone $platformOrders)
                ->whereIn('status', ['paid', 'completed', 'processing'])
                ->sum('total_amount'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function usersReport(Carbon $from, Carbon $to): array
    {
        return [
            'total' => User::count(),
            'new_in_range' => User::query()->whereBetween('created_at', [$from, $to])->count(),
            'verified' => User::query()->whereNotNull('email_verified_at')->count(),
            'daily_signups' => $this->dailyKpiSeries('users.daily', max(1, $from->diffInDays($to) + 1)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function supportReport(Carbon $from, Carbon $to): array
    {
        return [
            'open' => SupportTicket::query()->whereIn('status', ['open', 'pending', 'awaiting_user'])->count(),
            'created_in_range' => SupportTicket::query()->whereBetween('created_at', [$from, $to])->count(),
            'by_status' => SupportTicket::query()
                ->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->map(fn ($c) => (int) $c)
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function kycReport(Carbon $from, Carbon $to): array
    {
        return [
            'pending' => KycSubmission::query()->where('status', 'pending')->count(),
            'approved_in_range' => KycSubmission::query()
                ->where('status', 'approved')
                ->whereBetween('reviewed_at', [$from, $to])
                ->count(),
            'rejected_in_range' => KycSubmission::query()
                ->where('status', 'rejected')
                ->whereBetween('reviewed_at', [$from, $to])
                ->count(),
            'by_status' => KycSubmission::query()
                ->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->map(fn ($c) => (int) $c)
                ->all(),
        ];
    }

    private function snapshotValue(string $key, string $period): ?float
    {
        $snapshot = AnalyticsKpiSnapshot::query()
            ->where('kpi_key', $key)
            ->where('period', $period)
            ->orderByDesc('captured_at')
            ->first();

        return $snapshot ? (float) $snapshot->value : null;
    }

    /**
     * @deprecated Use snapshotValue for overview; kept for any drill-down callers.
     */
    private function kpiValue(string $key, string $period, callable $fallback): float|int
    {
        return $this->snapshotValue($key, $period) ?? $fallback();
    }

    /**
     * @return array<string, int>
     */
    private function ordersByStatus(string $period): array
    {
        $snapshot = AnalyticsKpiSnapshot::query()
            ->where('kpi_key', 'orders.by_status')
            ->where('period', $period)
            ->orderByDesc('captured_at')
            ->first();

        if ($snapshot && is_array($snapshot->meta)) {
            return array_map('intval', $snapshot->meta);
        }

        return [];
    }
}
