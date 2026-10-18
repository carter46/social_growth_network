<?php

return [
    'articles_path' => resource_path('content/help'),

    /*
    |--------------------------------------------------------------------------
    | Help Center categories (hub cards)
    |--------------------------------------------------------------------------
    */
    'categories' => [
        [
            'key' => 'getting-started',
            'article' => 'getting-started',
            'title' => 'Getting Started',
            'description' => 'Create your account, verify email, set up your wallet, and take your first step.',
            'icon' => 'rocket_launch',
            'cta' => 'Open guide',
            'tone' => 'primary',
            'badge' => 'Start here',
        ],
        [
            'key' => 'services',
            'article' => 'browsing-purchasing-services',
            'title' => 'Campaigns & Packages',
            'description' => 'Choose a campaign package, launch it, and track its progress.',
            'icon' => 'view_kanban',
            'cta' => 'Open guide',
            'tone' => 'amber',
            'badge' => 'Core flow',
        ],
        [
            'key' => 'billing',
            'article' => 'billing-wallets-payments',
            'title' => 'Billing & Payments',
            'description' => 'Fund your wallet, pay for campaigns, and sort out payment issues.',
            'icon' => 'account_balance',
            'cta' => 'Open guide',
            'tone' => 'emerald',
            'badge' => 'Wallet',
        ],
        [
            'key' => 'security',
            'article' => 'keeping-account-secure',
            'title' => 'Security & Trust',
            'description' => 'KYC, account safety, your YouTube channel connection, and reporting abuse.',
            'icon' => 'verified_user',
            'cta' => 'Open guide',
            'tone' => 'sky',
            'badge' => 'Trust',
        ],
        [
            'key' => 'creators',
            'article' => 'for-creators',
            'title' => 'For Creators',
            'description' => 'Pick a package, set requirements, pay securely, and track progress.',
            'icon' => 'campaign',
            'cta' => 'Open guide',
            'tone' => 'violet',
            'badge' => 'Creators',
        ],
    ],

    'faqs' => [
        [
            'q' => 'How do I fund my wallet?',
            'a' => 'Open your dashboard Wallet, enter an amount in US dollars (USD) and choose one of the payment options shown. Gateway payments usually reflect within a few minutes. Manual bank transfers are checked by our team and added to your wallet.',
            'article' => 'billing-wallets-payments',
            'section' => 'funding',
            'icon' => 'account_balance_wallet',
        ],
        [
            'q' => 'How do I start a campaign?',
            'a' => 'Browse the Services catalog, select the package that fits your goal, enter your YouTube video or channel link and any campaign instructions, then continue to checkout. Review the order summary and confirm payment using your funded wallet balance or another enabled payment method.',
            'article' => 'browsing-purchasing-services',
            'section' => 'creator-launch',
            'icon' => 'shopping_bag',
        ],
        [
            'q' => 'How does secure payment protect Creators?',
            'a' => 'When you launch a campaign, progress is checked against the package rules before it counts toward your order. Progress that does not meet the rules is rejected, and you may receive a replacement or refund per platform rules.',
            'article' => 'billing-wallets-payments',
            'section' => 'payment',
            'icon' => 'verified_user',
        ],
        [
            'q' => 'Why should I connect my YouTube channel?',
            'a' => 'Connecting your channel is optional. It confirms the channel is yours, fills in your channel details, and lets you pick your own videos when you set up a campaign. Access is read-only, so we can never post, comment or change anything on your channel, and you can disconnect it at any time from your settings.',
            'article' => 'keeping-account-secure',
            'section' => 'youtube-channel',
            'icon' => 'link',
        ],
        [
            'q' => 'When do I need to verify my identity (KYC)?',
            'a' => 'Most accounts only need a verified email. We may ask for identity verification (KYC) before larger payments or where the law requires it, to reduce fraud and protect your campaign budget. You can submit it from your account settings.',
            'article' => 'keeping-account-secure',
            'section' => 'kyc',
            'icon' => 'badge',
        ],
    ],
];
