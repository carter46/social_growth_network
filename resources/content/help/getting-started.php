<?php

return [
    'slug' => 'getting-started',
    'category_key' => 'getting-started',
    'title' => 'Getting Started',
    'intro' => 'Create your account, verify your email, set up your wallet, and launch your first YouTube promotion campaign on Social Growth Network.',
    'summary' => 'Social Growth Network helps creators boost the visibility and reach of their YouTube videos and channels with clear, fixed-price promotion campaigns.',
    'updated_at' => '2026-10-15',
    'hero_image' => 'assets/images/ai-powered-device-concept copy.jpg',
    'printable' => true,
    'related' => ['browsing-purchasing-services', 'billing-wallets-payments', 'keeping-account-secure'],
    'platform_actions' => [
        ['label' => 'Create an account', 'route' => 'register'],
        ['label' => 'Browse campaigns', 'route' => 'services'],
    ],
    'sections' => [
        [
            'id' => 'what-we-do',
            'nav' => 'What we do',
            'title' => 'What Social Growth Network does',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'We run YouTube promotion campaigns that help creators, brands and businesses get their videos seen by more viewers:'],
                ['type' => 'bullets', 'items' => [
                    'Choose a campaign, pick a package, add your video or channel link, pay securely and track progress.',
                    'Campaigns are designed to grow visibility, reach, watch time and engagement.',
                    'We don\'t use bots, fake views or fake subscribers.',
                ]],
                ['type' => 'tip', 'title' => 'Quick tip', 'content' => 'You can explore the Services catalog before registering. Payment requires an account.'],
            ],
        ],
        [
            'id' => 'create-account',
            'nav' => 'Create an account',
            'title' => 'Creating your account',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Use Register to create your account. Enter accurate details and agree to the Terms of Service and Privacy Policy.'],
                ['type' => 'checklist', 'items' => [
                    'Use an email address you can access. Verification is required before many dashboard features.',
                    'Keep your password private and unique to this account.',
                ]],
            ],
        ],
        [
            'id' => 'email-verification',
            'nav' => 'Email verification',
            'title' => 'Email verification',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Confirm your email using the code we send after registration. If you do not see the message, check spam or request a new code from the verification screen.'],
                ['type' => 'important', 'title' => 'Why it matters', 'content' => 'Verified email protects your wallet and campaign purchases from unauthorized access.'],
            ],
        ],
        [
            'id' => 'wallet',
            'nav' => 'Wallet setup',
            'title' => 'Wallet setup',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Your US dollar (USD) wallet is where you fund campaign purchases. Open Wallet from your dashboard after email verification to view your balance and funding options.'],
                ['type' => 'tip', 'title' => 'Next step', 'content' => 'See Billing, wallets & payments for funding your USD wallet and payment basics.'],
            ],
        ],
        [
            'id' => 'first-steps',
            'nav' => 'First campaign',
            'title' => 'Your first campaign',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Open Services, choose a YouTube campaign, enter your video or channel URL and any instructions, then complete secure payment with your funded wallet or another enabled payment method.'],
                ['type' => 'success', 'title' => 'After you start', 'content' => 'Track campaign progress from your dashboard.'],
            ],
        ],
        [
            'id' => 'support',
            'nav' => 'Get help',
            'title' => 'Get help',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Use this Help Center for guides and FAQs. For account-specific issues (payments, campaigns, verification), open a support ticket from your dashboard or visit the Contact page.'],
            ],
        ],
    ],
];
