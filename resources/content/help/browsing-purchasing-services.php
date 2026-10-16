<?php

return [
    'slug' => 'browsing-purchasing-services',
    'category_key' => 'services',
    'title' => 'Campaigns & packages',
    'intro' => 'How to choose a YouTube promotion campaign, launch it and follow its progress.',
    'summary' => 'Browse the campaign catalog, pick a package with a clear price, add your video or channel link, and track progress from your dashboard.',
    'updated_at' => '2026-10-15',
    'printable' => true,
    'related' => ['getting-started', 'billing-wallets-payments', 'keeping-account-secure'],
    'platform_actions' => [
        ['label' => 'Browse services', 'route' => 'services'],
        ['label' => 'For Creators', 'route' => 'creators'],
    ],
    'sections' => [
        [
            'id' => 'browse',
            'nav' => 'Browse campaigns',
            'title' => 'Browse campaign services',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Open Services to explore YouTube campaigns and packages. Each product page explains what the campaign includes, the package tiers, and what you need to provide (video or channel URL and any instructions).'],
                ['type' => 'bullets', 'items' => [
                    'Packages are set campaign offers with a fixed price.',
                    'Pricing and campaign rules are shown before you pay.',
                    'We don\'t use bots, fake views or fake subscribers.',
                ]],
            ],
        ],
        [
            'id' => 'creator-launch',
            'nav' => 'Launch a campaign',
            'title' => 'Launching a campaign',
            'blocks' => [
                ['type' => 'checklist', 'items' => [
                    'Select the campaign and package tier that matches your goal.',
                    'Enter your video or channel URL and any instructions for the campaign.',
                    'Review the order summary, then pay with wallet balance or another enabled payment method.',
                    'Monitor progress from your dashboard after payment clears.',
                ]],
                ['type' => 'important', 'title' => 'Secure payment', 'content' => 'Campaign progress is checked against the package rules before it counts toward your order.'],
            ],
        ],
        [
            'id' => 'tracking',
            'nav' => 'Track progress',
            'title' => 'Tracking orders and progress',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'After payment, follow campaign status and progress from your dashboard (orders and related campaign views).'],
                ['type' => 'tip', 'title' => 'Need help?', 'content' => 'Payment or campaign questions should go through support tickets so our support team can review your order records.'],
            ],
        ],
    ],
];
