<?php

return [
    'slug' => 'for-creators',
    'category_key' => 'creators',
    'title' => 'For Creators',
    'intro' => 'Launch YouTube promotion campaigns, pay securely, and track progress from your dashboard.',
    'summary' => 'Creators choose a campaign package, provide a video or channel link and any instructions, complete secure payment, and follow progress from the dashboard.',
    'updated_at' => '2026-10-15',
    'hero_image' => 'assets/images/homeslider1.jpg',
    'printable' => true,
    'related' => ['getting-started', 'browsing-purchasing-services', 'billing-wallets-payments'],
    'platform_actions' => [
        ['label' => 'Browse services', 'route' => 'services'],
        ['label' => 'Create account', 'route' => 'register'],
    ],
    'sections' => [
        [
            'id' => 'creator-overview',
            'nav' => 'Overview',
            'title' => 'What Creators do',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'As a Creator you choose a YouTube campaign, pick a package tier, add your video or channel link, and pay through the platform. Campaigns are designed to boost the visibility, reach and engagement of your content.'],
                ['type' => 'bullets', 'items' => [
                    'We don\'t use bots, fake views or fake subscribers.',
                    'Clear instructions help your campaign run smoothly.',
                    'Progress and order status live in your dashboard after payment.',
                ]],
            ],
        ],
        [
            'id' => 'launch',
            'nav' => 'Launch a campaign',
            'title' => 'Launching a campaign',
            'blocks' => [
                ['type' => 'checklist', 'items' => [
                    'Open Services and select the package that matches your goal.',
                    'Enter your video or channel URL and any campaign instructions.',
                    'Confirm the order summary and complete secure payment.',
                    'Track progress from your dashboard.',
                ]],
            ],
        ],
        [
            'id' => 'requirements',
            'nav' => 'Good instructions',
            'title' => 'Writing good campaign instructions',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Campaigns run best when your instructions are specific, for example what comments should focus on.'],
                ['type' => 'tip', 'title' => 'Tip', 'content' => 'Avoid vague instructions like “engage with my content.” Say exactly what you would like the campaign to highlight.'],
            ],
        ],
        [
            'id' => 'after-payment',
            'nav' => 'After payment',
            'title' => 'After you pay',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Campaign progress is checked against your package rules before it counts toward your order. Use support tickets for payment or campaign questions, with your order references attached.'],
            ],
        ],
    ],
];
