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
            'description' => 'Account setup, email verification, wallet creation, and your first steps on the platform.',
            'icon' => 'rocket',
            'cta' => 'Read guide',
            'tone' => 'primary',
        ],
        [
            'key' => 'services',
            'article' => 'browsing-purchasing-services',
            'title' => 'Service Management',
            'description' => 'Browse and buy platform services — social growth packs and more.',
            'icon' => 'grid',
            'cta' => 'Read guide',
            'tone' => 'warning',
        ],
        [
            'key' => 'billing',
            'article' => 'billing-wallets-payments',
            'title' => 'Billing & Payments',
            'description' => 'Fund your Naira wallet, Monnify checkout, reserved accounts, and withdrawal basics.',
            'icon' => 'wallet',
            'cta' => 'Read guide',
            'tone' => 'success',
        ],
        [
            'key' => 'security',
            'article' => 'keeping-account-secure',
            'title' => 'Security & Trust',
            'description' => 'KYC verification and keeping your account secure.',
            'icon' => 'lock',
            'cta' => 'Read guide',
            'tone' => 'info',
        ],
    ],

    'faqs' => [
        [
            'q' => 'How do I fund my wallet?',
            'a' => 'Open your dashboard Wallet, then use Monnify checkout or your reserved account when available. Bank deposits are reviewed by admin.',
            'article' => 'billing-wallets-payments',
            'section' => 'funding',
        ],
        [
            'q' => 'How do I buy a service?',
            'a' => 'Browse Services, open a product, choose a plan if available, then Buy Now. You will be asked to log in if needed, then continue to platform checkout with your wallet.',
            'article' => 'browsing-purchasing-services',
            'section' => 'checkout',
        ],
    ],
];
