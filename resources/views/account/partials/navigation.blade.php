@php
    $items = [
        'profile' => ['label' => 'Profile', 'icon' => 'user'],
        'security' => ['label' => 'Security', 'icon' => 'lock'],
    ];

    // KYC lives under Account for Creators and Agents (not Admin).
    if (in_array(($prefix ?? ''), ['dashboard', 'agent'], true)) {
        $items['kyc'] = ['label' => 'KYC', 'icon' => 'kyc'];
    }

    $items['preferences'] = ['label' => 'Preferences', 'icon' => 'tune'];
    $items['sessions'] = ['label' => 'Sessions', 'icon' => 'monitoring'];

    $active = collect($items)->keys()->first(function ($key) use ($prefix) {
        if ($key === 'kyc') {
            return request()->routeIs(
                $prefix.'.account.kyc',
                $prefix.'.kyc',
                $prefix.'.kyc.*',
            );
        }

        return request()->routeIs($prefix.'.account.'.$key);
    }) ?? 'profile';

    $tabs = collect($items)->map(fn ($item, $key) => [
        'id' => $key,
        'label' => $item['label'],
        'href' => route($prefix.'.account.'.$key),
    ])->values()->all();
@endphp

<nav data-account-menu aria-label="Account settings">
    <x-dashboard.ajax-tabs
        variant="pills"
        :active="$active"
        :tabs="$tabs"
    />
</nav>
