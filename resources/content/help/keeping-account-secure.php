<?php

return [
    'slug' => 'keeping-account-secure',
    'category_key' => 'security',
    'title' => 'Security, trust & verification',
    'intro' => 'Protect your login, complete KYC when required, understand proof standards, and report abuse safely.',
    'summary' => 'Trust on Social Growth Network depends on secure accounts, verified identity where needed, and honest proof from Agents.',
    'updated_at' => '2026-09-09',
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
                ['type' => 'paragraph', 'content' => 'Your account controls wallet funds, campaign purchases, and Agent earnings. Treat login security as part of payment security.'],
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
                ['type' => 'paragraph', 'content' => 'Depending on site settings and activity level, KYC (Know Your Customer) may be required before wallet funding, larger withdrawals, or higher-tier campaigns. Submit accurate government-issued identification and any requested details from your account settings.'],
                ['type' => 'important', 'title' => 'Why KYC exists', 'content' => 'Identity checks reduce fraud, protect Creators’ campaign budgets, and help Agents receive reliable payouts through compliant channels.'],
            ],
        ],
        [
            'id' => 'proof-standards',
            'nav' => 'Proof standards',
            'title' => 'Proof standards for Agents',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Proof must match the campaign instructions. Blurry, cropped, edited, reused, or unrelated evidence can be rejected.'],
                ['type' => 'bullets', 'items' => [
                    'Capture the full required screen or URL evidence when screenshots are requested.',
                    'Do not use bots, farms, or fake engagement to simulate completion.',
                    'If proof is rejected, read the reason carefully before resubmitting.',
                ]],
                ['type' => 'tip', 'title' => 'Creators', 'content' => 'Clear instructions and realistic proof requirements make verification faster and reduce disputes.'],
            ],
        ],
        [
            'id' => 'reporting',
            'nav' => 'Report issues',
            'title' => 'Reporting invalid proof or suspicious activity',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'If you see fraudulent proof, suspicious tasks, phishing attempts, or unauthorized wallet activity, report it through official support channels with evidence (order IDs, screenshots, timestamps).'],
                ['type' => 'warning', 'title' => 'Support safety', 'content' => 'Official support will never ask for your password. Use Help Center articles, the Contact page, or dashboard tickets only — not random social DMs claiming to be staff.'],
            ],
        ],
        [
            'id' => 'support-safety',
            'nav' => 'Support safety',
            'title' => 'Safe support practices',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'For payment audits, campaign adjustments, and proof disputes, open a tracked ticket from your dashboard when logged in, or start from the Contact page. Keep communication inside official channels so records stay attached to your account.'],
            ],
        ],
    ],
];
