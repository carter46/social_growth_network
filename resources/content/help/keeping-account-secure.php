<?php

return [
    'slug' => 'keeping-account-secure',
    'category_key' => 'security',
    'title' => 'Keeping your account secure',
    'intro' => 'Protect your login, complete KYC when required, and use support safely.',
    'summary' => 'Account security and KYC basics.',
    'updated_at' => '2026-09-04',
    'printable' => true,
    'related' => ['getting-started', 'billing-wallets-payments'],
    'platform_actions' => [
        ['label' => 'Account settings', 'route' => 'dashboard.account.profile', 'auth' => true],
    ],
    'sections' => [
        [
            'id' => 'passwords',
            'nav' => 'Passwords',
            'title' => 'Passwords & access',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Use a strong unique password. Never share your login. Sign out on shared devices.'],
            ],
        ],
        [
            'id' => 'kyc',
            'nav' => 'KYC',
            'title' => 'KYC verification',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Depending on settings, KYC may be required before wallet creation, deposits, or withdrawals. Submit accurate documents from your account settings.'],
            ],
        ],
        [
            'id' => 'support-safety',
            'nav' => 'Support safety',
            'title' => 'Support safety',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'We will never ask for your password. Use official Help, Contact, or dashboard tickets only.'],
            ],
        ],
    ],
];
