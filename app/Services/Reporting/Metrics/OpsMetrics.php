<?php

namespace App\Services\Reporting\Metrics;

use App\Models\KycSubmission;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\WalletFunding;
use App\Models\Withdrawal;
use App\Services\Reporting\ReportingRange;
use Illuminate\Support\Facades\DB;

class OpsMetrics
{
    public function fundingsCount(ReportingRange $range): int
    {
        return (int) WalletFunding::query()
            ->where('status', 'approved')
            ->whereBetween('approved_at', [$range->from, $range->to])
            ->count();
    }

    public function fundingsSum(ReportingRange $range): float
    {
        return round((float) WalletFunding::query()
            ->where('status', 'approved')
            ->whereBetween('approved_at', [$range->from, $range->to])
            ->sum('amount'), 2);
    }

    public function newUsers(ReportingRange $range): int
    {
        return (int) User::query()
            ->whereBetween('created_at', [$range->from, $range->to])
            ->count();
    }

    /**
     * @return array{labels: list<string>, values: list<float>}
     */
    public function fundingsDailySeries(ReportingRange $range): array
    {
        return $this->dailyCountSeries(
            $range,
            fn (string $day) => WalletFunding::query()
                ->where('status', 'approved')
                ->whereDate('approved_at', $day)
                ->count()
        );
    }

    /**
     * @return array{labels: list<string>, values: list<float>}
     */
    public function usersDailySeries(ReportingRange $range): array
    {
        return $this->dailyCountSeries(
            $range,
            fn (string $day) => User::query()->whereDate('created_at', $day)->count()
        );
    }

    public function pendingKyc(): int
    {
        return (int) KycSubmission::query()->where('status', 'pending')->count();
    }

    public function pendingWithdrawals(): int
    {
        return (int) Withdrawal::query()
            ->where(function ($query) {
                $query->whereIn('status', Withdrawal::OPEN_STATUSES)
                    ->orWhereIn('internal_status', Withdrawal::OPEN_INTERNAL);
            })
            ->count();
    }

    public function supportWaiting(): int
    {
        return (int) SupportTicket::query()
            ->whereIn('status', ['open', 'pending', 'awaiting_user'])
            ->count();
    }

    /** Gross platform order totals completed/paid in range. */
    public function gmv(ReportingRange $range): float
    {
        return round((float) Order::query()
            ->where('source', 'platform')
            ->whereIn('status', ['completed', 'paid', 'processing'])
            ->whereBetween('created_at', [$range->from, $range->to])
            ->sum(DB::raw('COALESCE(total_amount, amount)')), 2);
    }

    /**
     * @return list<array{label: string, value: float, color: string}>
     */
    public function kycStatusSlices(): array
    {
        return $this->statusSlices(
            KycSubmission::query()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status')->all(),
            [
                'pending' => '#f59e0b',
                'approved' => '#10b981',
                'rejected' => '#ef4444',
                'submitted' => '#3b82f6',
            ]
        );
    }

    /**
     * @return list<array{label: string, value: float, color: string}>
     */
    public function supportStatusSlices(): array
    {
        return $this->statusSlices(
            SupportTicket::query()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status')->all(),
            [
                'open' => '#f97316',
                'pending' => '#f59e0b',
                'awaiting_user' => '#6366f1',
                'resolved' => '#10b981',
                'closed' => '#94a3b8',
            ]
        );
    }

    /**
     * @return list<array{label: string, value: float, color: string}>
     */
    public function orderStatusSlices(ReportingRange $range): array
    {
        return $this->statusSlices(
            Order::query()
                ->whereBetween('created_at', [$range->from, $range->to])
                ->selectRaw('status, count(*) as c')
                ->groupBy('status')
                ->pluck('c', 'status')
                ->all(),
            [
                'pending' => '#f59e0b',
                'paid' => '#3b82f6',
                'processing' => '#6366f1',
                'completed' => '#10b981',
                'cancelled' => '#94a3b8',
                'disputed' => '#ef4444',
            ]
        );
    }

    /**
     * @param  array<string, int|float>  $counts
     * @param  array<string, string>  $colors
     * @return list<array{label: string, value: float, color: string, percent: string}>
     */
    private function statusSlices(array $counts, array $colors): array
    {
        $total = max(1.0, (float) array_sum($counts));
        $palette = ['#10b981', '#3b82f6', '#6366f1', '#f59e0b', '#f97316', '#ef4444', '#94a3b8'];
        $i = 0;
        $out = [];
        foreach ($counts as $status => $count) {
            $val = (float) $count;
            if ($val <= 0) {
                continue;
            }
            $out[] = [
                'label' => ucfirst(str_replace('_', ' ', (string) $status)),
                'value' => $val,
                'percent' => round(($val / $total) * 100).'%',
                'color' => $colors[(string) $status] ?? $palette[$i % count($palette)],
            ];
            $i++;
        }

        return $out;
    }

    /**
     * @param  callable(string): int|float  $counter
     * @return array{labels: list<string>, values: list<float>}
     */
    private function dailyCountSeries(ReportingRange $range, callable $counter): array
    {
        $labels = [];
        $values = [];
        $cursor = $range->from->copy()->startOfDay();
        $end = $range->to->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $labels[] = $cursor->format('M j');
            $values[] = (float) $counter($cursor->toDateString());
            $cursor->addDay();
        }

        return ['labels' => $labels, 'values' => $values];
    }
}
