<?php

namespace App\Services\Reporting\Metrics;

use App\Enums\TransactionType;
use App\Enums\WalletType;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Modules\Wallet\Services\WalletService;
use App\Services\Reporting\ReportingRange;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class PlatformRevenueMetric
{
    public function sum(ReportingRange $range): float
    {
        return round((float) $this->baseQuery($range)->sum('amount'), 2);
    }

    public function count(ReportingRange $range): int
    {
        return (int) $this->baseQuery($range)->count();
    }

    /**
     * @return list<array{type: string, count: int, total: float}>
     */
    public function byType(ReportingRange $range): array
    {
        return $this->baseQuery($range)
            ->selectRaw('type, count(*) as count, sum(amount) as total')
            ->groupBy('type')
            ->get()
            ->map(fn ($row) => [
                'type' => (string) $row->type,
                'count' => (int) $row->count,
                'total' => round((float) $row->total, 2),
            ])
            ->all();
    }

    /**
     * Chart series: hourly buckets for 24h / today, otherwise daily.
     *
     * @return array{labels: list<string>, values: list<float>}
     */
    public function chartSeries(ReportingRange $range): array
    {
        if (in_array($range->key, ['24h', 'today'], true) || $range->days() <= 1) {
            return $this->hourlySeries($range);
        }

        return $this->dailySeries($range);
    }

    /**
     * @return array{labels: list<string>, values: list<float>}
     */
    public function hourlySeries(ReportingRange $range): array
    {
        $labels = [];
        $values = [];
        $cursor = $range->from->copy()->startOfHour();
        $end = $range->to->copy()->startOfHour();

        while ($cursor->lte($end)) {
            $labels[] = $cursor->format('H:i');
            $hourRange = new ReportingRange('hour', $cursor->copy(), $cursor->copy()->endOfHour());
            $values[] = $this->sum($hourRange);
            $cursor->addHour();
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Daily series for charts (inclusive calendar days in range).
     *
     * @return array{labels: list<string>, values: list<float>}
     */
    public function dailySeries(ReportingRange $range): array
    {
        $labels = [];
        $values = [];
        $cursor = $range->from->copy()->startOfDay();
        $end = $range->to->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $labels[] = $cursor->format('M j');
            $dayRange = new ReportingRange('day', $cursor->copy()->startOfDay(), $cursor->copy()->endOfDay());
            $values[] = $this->sum($dayRange);
            $cursor->addDay();
        }

        return ['labels' => $labels, 'values' => $values];
    }

    public function deltaPercent(ReportingRange $range): ?float
    {
        $current = $this->sum($range);
        $prior = $this->sum($range->priorPeriod());
        if (abs($prior) < 0.00001) {
            return $current > 0 ? 100.0 : ($current < 0 ? -100.0 : 0.0);
        }

        return round((($current - $prior) / abs($prior)) * 100, 1);
    }

    private function baseQuery(ReportingRange $range): Builder
    {
        $platformWalletId = Wallet::query()
            ->where('type', WalletType::Platform->value)
            ->value('id');

        return Transaction::query()
            ->where('status', 'completed')
            ->where('currency', config('platform.currency'))
            ->whereBetween('created_at', [$range->from, $range->to])
            ->where(function (Builder $q) use ($platformWalletId) {
                $q->where('type', TransactionType::PlatformFee->value);
                if ($platformWalletId) {
                    // Only the platform's own withdrawal fee is income; provider fees recovered and paid are pass-through.
                    $q->orWhere(function (Builder $inner) use ($platformWalletId) {
                        $inner->where('type', TransactionType::WithdrawalFee->value)
                            ->where('wallet_id', $platformWalletId)
                            ->where('created_at', '>=', $this->feeSplitStartedAt());
                    });
                    $q->orWhere(function (Builder $inner) use ($platformWalletId) {
                        $inner->where('type', TransactionType::Purchase->value)
                            ->where('wallet_id', $platformWalletId)
                            ->where('amount', '>', 0);
                    });
                }
            });
    }

    /**
     * Withdrawal fees before the split held provider pass-through as well, so they are left out of revenue.
     * Before the first split entry exists, nothing is counted.
     */
    public function feeSplitStartedAt(): CarbonImmutable
    {
        $raw = SystemSetting::get(WalletService::FEE_SPLIT_STARTED_SETTING);

        return $raw ? CarbonImmutable::parse((string) $raw) : CarbonImmutable::now()->addCentury();
    }
}
