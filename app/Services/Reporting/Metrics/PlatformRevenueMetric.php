<?php

namespace App\Services\Reporting\Metrics;

use App\Enums\TransactionType;
use App\Enums\WalletType;
use App\Models\Campaign;
use App\Models\CampaignBudget;
use App\Models\Order;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Modules\Wallet\Services\WalletService;
use App\Services\Reporting\ReportingRange;
use App\Support\Money\Money;
use App\Support\SandboxCutover;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Platform revenue = money the platform keeps, not every dollar a creator pays.
 *
 * Catalog sale: creator pays order total; agent budget is a liability; platform share
 * = order total − funded agent budget. Fees are counted in full.
 * Sandbox-era orders (cutover / purged tag) are excluded.
 */
class PlatformRevenueMetric
{
    public function sum(ReportingRange $range): float
    {
        return round($this->feeTotal($range) + $this->catalogPlatformShare($range), 2);
    }

    public function count(ReportingRange $range): int
    {
        return (int) $this->feeQuery($range)->count()
            + (int) $this->livePaidOrdersQuery($range)->count();
    }

    /**
     * @return list<array{type: string, count: int, total: float}>
     */
    public function byType(ReportingRange $range): array
    {
        $rows = $this->feeQuery($range)
            ->selectRaw('type, count(*) as count, sum(amount) as total')
            ->groupBy('type')
            ->get()
            ->map(fn ($row) => [
                'type' => (string) $row->type,
                'count' => (int) $row->count,
                'total' => round((float) $row->total, 2),
            ])
            ->all();

        $orders = $this->livePaidOrdersQuery($range)->get();
        $share = 0.0;
        foreach ($orders as $order) {
            $share += $this->platformShareForOrder($order);
        }
        if ($orders->isNotEmpty() || $share > 0) {
            $rows[] = [
                'type' => 'catalog_platform_share',
                'count' => $orders->count(),
                'total' => round($share, 2),
            ];
        }

        return $rows;
    }

    /**
     * Creator spend (paid platform orders), agent rewards paid, and platform keep — for the ledger-style pulse.
     *
     * @return array{
     *   creator_spend: float,
     *   agent_rewards_paid: float,
     *   platform_share: float,
     *   fee_revenue: float,
     *   platform_revenue: float
     * }
     */
    public function breakdown(ReportingRange $range): array
    {
        $creatorSpend = round((float) $this->livePaidOrdersQuery($range)
            ->sum(DB::raw('COALESCE(total_amount, amount)')), 2);

        $agentPaid = round((float) Transaction::query()
            ->where('status', 'completed')
            ->where('type', TransactionType::CampaignReward->value)
            ->where('amount', '>', 0)
            ->where('currency', config('platform.currency'))
            ->whereBetween('created_at', [$range->from, $range->to])
            ->sum('amount'), 2);

        $platformShare = round($this->catalogPlatformShare($range), 2);
        $fees = round($this->feeTotal($range), 2);

        return [
            'creator_spend' => $creatorSpend,
            'agent_rewards_paid' => $agentPaid,
            'platform_share' => $platformShare,
            'fee_revenue' => $fees,
            'platform_revenue' => round($platformShare + $fees, 2),
        ];
    }

    /**
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

    private function feeTotal(ReportingRange $range): float
    {
        return (float) $this->feeQuery($range)->sum('amount');
    }

    private function catalogPlatformShare(ReportingRange $range): float
    {
        $total = '0';
        foreach ($this->livePaidOrdersQuery($range)->cursor() as $order) {
            $total = Money::add($total, (string) $this->platformShareForOrder($order), 2);
        }

        return (float) $total;
    }

    private function platformShareForOrder(Order $order): float
    {
        $gross = Money::round((string) ($order->total_amount ?? $order->amount ?? 0), 2);
        $campaignId = Campaign::query()->where('order_id', $order->id)->value('id');
        if (! $campaignId) {
            return (float) $gross;
        }

        $funded = CampaignBudget::query()->where('campaign_id', $campaignId)->value('funded_amount');
        if ($funded === null) {
            return (float) $gross;
        }

        $share = Money::sub($gross, (string) $funded, 2);

        return Money::cmp($share, '0', 2) > 0 ? (float) $share : 0.0;
    }

    private function livePaidOrdersQuery(ReportingRange $range): Builder
    {
        $query = Order::query()
            ->where('source', 'platform')
            ->where('status', 'paid')
            ->whereBetween('created_at', [$range->from, $range->to]);

        SandboxCutover::excludeSandboxOrders($query);

        return $query;
    }

    private function feeQuery(ReportingRange $range): Builder
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
                    $q->orWhere(function (Builder $inner) use ($platformWalletId) {
                        $inner->where('type', TransactionType::WithdrawalFee->value)
                            ->where('wallet_id', $platformWalletId)
                            ->where('created_at', '>=', $this->feeSplitStartedAt());
                    });
                }
            });
    }

    public function feeSplitStartedAt(): CarbonImmutable
    {
        $raw = SystemSetting::get(WalletService::FEE_SPLIT_STARTED_SETTING);

        return $raw ? CarbonImmutable::parse((string) $raw) : CarbonImmutable::now()->addCentury();
    }
}
