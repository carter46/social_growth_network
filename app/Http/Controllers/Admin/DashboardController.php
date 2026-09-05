<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Analytics\AnalyticsServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\AnalyticsProvider;
use App\Services\Analytics\InternalBusinessProvider;
use App\Services\Reporting\ReportingRange;
use App\Services\Reporting\ReportingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private AnalyticsServiceInterface $analytics,
        private InternalBusinessProvider $business,
        private ReportingService $reporting,
    ) {}

    public function index(Request $request): View
    {
        $user = auth()->user();
        $range = $this->resolveOverviewRange($request);
        $payload = $this->overviewPayload($range, $user);

        return view('dashboard.admin.overview', $payload);
    }

    public function overviewPanel(Request $request): Response
    {
        $user = auth()->user();
        $range = $this->resolveOverviewRange($request, persist: true);
        $payload = $this->overviewPayload($range, $user);

        return response()
            ->view('dashboard.admin.partials.overview-live', $payload)
            ->header('Cache-Control', 'no-store');
    }

    public function analytics(Request $request): View|Response
    {
        $user = auth()->user();
        $allowedSections = $this->analytics->allowedSections($user);
        if ($allowedSections === []) {
            abort(403);
        }

        $section = $request->string('section')->toString() ?: 'all';
        $selectable = array_merge(['all'], $allowedSections);
        if (! in_array($section, $selectable, true)) {
            abort(403);
        }

        $sessionKey = 'admin.analytics.range.'.($section === 'all' ? 'all' : $section);
        $filters = [
            'range' => $request->string('range')->toString()
                ?: (string) session($sessionKey, '30d'),
            'from' => $request->string('from')->toString() ?: null,
            'to' => $request->string('to')->toString() ?: null,
        ];

        if ($request->filled('range') || $request->boolean('persist_range')) {
            session([$sessionKey => $filters['range']]);
        }

        $gaProvider = AnalyticsProvider::forProvider(AnalyticsProvider::PROVIDER_GOOGLE_ANALYTICS);
        $gaEnabled = $gaProvider->enabled;
        $gaConnected = $gaEnabled && $gaProvider->status === 'connected';
        $range = $this->analytics->parseRange($filters);

        $sectionBundles = [];
        $data = [];
        $productMetrics = [];
        $marketing = [];

        $buildBundle = function (string $sec) use ($filters, $user, $gaConnected, $range): array {
            $report = $this->analytics->getReport($sec, $filters, $user);
            if (($report['error'] ?? null) === 'Forbidden') {
                return ['data' => [], 'productMetrics' => [], 'marketing' => []];
            }

            $bundle = [
                'data' => $report['data'] ?? [],
                'productMetrics' => [],
                'marketing' => [],
            ];

            if ($sec === 'services' && $user?->can('catalog.manage')) {
                $bundle['productMetrics'] = app(\App\Services\Analytics\ProductAnalyticsProvider::class)
                    ->topMetrics((int) ($range['days'] ?? 30));
            }

            if ($sec === 'traffic' && $user?->can('analytics.view') && $gaConnected) {
                $bundle['marketing'] = $this->business->marketingSnapshots((int) ($range['days'] ?? 7));
            }

            return $bundle;
        };

        if ($section === 'all') {
            foreach ($allowedSections as $sec) {
                $sectionBundles[$sec] = $buildBundle($sec);
            }
        } else {
            $bundle = $buildBundle($section);
            $data = $bundle['data'];
            $productMetrics = $bundle['productMetrics'];
            $marketing = $bundle['marketing'];
        }

        $payload = [
            'section' => $section,
            'range' => $range,
            'filters' => $filters,
            'data' => $data,
            'gaEnabled' => $gaEnabled,
            'gaConnected' => $gaConnected,
            'productMetrics' => $productMetrics,
            'marketing' => $marketing,
            'sectionBundles' => $sectionBundles,
            'sections' => $selectable,
            'greeting' => $this->greeting(),
            'adminName' => $user?->name ?? 'Admin',
        ];

        if ($request->headers->get('X-Dashboard-Tab') === '1' || $request->boolean('partial')) {
            return response()
                ->view('dashboard.admin.partials.analytics-report', $payload)
                ->header('Cache-Control', 'no-store');
        }

        return view('dashboard.admin.analytics', $payload);
    }

    public function transactions(): View
    {
        $transactions = \App\Models\Transaction::with('user')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('dashboard.admin.transactions', [
            'transactions' => $transactions,
        ]);
    }

    public function social(): View
    {
        return view('dashboard.admin.social', [
            'items' => collect(),
        ]);
    }

    private function resolveOverviewRange(Request $request, bool $persist = true): ReportingRange
    {
        $input = [
            'range' => $request->string('range')->toString()
                ?: (string) session('admin.overview.range', '24h'),
            'from' => $request->string('from')->toString() ?: null,
            'to' => $request->string('to')->toString() ?: null,
        ];

        $range = ReportingRange::fromInput($input, '24h');

        if ($persist && ($request->filled('range') || $request->boolean('persist_range') || ! session()->has('admin.overview.range'))) {
            session(['admin.overview.range' => $range->key]);
        }

        return $range;
    }

    /**
     * @return array<string, mixed>
     */
    private function overviewPayload(ReportingRange $range, $user): array
    {
        $canFinance = $user?->can('finance.manage') ?? false;
        $canSupport = $user?->can('support.manage') ?? false;
        $canCompliance = $user?->can('compliance.manage') ?? false;
        $canAnalytics = $user?->can('analytics.view') ?? false;
        $canCatalog = $user?->can('catalog.manage') ?? false;
        $canSystem = $user?->can('system.manage') ?? false;

        $overview = ($canFinance || $canSupport || $canCompliance || $canAnalytics || $canCatalog)
            ? $this->reporting->overview($range)
            : ['pulse' => [], 'growth' => [], 'distributions' => [], 'range' => $range->toArray()];

        $pulse = $overview['pulse'] ?? [];
        $growth = $overview['growth'] ?? [];
        $supportWaiting = (int) ($pulse['support_waiting'] ?? 0);

        // Align prior series length to current for Chart.js compare line.
        if (isset($growth['revenue']['values'], $growth['revenue_prior']['values'])) {
            $len = count($growth['revenue']['values']);
            $prior = array_values($growth['revenue_prior']['values']);
            if (count($prior) > $len) {
                $prior = array_slice($prior, -$len);
            } elseif (count($prior) < $len) {
                $prior = array_pad($prior, $len, 0);
            }
            $growth['revenue_prior']['values'] = $prior;
        }

        return [
            'greeting' => $this->greeting(),
            'adminName' => $user?->name ?? 'Admin',
            'canFinance' => $canFinance,
            'canSupport' => $canSupport,
            'canCompliance' => $canCompliance,
            'canAnalytics' => $canAnalytics,
            'canCatalog' => $canCatalog,
            'canSystem' => $canSystem,
            'rangeKey' => $range->key,
            'rangeMeta' => $range->toArray(),
            'pulseItems' => $this->buildPulseItems(
                $pulse,
                $growth,
                $canFinance,
                $canAnalytics,
                $canCompliance,
                $canSupport,
                $canCatalog,
                $range->key,
            ),
            'growth' => $growth,
            'distributions' => $overview['distributions'] ?? [],
            'distribution' => $this->buildDistribution($growth),
            'recentTransactions' => $canFinance ? $this->reporting->recentTransactions(5) : [],
            'recentAudit' => $canSystem ? $this->reporting->recentAudit(5) : [],
            'health' => $canSystem ? app(\App\Services\Reporting\SystemHealthService::class)->snapshot() : ['rings' => [], 'metrics' => []],
            'quickActions' => $this->quickActions(),
            'supportWaiting' => $supportWaiting,
        ];
    }

    /**
     * @param  array<string, mixed>  $pulse
     * @param  array<string, mixed>  $growth
     * @return list<array<string, mixed>>
     */
    private function buildPulseItems(
        array $pulse,
        array $growth,
        bool $canFinance,
        bool $canAnalytics,
        bool $canCompliance,
        bool $canSupport,
        bool $canCatalog,
        string $rangeKey,
    ): array {
        $items = [];
        $rangeLabel = match ($rangeKey) {
            '24h' => 'Last 24 hours',
            '7d' => 'Last 7 days',
            '30d' => 'Last 30 days',
            '90d' => 'Last 90 days',
            default => 'Selected range',
        };

        if ($canFinance) {
            $rev = $pulse['revenue'] ?? [];
            $items[] = [
                'label' => 'Revenue',
                'value' => $rev['formatted']
                    ?? ('₦'.number_format((float) ($rev['value'] ?? 0), 2, '.', ',')),
                'accent' => 'emerald',
                'delta' => $rev['delta'] ?? null,
                'delta_label' => $rev['delta_label'] ?? 'vs prior period',
                'description' => $rev['description'] ?? $rangeLabel,
                'sparkline' => $rev['sparkline'] ?? ($growth['revenue']['values'] ?? []),
                'badge' => ['label' => 'Live', 'class' => 'bg-emerald-50 text-emerald-700'],
                'href' => $canAnalytics
                    ? route('admin.analytics', ['section' => 'revenue', 'range' => $rangeKey])
                    : route('admin.transactions'),
            ];
        }

        if ($canAnalytics) {
            $visitors = $pulse['visitors'] ?? [];
            if (($visitors['enabled'] ?? false) && ($visitors['value'] ?? null) !== null) {
                $items[] = [
                    'label' => 'Visitors',
                    'value' => number_format((float) $visitors['value']),
                    'accent' => 'blue',
                    'description' => $visitors['description'] ?? 'Sessions today',
                    'badge' => ['label' => 'GA', 'class' => 'bg-blue-50 text-blue-700'],
                    'href' => route('admin.analytics', ['section' => 'traffic', 'range' => '24h']),
                ];
            } elseif ((auth()->user()?->can('users.manage') ?? false) && ! empty($pulse['users'])) {
                $users = $pulse['users'];
                $items[] = [
                    'label' => 'New Users',
                    'value' => $users['formatted'] ?? '0',
                    'accent' => 'blue',
                    'delta' => $users['delta'] ?? null,
                    'delta_label' => $users['delta_label'] ?? 'vs prior period',
                    'description' => $users['description'] ?? $rangeLabel,
                    'sparkline' => $users['sparkline'] ?? ($growth['users']['values'] ?? []),
                    'href' => route('admin.users'),
                ];
            } else {
                $items[] = [
                    'label' => 'Visitors',
                    'value' => '—',
                    'accent' => 'blue',
                    'hint' => $visitors['hint'] ?? 'Google Analytics disabled',
                    'description' => 'Connect GA for live traffic',
                    'badge' => ['label' => 'Offline', 'class' => 'bg-slate-100 text-slate-500'],
                    'href' => route('admin.settings'),
                ];
            }
        }

        if ($canFinance) {
            $fundings = $pulse['fundings'] ?? [];
            $pendingWithdrawals = (int) ($pulse['pending_withdrawals'] ?? 0);
            $description = isset($fundings['sum'])
                ? '₦'.number_format((float) $fundings['sum'], 0).' funding volume'
                : ($fundings['description'] ?? $rangeLabel);
            if ($pendingWithdrawals > 0) {
                $description .= ' · '.number_format($pendingWithdrawals).' pending withdrawal'
                    .($pendingWithdrawals === 1 ? '' : 's');
            }
            $items[] = [
                'label' => 'Wallets Funded',
                'value' => $fundings['formatted'] ?? '0',
                'accent' => 'indigo',
                'delta' => $fundings['delta'] ?? null,
                'delta_label' => $fundings['delta_label'] ?? 'vs prior period',
                'description' => $description,
                'sparkline' => $fundings['sparkline'] ?? ($growth['fundings']['values'] ?? []),
                'badge' => $pendingWithdrawals > 0
                    ? ['label' => number_format($pendingWithdrawals).' pending', 'class' => 'bg-amber-50 text-amber-700']
                    : null,
                'href' => route('admin.fundings'),
            ];
        }

        if ($canCompliance) {
            $kyc = (int) ($pulse['pending_kyc'] ?? 0);
            $items[] = [
                'label' => 'Pending KYC',
                'value' => number_format($kyc),
                'accent' => 'amber',
                'description' => 'Identity verification queue',
                'badge' => $kyc > 0
                    ? ['label' => 'Action', 'class' => 'bg-amber-50 text-amber-700']
                    : ['label' => 'Clear', 'class' => 'bg-emerald-50 text-emerald-700'],
                'hint' => 'Open queue',
                'href' => route('admin.kyc', ['status' => 'pending']),
            ];
        }

        if ($canFinance) {
            $withdrawals = (int) ($pulse['pending_withdrawals'] ?? 0);
            $items[] = [
                'label' => 'Pending Withdrawals',
                'value' => number_format($withdrawals),
                'accent' => 'indigo',
                'description' => 'Awaiting admin review',
                'badge' => $withdrawals > 0
                    ? ['label' => 'Active', 'class' => 'bg-indigo-50 text-indigo-700']
                    : null,
                'href' => route('admin.withdrawals'),
            ];
        }

        if ($canSupport) {
            $sup = (int) ($pulse['support_waiting'] ?? 0);
            $items[] = [
                'label' => 'Support Waiting',
                'value' => number_format($sup),
                'accent' => 'orange',
                'description' => 'Open / awaiting tickets',
                'badge' => $sup > 5
                    ? ['label' => 'Busy', 'class' => 'bg-red-50 text-red-600']
                    : ['label' => 'Queue', 'class' => 'bg-orange-50 text-orange-700'],
                'href' => route('admin.tickets'),
            ];
        }

        return array_slice($items, 0, 6);
    }

    /**
     * @param  array<string, mixed>  $growth
     * @return list<array<string, mixed>>
     */
    private function buildDistribution(array $growth): array
    {
        $users = array_sum(array_map('floatval', $growth['users']['values'] ?? []));
        $fundings = array_sum(array_map('floatval', $growth['fundings']['values'] ?? []));
        $revenueDays = count(array_filter($growth['revenue']['values'] ?? [], fn ($v) => (float) $v > 0));
        $total = max(1.0, $users + $fundings + $revenueDays);

        $slices = [
            [
                'label' => 'New users',
                'value' => $users,
                'percent' => round(($users / $total) * 100).'%',
                'color' => '#3b82f6',
            ],
            [
                'label' => 'Fundings',
                'value' => $fundings,
                'percent' => round(($fundings / $total) * 100).'%',
                'color' => '#6366f1',
            ],
            [
                'label' => 'Revenue days',
                'value' => $revenueDays,
                'percent' => round(($revenueDays / $total) * 100).'%',
                'color' => '#10b981',
            ],
        ];

        return array_values(array_filter($slices, fn ($s) => (float) $s['value'] > 0));
    }

    private function greeting(): string
    {
        $hour = (int) now()->format('G');

        return match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };
    }

    /**
     * @return list<array{title: string, subtitle: string|null, href: string, icon: string, accent: string}>
     */
    private function quickActions(): array
    {
        $user = auth()->user();

        $actions = [
            [
                'title' => 'Manage Products',
                'subtitle' => 'Edit fixed platform product copy, prices, and status.',
                'href' => route('admin.platform-products'),
                'icon' => 'listings',
                'accent' => 'emerald',
                'permission' => 'catalog.manage',
            ],
            [
                'title' => 'Review KYC',
                'subtitle' => 'Identity verification queue.',
                'href' => route('admin.kyc', ['status' => 'pending']),
                'icon' => 'kyc',
                'accent' => 'amber',
                'permission' => 'compliance.manage',
            ],
            [
                'title' => 'Platform Orders',
                'subtitle' => 'Service purchases and manual payments.',
                'href' => route('admin.orders'),
                'icon' => 'orders',
                'accent' => 'blue',
                'permission' => 'finance.manage',
            ],
        ];

        return array_values(array_filter(
            $actions,
            fn (array $action): bool => $user?->can($action['permission']) ?? false,
        ));
    }
}
