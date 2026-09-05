<?php

namespace App\Services\Analytics;

use App\Contracts\Analytics\AnalyticsServiceInterface;
use App\Models\User;

class AnalyticsService implements AnalyticsServiceInterface
{
    /** @var array<string, string> */
    public const SECTION_PERMISSIONS = [
        'traffic' => 'analytics.view',
        'revenue' => 'finance.manage',
        'services' => 'catalog.manage',
        'users' => 'users.manage',
        'support' => 'support.manage',
        'kyc' => 'compliance.manage',
        'business' => 'analytics.view',
        'products' => 'catalog.manage',
        'marketing' => 'analytics.view',
    ];

    public function __construct(
        private InternalBusinessProvider $business,
        private ProductAnalyticsProvider $products,
        private \App\Services\Reporting\ReportingService $reporting,
    ) {}

    public function getOverview(?User $user): array
    {
        $overview = [
            'generated_at' => now()->toDateTimeString(),
            'kpis' => [],
            'product_metrics' => [],
            'marketing' => [],
        ];

        if (! $user) {
            return $overview;
        }

        if ($user->can('users.manage') || $user->can('analytics.view')) {
            $overview['kpis']['users_total'] = $this->business->kpis()['users_total'];
        }

        if ($user->can('finance.manage') || $user->can('analytics.view')) {
            $kpis = $this->business->kpis();
            $overview['kpis']['sales_total_ngn'] = $kpis['sales_total_ngn'];
            $overview['kpis']['transactions_total'] = $kpis['transactions_total'];
        }

        if ($user->can('support.manage') || $user->can('analytics.view')) {
            $kpis = $this->business->kpis();
            $overview['kpis']['tickets_total'] = $kpis['tickets_total'];
            $overview['kpis']['tickets_open'] = $kpis['tickets_open'];
        }

        if ($user->can('analytics.view')) {
            $overview['kpis'] = array_merge($overview['kpis'], [
                'orders_by_status' => $this->business->kpis()['orders_by_status'],
            ]);
            $overview['product_metrics'] = $this->products->topMetrics();
            $overview['marketing'] = $this->business->marketingSnapshots();
        }

        return $overview;
    }

    public function getReport(string $section, array $filters, ?User $user): array
    {
        if (! $user) {
            return ['error' => 'Forbidden'];
        }

        $permission = self::SECTION_PERMISSIONS[$section] ?? null;
        if (! $permission || ! $user->can($permission)) {
            return ['error' => 'Forbidden'];
        }

        $range = $this->parseRange($filters);
        $days = $range['days'];

        if (in_array($section, ['traffic', 'revenue', 'services', 'users', 'support', 'kyc'], true)) {
            return [
                'section' => $section,
                'range' => $range,
                'data' => $this->business->sectionReport($section, $range),
            ];
        }

        return match ($section) {
            'business' => [
                'section' => 'business',
                'range' => $range,
                'data' => $this->business->kpis($filters['period'] ?? 'current'),
            ],
            'products' => [
                'section' => 'products',
                'range' => $range,
                'data' => $this->products->topMetrics($days),
            ],
            'marketing' => [
                'section' => 'marketing',
                'range' => $range,
                'data' => $this->business->marketingSnapshots($days),
            ],
            default => ['error' => 'Unknown section'],
        };
    }

    /**
     * @return list<string>
     */
    public function allowedSections(User $user): array
    {
        $sections = [];
        foreach (['traffic', 'revenue', 'services', 'users', 'support', 'kyc'] as $section) {
            $permission = self::SECTION_PERMISSIONS[$section];
            if ($user->can($permission)) {
                $sections[] = $section;
            }
        }

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{range: string, from: string, to: string, days: int}
     */
    public function parseRange(array $filters): array
    {
        $resolved = \App\Services\Reporting\ReportingRange::fromInput($filters, '30d');

        return [
            'range' => $resolved->key === 'prior' ? 'custom' : $resolved->key,
            'from' => $resolved->from->toDateString(),
            'to' => $resolved->to->toDateString(),
            'days' => $resolved->days(),
            '_from' => $resolved->from,
            '_to' => $resolved->to,
        ];
    }

    /**
     * @return list<array{key: string, label: string, labels: list<string>, values: list<float|null>}>
     */
    public function chartStrip(?User $user, int $days = 7): array
    {
        if (! $user || ! $user->can('analytics.view')) {
            return [];
        }

        $range = \App\Services\Reporting\ReportingRange::fromInput(['range' => $days <= 1 ? 'today' : $days.'d'], '7d');

        return $this->reporting->chartStrip($range);
    }

    public function reporting(): \App\Services\Reporting\ReportingService
    {
        return $this->reporting;
    }
}
