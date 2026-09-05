@extends('layouts.dashboard-admin')

@section('title', 'Overview')

@section('content')
<div
    class="space-y-12"
    data-command-overview
    data-overview-endpoint="{{ route('admin.overview.panel') }}"
>
    <x-dashboard.command.page-toolbar
        :greeting="($greeting ?? 'Hello').', '.($adminName ?? 'Admin')"
        subtitle="Command center — platform alerts and quick actions."
        :breadcrumb="[['Admin'], ['Overview']]"
        :range="$rangeKey ?? '24h'"
    />

    <div id="command-live" class="space-y-12">
        @include('dashboard.admin.partials.overview-live')
    </div>

    <section class="space-y-4">
        <x-dashboard.command.section-label title="Operations & Health" accent="orange" />

        @if ($canFinance ?? false)
            <x-dashboard.command.tx-table
                :rows="$recentTransactions ?? []"
                :view-all-url="route('admin.transactions')"
            />
        @endif

        @if ($canSystem ?? false)
            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-2">
                <x-dashboard.command.health-panel
                    :rings="$health['rings'] ?? []"
                    :metrics="$health['metrics'] ?? []"
                    :checked-at="$health['checked_at'] ?? null"
                    :view-more-url="route('admin.monitoring')"
                    :limit="5"
                />
                <x-dashboard.command.audit-timeline
                    :entries="$recentAudit ?? []"
                    :console-url="route('admin.audit-logs')"
                />
            </div>
        @elseif ($canAnalytics ?? false)
            <a href="{{ route('admin.analytics') }}" class="block rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md dark:border-border-default dark:bg-elevated">
                <p class="text-sm font-bold text-slate-900 dark:text-text-primary">Analytics drill-down</p>
                <p class="mt-1 text-xs text-slate-500">Traffic, revenue, services, and ops reports with shared ranges.</p>
            </a>
        @endif
    </section>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-command-overview]');
    if (!root || !window.bindCommandRange) return;
    window.bindCommandRange(root, {
        endpoint: root.dataset.overviewEndpoint,
        onHtml(html) {
            const live = document.getElementById('command-live');
            if (!live) return;
            live.innerHTML = html;
            window.mountCommandCharts?.(live);
        },
    });
});
</script>
@endpush
@endsection
