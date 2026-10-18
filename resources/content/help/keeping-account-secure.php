<?php

return [
    'slug' => 'keeping-account-secure',
    'category_key' => 'security',
    'title' => 'Security, trust & verification',
    'intro' => 'Protect your login, complete KYC when required, connect your YouTube channel safely, and report problems.',
    'summary' => 'Keep your account secure, verify your identity when asked, and stay in control of your YouTube channel connection.',
    'updated_at' => '2026-10-19',
    'printable' => true,
    'related' => ['getting-started', 'billing-wallets-payments', 'browsing-purchasing-services'],
    'platform_actions' => [
        ['label' => 'Account settings', 'route' => 'dashboard.account.profile', 'auth' => true],
        ['label' => 'Contact support', 'route' => 'contact'],
    ],
    'sections' => [
        [
            'id' => 'passwords',
            'nav' => 'Passwords & access',
            'title' => 'Passwords and account access',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Your account controls your wallet funds and campaign purchases. Treat login security as part of payment security.'],
                ['type' => 'checklist', 'items' => [
                    'Use a strong, unique password.',
                    'Never share your password, OTP, or recovery codes.',
                    'Sign out on shared devices and keep your email account secure.',
                    'Contact support immediately if you notice unfamiliar logins or transactions.',
                ]],
            ],
        ],
        [
            'id' => 'kyc',
            'nav' => 'KYC',
            'title' => 'KYC verification',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Depending on site settings and activity level, KYC (Know Your Customer) may be required before larger payments or higher-tier campaigns. Submit accurate government-issued identification and any requested details from your account settings.'],
                ['type' => 'important', 'title' => 'Why KYC exists', 'content' => 'Identity checks reduce fraud and protect your campaign budget and payments.'],
            ],
        ],
        [
            'id' => 'youtube-channel',
            'nav' => 'YouTube channel',
            'title' => 'Connecting your YouTube channel',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Connecting your YouTube channel is optional. It confirms the channel is yours, fills in your channel details, and lets you pick your own videos when you set up a campaign.'],
                ['type' => 'bullets', 'items' => [
                    'Connect with Google gives us read-only access. We can never post, comment, upload, delete or change anything on your channel.',
                    'You can also connect by adding a short code to your channel description.',
                    'Disconnect your channel or remove Google access at any time from the YouTube channel page in your settings. We email you when a channel is disconnected.',
                    'We will never ask for your YouTube or Google password.',
                ]],
            ],
        ],
        [
            'id' => 'reporting',
            'nav' => 'Report issues',
            'title' => 'Reporting suspicious activity',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'If you see phishing attempts, unfamiliar logins, or unauthorized wallet activity, report it through official support channels with evidence (order IDs, screenshots, timestamps).'],
                ['type' => 'warning', 'title' => 'Support safety', 'content' => 'Official support will never ask for your password. Use Help Center articles, the Contact page, or dashboard tickets only. Ignore random social DMs claiming to be staff.'],
            ],
        ],
        [
            'id' => 'support-safety',
            'nav' => 'Support safety',
            'title' => 'Safe support practices',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'For payment questions, campaign adjustments, and delivery issues, open a tracked ticket from your dashboard when logged in, or start from the Contact page. Keep communication inside official channels so records stay attached to your account.'],
            ],
        ],
    ],
];
