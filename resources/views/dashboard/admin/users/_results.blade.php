@php
    $status = $status ?? 'active';
    $tabQuery = fn (string $tabStatus) => array_filter([
        'status' => $tabStatus,
        'role' => ($roleFilter ?? 'all') !== 'all' ? ($roleFilter ?? null) : null,
        'q' => ($search ?? '') !== '' ? $search : null,
    ]);
@endphp

<x-dashboard.ajax-tabs
    :active="$status"
    :tabs="[
        ['id' => 'active', 'label' => 'Active', 'href' => route('admin.users', $tabQuery('active')), 'count' => $activeCount ?? null],
        ['id' => 'suspended', 'label' => 'Suspended', 'href' => route('admin.users', $tabQuery('suspended')), 'count' => $suspendedCount ?? null],
    ]"
    class="mb-4"
/>

<div id="dashboard-tab-panel">
    @include('dashboard.admin.users._table')
</div>
