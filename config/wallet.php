<?php

return [
    'default_provider' => env('WALLET_PROVIDER', 'manual'),

    /*
    |--------------------------------------------------------------------------
    | Nigerian payout banks
    |--------------------------------------------------------------------------
    | Matched case-insensitively against the gateway's Nigerian bank list. Other
    | countries show the gateway list as returned. Heritage Bank is excluded
    | (license revoked). Duplicate labels are deduped by code.
    */
    'allowed_bank_patterns' => [
        'access bank',
        'citibank',
        'ecobank',
        'fidelity bank',
        'first bank',
        'fcmb',
        'first city monument',
        'globus bank',
        'guaranty trust',
        'gtbank',
        'jaiz bank',
        'keystone bank',
        'lotus bank',
        'optimus bank',
        'parallex bank',
        'polaris bank',
        'premium trust bank',
        'providus bank',
        'stanbic ibtc',
        'sterling bank',
        'suntrust bank',
        'titan trust bank',
        'union bank',
        'united bank for africa',
        'uba',
        'unity bank',
        'wema bank',
        'zenith bank',
        '9psb',
        '9 payment service bank',
        '9mobile',
        'hope payment service bank',
        'moneymaster psb',
        'palmpay',
        'opay',
        'moniepoint',
        'kuda',
    ],

    'excluded_bank_patterns' => [
        'heritage bank',
    ],
];
