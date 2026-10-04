<x-dashboard.table
    :empty="$tools->isEmpty()"
    empty-title="No websites yet"
    empty-description="When you buy a website package or template from the catalog, it appears here for setup and access."
    empty-icon="listings"
    striped
>
    <x-slot:filters>
        <x-dashboard.filter-bar>
            <form method="GET" class="contents">
                <div class="min-w-[10rem] flex-1">
                    <x-dashboard.input name="q" type="text" :value="$filters['q'] ?? ''" placeholder="Search websites..." />
                </div>
                <div class="min-w-[8rem]">
                    <x-dashboard.select name="status">
                        <option value="">All statuses</option>
                        @foreach (['pending_setup', 'active', 'suspended', 'expired'] as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                        @endforeach
                    </x-dashboard.select>
                </div>
                <label class="flex items-center gap-2 text-sm text-text-secondary">
                    <input type="checkbox" name="expiring_soon" value="1" @checked($filters['expiring_soon'] ?? false) class="rounded border-border-default">
                    Expiring soon
                </label>
                <x-dashboard.button type="submit" variant="secondary">Filter</x-dashboard.button>
            </form>
        </x-dashboard.filter-bar>
    </x-slot:filters>
    <x-slot:head>
        <x-dashboard.th>Name</x-dashboard.th>
        <x-dashboard.th>Type</x-dashboard.th>
        <x-dashboard.th>Status</x-dashboard.th>
        <x-dashboard.th>Expires</x-dashboard.th>
        <x-dashboard.th></x-dashboard.th>
    </x-slot:head>
    @foreach ($tools as $tool)
        <tr>
            <x-dashboard.td class="font-medium text-text-primary">{{ $tool->resolvedDisplayName() }}</x-dashboard.td>
            <x-dashboard.td class="text-xs text-text-secondary">{{ $tool->product?->product_type?->label() ?? '-' }}</x-dashboard.td>
            <x-dashboard.td>
                <x-dashboard.badge :status="$tool->status->value" />
                @if ($tool->isExpiringSoon())
                    <span class="ml-1 text-xs text-amber-600">Expiring soon</span>
                @endif
            </x-dashboard.td>
            <x-dashboard.td class="text-xs text-text-muted">{{ $tool->expires_at?->format('j M Y') ?? '-' }}</x-dashboard.td>
            <x-dashboard.td>
                <x-dashboard.button :href="route('dashboard.my-tools.show', $tool)" size="sm">View</x-dashboard.button>
            </x-dashboard.td>
        </tr>
    @endforeach
</x-dashboard.table>
<x-dashboard.pagination :paginator="$tools" />
