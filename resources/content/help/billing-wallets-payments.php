<?php

return [
    'slug' => 'billing-wallets-payments',
    'category_key' => 'billing',
    'title' => 'Billing, wallets & payments',
    'intro' => 'Fund your US dollar (USD) wallet, pay for campaign packages, and get help with payment issues.',
    'summary' => 'Fund campaigns with card or bank payments through our payment partners. All prices and balances are in US dollars.',
    'updated_at' => '2026-10-19',
    'printable' => true,
    'related' => ['getting-started', 'browsing-purchasing-services', 'keeping-account-secure'],
    'platform_actions' => [
        ['label' => 'Open wallet', 'route' => 'dashboard.wallet', 'auth' => true],
        ['label' => 'Browse services', 'route' => 'services'],
    ],
    'sections' => [
        [
            'id' => 'funding',
            'nav' => 'Fund wallet',
            'title' => 'Funding your wallet',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Open Wallet in your dashboard to add USD balance before buying campaign packages. All prices and balances on the site are in US dollars. Available methods depend on site settings and may include:'],
                ['type' => 'bullets', 'items' => [
                    'Card or bank payment through one of our payment partners. You choose the option at checkout.',
                    'Manual bank transfers, checked by our support team before they are credited.',
                ]],
                ['type' => 'tip', 'title' => 'Timing', 'content' => 'Gateway payments often reflect within a few minutes. Manual transfers can take longer while admins verify payment details. If your card is in another currency, your bank may apply its own conversion.'],
            ],
        ],
        [
            'id' => 'payment',
            'nav' => 'Campaign payment',
            'title' => 'Paying for campaign packages',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'When you pay, you fund the selected package using your wallet balance or another enabled payment method. Review the order summary carefully before confirming.'],
                ['type' => 'important', 'title' => 'Secure payment protection', 'content' => 'Campaign progress is checked against your package rules before it counts toward your order. All payments go through the platform.'],
                ['type' => 'checklist', 'items' => [
                    'Confirm package tier, quantity, and target requirements.',
                    'Ensure your wallet has enough balance (or complete gateway payment).',
                    'Keep the order confirmation for your records if you need support later.',
                ]],
            ],
        ],
        [
            'id' => 'disputes',
            'nav' => 'Payment issues',
            'title' => 'Payment issues and disputes',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'If a deposit does not appear, a payment fails, or a campaign is not delivered as described, open a support ticket with transaction references, screenshots, and timestamps. Approved refunds are usually credited to your wallet. Do not share passwords or one-time codes with anyone claiming to be support.'],
            ],
        ],
    ],
];
