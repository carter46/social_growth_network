{{-- AJAX-refreshed pulse + growth + ops distributions --}}
<section id="command-pulse" class="space-y-4">
    <x-dashboard.command.section-label
        title="Business Pulse"
        accent="primary"
        action="Metrics Detail"
        :action-href="($canAnalytics ?? false) ? route('admin.analytics', ['section' => 'revenue', 'range' => $rangeKey ?? '24h']) : null"
    />
    @if (! empty($pulseItems))
        <x-dashboard.command.pulse-grid :items="$pulseItems" />
    @else
        <p class="text-sm text-slate-400">No pulse metrics available for your permissions.</p>
    @endif
</section>

@if (! empty($quickActions))
    <section id="command-quick-actions" class="space-y-4">
        <x-dashboard.command.section-label title="Platform Quick Actions" accent="indigo" />
        <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($quickActions as $action)
                <x-dashboard.command.quick-action
                    :title="$action['title']"
                    :subtitle="$action['subtitle'] ?? null"
                    :icon="$action['icon']"
                    :href="$action['href']"
                    :accent="$action['accent'] ?? 'emerald'"
                />
            @endforeach
        </div>
    </section>
@endif

<section id="command-growth" class="space-y-4">
    <x-dashboard.command.section-label title="Growth & Performance" accent="blue" />
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
        <div class="lg:col-span-8">
            <x-dashboard.command.hero-chart
                title="Revenue & Transactional Volume"
                subtitle="Live data feed with emerald performance gradient"
                :labels="$growth['revenue']['labels'] ?? []"
                :values="$growth['revenue']['values'] ?? []"
                :compare-values="$growth['revenue_prior']['values'] ?? null"
                :height="'20rem'"
                id="overview-revenue-chart"
            />
        </div>
        <div class="lg:col-span-4">
            <x-dashboard.command.distribution-card
                title="Order Status"
                :center-value="number_format(collect($distributions['orders'] ?? [])->sum('value'))"
                center-label="Orders"
                :slices="$distributions['orders'] ?? []"
                id="overview-orders-donut"
            />
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
        <x-dashboard.command.mini-chart
            title="Wallet Fundings"
            subtitle="Approved fundings in range"
            theme="bar"
            color="#6366f1"
            :labels="$growth['fundings']['labels'] ?? []"
            :values="$growth['fundings']['values'] ?? []"
            id="overview-fundings-bar"
        />
        <x-dashboard.command.mini-chart
            title="New Users"
            subtitle="Registrations over time"
            theme="line"
            color="#3b82f6"
            :labels="$growth['users']['labels'] ?? []"
            :values="$growth['users']['values'] ?? []"
            id="overview-users-line"
        />
        <x-dashboard.command.distribution-card
            title="Support Tickets"
            :center-value="number_format(collect($distributions['support'] ?? [])->sum('value'))"
            center-label="Tickets"
            :slices="$distributions['support'] ?? []"
            id="overview-support-donut"
        />
    </div>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <x-dashboard.command.distribution-card
            title="KYC Status"
            :center-value="number_format(collect($distributions['kyc'] ?? [])->sum('value'))"
            center-label="Submissions"
            :slices="$distributions['kyc'] ?? []"
            id="overview-kyc-donut"
        />
        <x-dashboard.command.distribution-card
            title="Escrow Status"
            :center-value="number_format(collect($distributions['escrows'] ?? [])->sum('value'))"
            center-label="Escrows"
            :slices="$distributions['escrows'] ?? []"
            id="overview-escrow-donut"
        />
    </div>
</section>
