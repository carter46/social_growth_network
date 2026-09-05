<?php

return [
    'slug' => 'getting-started',
    'category_key' => 'getting-started',
    'title' => 'Getting Started',
    'intro' => 'Create your account, verify your email, set up your wallet, and buy your first social media service.',
    'summary' => 'From registration to your first social media pack purchase.',
    'updated_at' => '2026-09-04',
    'hero_image' => 'assets/images/ai-powered-device-concept copy.jpg',
    'printable' => true,
    'related' => ['billing-wallets-payments', 'browsing-purchasing-services', 'keeping-account-secure'],
    'platform_actions' => [
        ['label' => 'Create account', 'route' => 'register'],
        ['label' => 'Open dashboard', 'route' => 'dashboard', 'auth' => true],
        ['label' => 'Browse services', 'route' => 'services'],
    ],
    'sections' => [
        [
            'id' => 'create-account',
            'nav' => 'Creating an account',
            'title' => 'Creating an account',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Visit Register, enter your details, and agree to the Terms and Privacy Policy.'],
                ['type' => 'tip', 'title' => 'Quick tip', 'content' => 'Use an email you can access — verification is required before many dashboard features.'],
            ],
        ],
        [
            'id' => 'email-verification',
            'nav' => 'Email verification',
            'title' => 'Email verification',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Confirm your email from the link we send. You can resend verification from the login or dashboard prompts if needed.'],
            ],
        ],
        [
            'id' => 'wallet',
            'nav' => 'Wallet setup',
            'title' => 'Wallet setup',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Open My Wallet in the dashboard to create your Naira wallet. KYC may be required depending on site settings before deposits.'],
            ],
        ],
        [
            'id' => 'first-purchase',
            'nav' => 'First purchase',
            'title' => 'Buy a social media service',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Open Services, choose a pack (Instagram, TikTok, YouTube, Twitter/X, or Facebook), pick a plan, and check out with wallet, card/transfer, or manual bank when enabled.'],
                ['type' => 'tip', 'title' => 'After payment', 'content' => 'Find your purchase under My Orders and My Tools.'],
            ],
        ],
        [
            'id' => 'support',
            'nav' => 'Support',
            'title' => 'Get help',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Use Help, Contact, or open a support ticket from your dashboard.'],
            ],
        ],
    ],
];
