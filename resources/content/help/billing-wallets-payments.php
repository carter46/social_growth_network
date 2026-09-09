<?php

return [
    'slug' => 'billing-wallets-payments',
    'category_key' => 'billing',
    'title' => 'Billing, wallets & payments',
    'intro' => 'Fund your Naira wallet, pay for campaign packages, receive Agent rewards, and withdraw after verification.',
    'summary' => 'Creators fund campaigns through wallet and Monnify options. Agent rewards unlock after approved proof. Withdrawals follow KYC and clearing rules.',
    'updated_at' => '2026-09-09',
    'printable' => true,
    'related' => ['getting-started', 'browsing-purchasing-services', 'keeping-account-secure'],
    'platform_actions' => [
        ['label' => 'Open wallet', 'route' => 'dashboard.wallet', 'auth' => true],
        ['label' => 'Browse services', 'route' => 'services'],
    ],
    'sections' => [
        [
            'id' => 'funding',
            'nav' => 'Fund wallet',
            'title' => 'Funding your wallet',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Open Wallet in your dashboard to add Naira balance before buying campaign packages. Available methods depend on site settings and may include:'],
                ['type' => 'bullets', 'items' => [
                    'Monnify payment (card or transfer when enabled).',
                    'Dedicated reserved / virtual bank account deposits that credit after confirmation.',
                    'Manual bank transfer reviewed by administrators during clearing windows.',
                ]],
                ['type' => 'tip', 'title' => 'Timing', 'content' => 'Automated deposits often reflect within a few minutes. Manual transfers can take longer while admins verify payment details.'],
            ],
        ],
        [
            'id' => 'payment',
            'nav' => 'Campaign payment',
            'title' => 'Paying for campaign packages',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'When you pay, Creators fund the selected package using wallet balance and/or other enabled payment methods. Review the order summary carefully before confirming.'],
                ['type' => 'important', 'title' => 'Secure payment protection', 'content' => 'Funds for a campaign stay protected until tasks are completed and proof is verified. You are not expected to pay Agents directly outside the platform.'],
                ['type' => 'checklist', 'items' => [
                    'Confirm package tier, quantity, and target requirements.',
                    'Ensure your wallet has enough balance (or complete gateway payment).',
                    'Keep the order confirmation for your records if you need support later.',
                ]],
            ],
        ],
        [
            'id' => 'agent-rewards',
            'nav' => 'Agent rewards',
            'title' => 'Agent rewards and earnings',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'When an Agent’s proof is approved, the reward for that task is credited toward their withdrawable earnings according to platform rules. Pending or rejected proof does not pay out.'],
                ['type' => 'bullets', 'items' => [
                    'Rewards are tied to verified task completion — not to claiming a task alone.',
                    'Earnings appear in wallet / earnings views once released.',
                    'Chargebacks, fraud, or policy violations can reverse or hold payouts.',
                ]],
            ],
        ],
        [
            'id' => 'withdrawals',
            'nav' => 'Withdrawals',
            'title' => 'Withdrawals',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Withdrawals move available Naira from your wallet to a verified bank account. Processing depends on KYC status, withdrawal limits, and scheduled clearing cycles.'],
                ['type' => 'warning', 'title' => 'Bank details', 'content' => 'You are responsible for accurate bank information. Incorrect details you supply can delay or lose funds; contact support immediately if you notice an error.'],
            ],
        ],
        [
            'id' => 'disputes',
            'nav' => 'Payment issues',
            'title' => 'Payment issues and disputes',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'If a deposit does not appear, a payment fails, or a reward/withdrawal is delayed, open a support ticket with transaction references, screenshots, and timestamps. Do not share passwords or one-time codes with anyone claiming to be support.'],
            ],
        ],
    ],
];
