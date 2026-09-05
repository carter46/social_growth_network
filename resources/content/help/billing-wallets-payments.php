<?php

return [
    'slug' => 'billing-wallets-payments',
    'category_key' => 'billing',
    'title' => 'Billing, wallets & payments',
    'intro' => 'Fund your Naira wallet, pay for social media services, and withdraw when available.',
    'summary' => 'Wallet deposits, checkout methods, and withdrawals.',
    'updated_at' => '2026-09-04',
    'printable' => true,
    'related' => ['getting-started', 'browsing-purchasing-services'],
    'platform_actions' => [
        ['label' => 'My Wallet', 'route' => 'dashboard.wallet', 'auth' => true],
    ],
    'sections' => [
        [
            'id' => 'funding',
            'nav' => 'Funding',
            'title' => 'Funding your wallet',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'From My Wallet, start a bank deposit when enabled. Deposits may require admin review before credit. KYC may be required based on site settings.'],
            ],
        ],
        [
            'id' => 'checkout',
            'nav' => 'Paying for services',
            'title' => 'Paying for services',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'At checkout you can use wallet balance, Monnify card/transfer, or manual bank transfer when those options are turned on by the site.'],
            ],
        ],
        [
            'id' => 'withdrawals',
            'nav' => 'Withdrawals',
            'title' => 'Withdrawals',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Withdrawals move Naira from your wallet to your bank account, subject to limits, KYC, and admin processing.'],
            ],
        ],
    ],
];
