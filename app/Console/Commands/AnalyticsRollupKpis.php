<?php

namespace App\Console\Commands;

use App\Models\AnalyticsKpiSnapshot;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Reporting\ReportingRange;
use App\Services\Reporting\ReportingService;
use Illuminate\Console\Command;

class AnalyticsRollupKpis extends Command
{
    protected $signature = 'analytics:rollup-kpis';

    protected $description = 'Upsert analytics_kpi_snapshots from ReportingService (same definitions as Overview)';

    public function handle(ReportingService $reporting): int
    {
        $now = now();
        $ops = app(\App\Services\Reporting\Metrics\OpsMetrics::class);
        $revenue = app(\App\Services\Reporting\Metrics\PlatformRevenueMetric::class);

        $periods = [
            'current' => ReportingRange::preset('90d'),
            'today' => ReportingRange::preset('today'),
            '7d' => ReportingRange::preset('7d'),
            '30d' => ReportingRange::preset('30d'),
        ];

        foreach ($periods as $period => $range) {
            $this->upsertSnapshot('users.total', $period, User::count(), $now);
            $this->upsertSnapshot('kyc.pending', $period, $ops->pendingKyc(), $now);
            $this->upsertSnapshot('support.waiting', $period, $ops->supportWaiting(), $now);
            $this->upsertSnapshot('tickets.open', $period, $ops->supportWaiting(), $now);
            $this->upsertSnapshot('tickets.total', $period, \App\Models\SupportTicket::count(), $now);
            $this->upsertSnapshot('sales.total_ngn', $period, $revenue->sum($range), $now);
            $this->upsertSnapshot('transactions.total', $period, Transaction::query()
                ->whereBetween('created_at', [$range->from, $range->to])
                ->count(), $now);
        }

        $today = ReportingRange::preset('today');
        $this->upsertSnapshot('revenue.today', 'today', $revenue->sum($today), $now);
        $this->upsertSnapshot('fundings.today', 'today', $ops->fundingsCount($today), $now);

        $ordersByStatus = Order::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->map(fn ($c) => (int) $c)
            ->all();

        $this->upsertSnapshot('orders.by_status', 'current', array_sum($ordersByStatus), $now, $ordersByStatus);

        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $dateKey = $day->toDateString();
            $dayRange = new ReportingRange('day', $day->copy()->startOfDay(), $day->copy()->endOfDay());

            $this->upsertSnapshot('revenue.daily', 'daily', $revenue->sum($dayRange), $day->copy()->endOfDay(), ['day' => $dateKey]);
            $this->upsertSnapshot('users.daily', 'daily', User::whereDate('created_at', $dateKey)->count(), $day->copy()->endOfDay(), ['day' => $dateKey]);
            $this->upsertSnapshot('fundings.daily', 'daily', $ops->fundingsCount($dayRange), $day->copy()->endOfDay(), ['day' => $dateKey]);
        }

        $pruned = AnalyticsKpiSnapshot::query()
            ->where('captured_at', '<', now()->subDays(90))
            ->delete();

        $this->info('KPI snapshots rolled up via ReportingService.'.($pruned ? " Pruned {$pruned} old rows." : ''));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function upsertSnapshot(string $key, string $period, float|int $value, $capturedAt, array $meta = []): void
    {
        $query = AnalyticsKpiSnapshot::query()
            ->where('kpi_key', $key)
            ->where('period', $period);

        if ($period === 'daily' && isset($meta['day'])) {
            $query->where('meta->day', $meta['day']);
        }

        $existing = $query->first();

        if ($existing) {
            $existing->update([
                'value' => $value,
                'meta' => $meta ?: null,
                'captured_at' => $capturedAt,
            ]);

            return;
        }

        AnalyticsKpiSnapshot::create([
            'kpi_key' => $key,
            'period' => $period,
            'value' => $value,
            'meta' => $meta ?: null,
            'captured_at' => $capturedAt,
        ]);
    }
}
