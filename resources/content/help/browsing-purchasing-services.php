<?php

return [
    'slug' => 'browsing-purchasing-services',
    'category_key' => 'services',
    'title' => 'Browsing & purchasing social media services',
    'intro' => 'How to find Instagram, TikTok, YouTube, Twitter/X, and Facebook packs and complete checkout.',
    'summary' => 'Browse the social media catalog and pay securely.',
    'updated_at' => '2026-09-04',
    'printable' => true,
    'related' => ['getting-started', 'billing-wallets-payments'],
    'platform_actions' => [
        ['label' => 'Browse services', 'route' => 'services'],
        ['label' => 'My Orders', 'route' => 'dashboard.service-orders', 'auth' => true],
    ],
    'sections' => [
        [
            'id' => 'browse',
            'nav' => 'Browse',
            'title' => 'Browse social media packs',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Open Services to see Social Media packs. Each product page lists deliverables, pricing, and plan options.'],
            ],
        ],
        [
            'id' => 'checkout',
            'nav' => 'Checkout',
            'title' => 'Checkout',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Sign in, choose a variant/plan, then pay with wallet balance, card/transfer (Monnify), or manual bank transfer when those methods are enabled.'],
            ],
        ],
        [
            'id' => 'delivery',
            'nav' => 'Delivery',
            'title' => 'After you pay',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Paid orders appear under My Orders. Provisioned services show under My Tools for tracking and setup.'],
            ],
        ],
    ],
];
