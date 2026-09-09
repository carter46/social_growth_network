<?php

return [
    'slug' => 'for-creators',
    'category_key' => 'creators',
    'title' => 'For Creators',
    'intro' => 'Launch campaign packages, set clear requirements, fund checkout, and track verified progress from your dashboard.',
    'summary' => 'Creators buy predefined campaign packages, provide a target URL and instructions, pay at secure checkout, and monitor task completion without managing Agents one by one.',
    'updated_at' => '2026-09-09',
    'hero_image' => 'assets/images/homeslider1.jpg',
    'printable' => true,
    'related' => ['getting-started', 'browsing-purchasing-services', 'billing-wallets-payments'],
    'platform_actions' => [
        ['label' => 'Browse services', 'route' => 'services'],
        ['label' => 'Create account', 'route' => 'register'],
        ['label' => 'For Creators page', 'route' => 'creators'],
    ],
    'sections' => [
        [
            'id' => 'creator-overview',
            'nav' => 'Overview',
            'title' => 'What Creators do',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'As a Creator you choose a campaign service, pick a predefined package tier, describe what Agents must complete, and fund the order through Platform checkout.'],
                ['type' => 'bullets', 'items' => [
                    'You do not pay Agents directly outside the Platform.',
                    'Clear instructions and realistic proof requirements speed up verification.',
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
                    'Enter your target URL and completion guidelines.',
                    'Confirm the order summary and complete secure checkout.',
                    'Track completions and proof outcomes from your dashboard.',
                ]],
            ],
        ],
        [
            'id' => 'requirements',
            'nav' => 'Good requirements',
            'title' => 'Writing good campaign requirements',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Agents succeed when your instructions are specific: which page to visit, what action to take, what proof to capture, and any geo or account rules that matter.'],
                ['type' => 'tip', 'title' => 'Tip', 'content' => 'Avoid vague instructions like “engage with my content.” Say exactly what counts as done and what screenshot or link you need.'],
            ],
        ],
        [
            'id' => 'after-checkout',
            'nav' => 'After checkout',
            'title' => 'After you pay',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Funds stay protected until verified task completion. Invalid proof can be rejected so you are not charged for work that misses your package rules. Use support tickets for payment or proof disputes with your order references attached.'],
            ],
        ],
    ],
];
