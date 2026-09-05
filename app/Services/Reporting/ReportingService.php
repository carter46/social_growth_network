<?php

namespace App\Services\Reporting;

use App\Models\AnalyticsGaSnapshot;
use App\Models\AnalyticsProvider;
use App\Models\AuditLog;
use App\Models\Transaction;
use App\Services\Reporting\Metrics\OpsMetrics;
use App\Services\Reporting\Metrics\PlatformRevenueMetric;

class ReportingService
{
    public function __construct(
        private PlatformRevenueMetric $revenue,
        private OpsMetrics $ops,
    ) {}

    /**
     * Command-center overview payload for a reporting range.
     *
     * @return array<string, mixed>
     */
    public function overview(ReportingRange $range): array
    {
        $revenue = $this->revenue->sum($range);
        $revenueDelta = $this->revenue->deltaPercent($range);
        $fundings = $this->ops->fundingsCount($range);
        $priorFundings = $this->ops->fundingsCount($range->priorPeriod());
        $fundingDelta = $this->percentDelta((float) $fundings, (float) $priorFundings);
        $newUsers = $this->ops->newUsers($range);
        $priorUsers = $this->ops->newUsers($range->priorPeriod());

        $revenueSeries = $this->revenue->chartSeries($range);
        $usersSeries = $this->ops->usersDailySeries($range);
        $fundingsSeries = $this->ops->fundingsDailySeries($range);
        $priorRevenueSeries = $this->revenue->chartSeries($range->priorPeriod());

        $kycSlices = $this->ops->kycStatusSlices();
        $supportSlices = $this->ops->supportStatusSlices();
        $orderSlices = $this->ops->orderStatusSlices($range);

        return [
            'range' => $range->toArray(),
            'pulse' => [
                'revenue' => [
                    'value' => $revenue,
                    'formatted' => '₦'.number_format((float) $revenue, 2, '.', ','),
                    'delta' => $revenueDelta,
                    'delta_label' => 'vs prior period',
                    'sparkline' => $revenueSeries['values'],
                    'description' => 'Platform fees + catalog sales',
                ],
                'visitors' => array_merge($this->visitorsPulse(), [
                    'description' => 'Sessions from Google Analytics',
                ]),
                'fundings' => [
                    'value' => $fundings,
                    'formatted' => (string) $fundings,
                    'delta' => $fundingDelta,
                    'delta_label' => 'vs prior period',
                    'sparkline' => $fundingsSeries['values'],
                    'description' => 'Approved wallet fundings',
                    'sum' => $this->ops->fundingsSum($range),
                ],
                'users' => [
                    'value' => $newUsers,
                    'formatted' => (string) $newUsers,
                    'delta' => $this->percentDelta((float) $newUsers, (float) $priorUsers),
                    'delta_label' => 'vs prior period',
                    'sparkline' => $usersSeries['values'],
                    'description' => 'New registrations',
                ],
                'pending_kyc' => $this->ops->pendingKyc(),
                'pending_withdrawals' => $this->ops->pendingWithdrawals(),
                'support_waiting' => $this->ops->supportWaiting(),
            ],
            'growth' => [
                'revenue' => $revenueSeries,
                'revenue_prior' => $priorRevenueSeries,
                'users' => $usersSeries,
                'fundings' => $fundingsSeries,
            ],
            'distributions' => [
                'kyc' => $kycSlices,
                'support' => $supportSlices,
                'orders' => $orderSlices,
            ],
            'gmv' => $this->ops->gmv($range),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function revenueSection(ReportingRange $range): array
    {
        return [
            'total_ngn' => $this->revenue->sum($range),
            'count' => $this->revenue->count($range),
            'by_type' => $this->revenue->byType($range),
            'delta_percent' => $this->revenue->deltaPercent($range),
            'series' => $this->revenue->dailySeries($range),
            'gmv' => $this->ops->gmv($range),
        ];
    }

    public function platformRevenue(ReportingRange $range): float
    {
        return $this->revenue->sum($range);
    }

    public function revenueDailySeries(ReportingRange $range): array
    {
        return $this->revenue->dailySeries($range);
    }

    /**
     * @return list<array{key: string, label: string, labels: list<string>, values: list<float|null>}>
     */
    public function chartStrip(ReportingRange $range): array
    {
        $revenue = $this->revenue->dailySeries($range);
        $users = $this->ops->usersDailySeries($range);
        $fundings = $this->ops->fundingsDailySeries($range);

        return [
            [
                'key' => 'revenue.daily',
                'label' => 'Revenue (NGN)',
                'labels' => $revenue['labels'],
                'values' => $revenue['values'],
            ],
            [
                'key' => 'users.daily',
                'label' => 'New users',
                'labels' => $users['labels'],
                'values' => $users['values'],
            ],
            [
                'key' => 'fundings.daily',
                'label' => 'Wallet fundings',
                'labels' => $fundings['labels'],
                'values' => $fundings['values'],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentTransactions(int $limit = 10): array
    {
        return Transaction::query()
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Transaction $tx) => [
                'id' => $tx->id,
                'reference' => $tx->reference,
                'type' => $tx->type,
                'user_name' => $tx->user?->name ?? '—',
                'user_initials' => $this->initials($tx->user?->name),
                'avatar_tone' => $this->avatarTone($tx->user?->name),
                'amount' => (float) $tx->amount,
                'status' => $tx->status,
                'created_at' => $tx->created_at?->diffForHumans(),
                'href' => route('admin.transactions'),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentAudit(int $limit = 8): array
    {
        return AuditLog::query()
            ->with('admin:id,name,email')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $log) => [
                'action' => $log->action,
                'actor' => $log->admin?->email ?? $log->admin?->name ?? 'system',
                'when' => $log->created_at?->diffForHumans(),
                'day' => $log->created_at?->toDateString(),
                'day_label' => $log->created_at?->isToday()
                    ? 'Today'
                    : ($log->created_at?->isYesterday() ? 'Yesterday' : $log->created_at?->format('M j, Y')),
                'icon' => $this->auditIcon((string) $log->action),
                'tone' => $this->auditTone((string) $log->action),
                'severity' => str_contains((string) $log->action, 'suspend')
                    || str_contains((string) $log->action, 'anonym'),
            ])
            ->all();
    }

    /**
     * @return array{enabled: bool, value: float|null, hint: string|null}
     */
    private function visitorsPulse(): array
    {
        $provider = AnalyticsProvider::forProvider(AnalyticsProvider::PROVIDER_GOOGLE_ANALYTICS);
        if (! $provider->enabled) {
            return ['enabled' => false, 'value' => null, 'hint' => 'Google Analytics disabled'];
        }

        try {
            $snap = AnalyticsGaSnapshot::query()
                ->whereDate('fetched_at', today())
                ->where('metric', 'sessions')
                ->orderByDesc('fetched_at')
                ->first();
        } catch (\Throwable) {
            return ['enabled' => true, 'value' => null, 'hint' => 'GA snapshot unavailable'];
        }

        return [
            'enabled' => true,
            'value' => $snap ? (float) ($snap->payload['value'] ?? 0) : null,
            'hint' => $snap ? null : 'Awaiting GA snapshot',
        ];
    }

    private function percentDelta(float $current, float $prior): float
    {
        if (abs($prior) < 0.00001) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $prior) / abs($prior)) * 100, 1);
    }

    private function initials(?string $name): string
    {
        if (! $name) {
            return '?';
        }
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $letters ?: '?';
    }

    private function avatarTone(?string $name): string
    {
        $tones = ['emerald', 'blue', 'indigo', 'amber', 'orange'];
        $idx = abs(crc32((string) $name)) % count($tones);

        return $tones[$idx];
    }

    private function auditIcon(string $action): string
    {
        return match (true) {
            str_contains($action, 'suspend'), str_contains($action, 'anonym') => 'audit',
            str_contains($action, 'impersonation.started') => 'person',
            str_contains($action, 'impersonation.stopped') => 'history',
            str_contains($action, 'settings') => 'settings',
            default => 'history',
        };
    }

    private function auditTone(string $action): string
    {
        return match (true) {
            str_contains($action, 'suspend') => 'red',
            str_contains($action, 'anonym') => 'slate',
            str_contains($action, 'impersonation') => 'brand',
            str_contains($action, 'settings') => 'blue',
            default => 'slate',
        };
    }
}
