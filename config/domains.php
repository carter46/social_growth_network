<?php

return [
    'quote_ttl_minutes' => (int) env('DOMAIN_QUOTE_TTL_MINUTES', 15),

    'price_drift_tolerance_percent' => (float) env('DOMAIN_PRICE_DRIFT_TOLERANCE_PERCENT', 2),

    'tld_cache_ttl_minutes' => (int) env('DOMAIN_TLD_CACHE_TTL_MINUTES', 60),

    'http_timeout_seconds' => (int) env('DOMAIN_HTTP_TIMEOUT_SECONDS', 15),

    'registration_product_slug' => 'domain-registration',

    'auto_register_on_purchase' => (bool) env('DOMAIN_AUTO_REGISTER', true),

    'common_tlds' => ['com', 'net', 'org', 'io', 'co', 'ng'],

    // Shown first in admin + customer domain search; also the default allowed set for new domain products.
    'default_product_tlds' => [
        'com', 'agency', 'bet', 'biz', 'ca', 'cc', 'charity', 'click', 'club', 'co',
        'eu', 'fit', 'forex', 'fund', 'icu', 'info', 'llc', 'ltd', 'me', 'net',
        'ngo', 'online', 'org', 'pet', 'pro', 'shop', 'site', 'solutions', 'space',
        'store', 'us', 'uk', 'vip', 'xyz',
    ],

    'suggestion_limit' => (int) env('DOMAIN_SUGGESTION_LIMIT', 3),

    'suggestion_max_attempts' => (int) env('DOMAIN_SUGGESTION_MAX_ATTEMPTS', 8),

    // Tried first when building alternate available extensions after a search.
    'suggestion_preferred_tlds' => ['online', 'net', 'pro'],

    'default_nameservers' => array_values(array_filter([
        env('DOMAIN_NS1'),
        env('DOMAIN_NS2'),
    ])),
    // Platform default nameservers used only when initially registering a new domain.
    // Not the authoritative current state after a customer changes nameservers via My Domains.

    'registration_contacts' => [
        // Legacy/dev fallback only — checkout collects per-customer registrant details.
        'first_name' => env('DOMAIN_CONTACT_FIRST_NAME', 'Domain'),
        'last_name' => env('DOMAIN_CONTACT_LAST_NAME', 'Admin'),
        'company' => env('DOMAIN_CONTACT_COMPANY', config('app.name', 'Social Growth Network')),
        'email' => env('DOMAIN_CONTACT_EMAIL', 'domains@7thtradehub.online'),
        'phone' => env('DOMAIN_CONTACT_PHONE', '+234.8000000000'),
        'address' => env('DOMAIN_CONTACT_ADDRESS', 'Lagos'),
        'city' => env('DOMAIN_CONTACT_CITY', 'Lagos'),
        'state' => env('DOMAIN_CONTACT_STATE', 'LA'),
        'zip' => env('DOMAIN_CONTACT_ZIP', '100001'),
        'country' => env('DOMAIN_CONTACT_COUNTRY', 'NG'),
    ],

    'providers' => [
        'namecom' => [
            'display_name' => 'Name.com',
            'adapter' => \App\Services\Domains\Providers\NameCom\NameComProvider::class,
            'capabilities' => ['search', 'availability', 'registration_quote', 'tld_pricing', 'register', 'nameserver_read', 'nameserver_update'],
            'credential_keys' => ['username', 'api_token'],
            'credential_labels' => [
                'username' => 'Username',
                'api_token' => 'API token',
            ],
            'sandbox_hint' => 'Sandbox API username is typically your production username with "-test" appended (see Name.com docs).',
        ],
        'domainnameapi' => [
            'display_name' => 'DomainNameAPI',
            'adapter' => \App\Services\Domains\Providers\DomainNameApi\DomainNameApiProvider::class,
            'capabilities' => ['search', 'availability', 'registration_quote', 'tld_pricing', 'register', 'nameserver_read', 'nameserver_update'],
            'credential_keys' => ['reseller_id', 'api_key'],
            'credential_labels' => [
                'reseller_id' => 'Reseller ID',
                'api_key' => 'API key',
            ],
        ],
    ],
];
