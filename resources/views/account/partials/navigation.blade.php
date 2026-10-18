@php
    $isMember = in_array(($prefix ?? ''), ['dashboard', 'agent'], true);
    $items = [
        'profile' => ['label' => 'Profile', 'icon' => 'user'],
        'security' => ['label' => 'Security', 'icon' => 'lock'],
    ];

    // KYC lives under Account for Creators and Agents (not Admin).
    if ($isMember) {
        $items['kyc'] = ['label' => 'KYC', 'icon' => 'kyc'];
    }

    if ($isMember && \App\Services\Campaigns\YoutubeIdentityService::enabled()) {
        $items['youtube-channel'] = [
            'label' => 'YouTube channel',
            'icon' => 'verified',
            'route' => $prefix.'.youtube-channel.show',
            'match' => [$prefix.'.youtube-channel.*'],
        ];
    }

    $items['preferences'] = ['label' => 'Preferences', 'icon' => 'tune'];
    $items['sessions'] = ['label' => 'Sessions', 'icon' => 'monitoring'];

    $active = collect($items)->keys()->first(function ($key) use ($prefix, $items) {
        if ($key === 'kyc') {
            return request()->routeIs(
                $prefix.'.account.kyc',
                $prefix.'.kyc',
                $prefix.'.kyc.*',
            );
        }

        if (isset($items[$key]['match'])) {
            return request()->routeIs(...$items[$key]['match']);
        }

        return request()->routeIs($prefix.'.account.'.$key);
    }) ?? 'profile';

    $tabs = collect($items)->map(fn ($item, $key) => [
        'id' => $key,
        'label' => $item['label'],
        'href' => route($item['route'] ?? $prefix.'.account.'.$key),
    ])->values()->all();
@endphp

<nav data-account-menu aria-label="Account settings">
    <x-dashboard.ajax-tabs
        variant="pills"
        :active="$active"
        :tabs="$tabs"
    />
</nav>
