<?php

return [
    'slug' => 'getting-started',
    'category_key' => 'getting-started',
    'title' => 'Getting Started',
    'intro' => 'Create your account as a Creator or Agent, verify your email, set up your wallet, and take your first step on Social Growth Network.',
    'summary' => 'Social Growth Network is a two-sided campaign marketplace: Creators launch digital campaigns with predefined packages; Agents complete tasks, submit proof, and earn after verification.',
    'updated_at' => '2026-09-09',
    'hero_image' => 'assets/images/ai-powered-device-concept copy.jpg',
    'printable' => true,
    'related' => ['browsing-purchasing-services', 'billing-wallets-payments', 'keeping-account-secure'],
    'platform_actions' => [
        ['label' => 'Create Creator account', 'route' => 'register'],
        ['label' => 'Apply as Agent', 'route' => 'register.agent'],
        ['label' => 'Browse campaign services', 'route' => 'services'],
    ],
    'sections' => [
        [
            'id' => 'choose-role',
            'nav' => 'Choose your role',
            'title' => 'Creators and Agents',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Social Growth Network connects two sides of every campaign:'],
                ['type' => 'bullets', 'items' => [
                    'Creators (businesses, brands, organizations, and individuals) choose a campaign service, pick a predefined package, set requirements, pay at checkout, and track progress.',
                    'Agents discover eligible campaign tasks, complete the required activity, submit verifiable proof, and earn rewards when work is approved.',
                ]],
                ['type' => 'tip', 'title' => 'Quick tip', 'content' => 'You can explore the Services catalog before registering. Checkout and task claiming require an account.'],
            ],
        ],
        [
            'id' => 'create-account',
            'nav' => 'Create an account',
            'title' => 'Creating your account',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Use Register to create a Creator account for launching campaigns, or Apply as Agent if you want to complete tasks and earn. Enter accurate details and agree to the Terms of Service and Privacy Policy.'],
                ['type' => 'checklist', 'items' => [
                    'Choose the registration path that matches how you will use the platform.',
                    'Use an email address you can access — verification is required before many dashboard features.',
                    'Keep your password private and unique to this account.',
                ]],
            ],
        ],
        [
            'id' => 'email-verification',
            'nav' => 'Email verification',
            'title' => 'Email verification',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Confirm your email using the link we send after registration. If you do not see the message, check spam or request a new verification email from the login or dashboard prompts.'],
                ['type' => 'important', 'title' => 'Why it matters', 'content' => 'Verified email protects your wallet, campaign purchases, and agent payouts from unauthorized access.'],
            ],
        ],
        [
            'id' => 'wallet',
            'nav' => 'Wallet setup',
            'title' => 'Wallet setup',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Your Naira wallet is where Creators fund campaign purchases and where Agents receive approved rewards. Open Wallet from your dashboard after email verification to view balance, funding options, and withdrawal status.'],
                ['type' => 'tip', 'title' => 'Next step', 'content' => 'See Billing, wallets & payments for Monnify checkout, reserved accounts, and withdrawal basics.'],
            ],
        ],
        [
            'id' => 'first-steps',
            'nav' => 'First campaign or task',
            'title' => 'Your first campaign or task',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Creators: open Services, choose a campaign package (for example YouTube, Instagram, TikTok, Facebook, or X growth), enter your target URL and instructions, then complete secure checkout with your funded wallet or enabled payment method.'],
                ['type' => 'paragraph', 'content' => 'Agents: browse available tasks from your dashboard marketplace, claim work you can complete correctly, follow the Creator’s instructions, and submit proof for review.'],
                ['type' => 'success', 'title' => 'After you start', 'content' => 'Creators track campaign progress from the dashboard. Agents monitor claimed tasks, proof status, and earnings until payouts become withdrawable.'],
            ],
        ],
        [
            'id' => 'support',
            'nav' => 'Get help',
            'title' => 'Get help',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Use this Help Center for guides and FAQs. For account-specific issues (payments, proof disputes, KYC), open a support ticket from your dashboard or visit the Contact page.'],
            ],
        ],
    ],
];
