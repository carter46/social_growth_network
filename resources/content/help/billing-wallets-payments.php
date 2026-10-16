<?php

return [
    'slug' => 'billing-wallets-payments',
    'category_key' => 'billing',
    'title' => 'Billing, wallets & payments',
    'intro' => 'Fund your US dollar (USD) wallet, pay for campaign packages, and withdraw available balance to your own payout account after verification.',
    'summary' => 'Fund campaigns with card or bank payments through our payment partners. Withdrawals are paid in USD and converted by the payout provider where needed.',
    'updated_at' => '2026-10-13',
    'printable' => true,
    'related' => ['getting-started', 'browsing-purchasing-services', 'keeping-account-secure'],
    'platform_actions' => [
        ['label' => 'Open wallet', 'route' => 'dashboard.wallet', 'auth' => true],
        ['label' => 'Browse services', 'route' => 'dashboard.services'],
    ],
    'sections' => [
        [
            'id' => 'funding',
            'nav' => 'Fund wallet',
            'title' => 'Funding your wallet',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Open Wallet in your dashboard to add USD balance before buying campaign packages. All prices and balances on the site are in US dollars. Available methods depend on site settings and may include:'],
                ['type' => 'bullets', 'items' => [
                    'Card or bank payment through one of our payment partners. You choose the option at checkout.',
                    'Manual bank transfers, checked by our support team before they are credited.',
                ]],
                ['type' => 'tip', 'title' => 'Timing', 'content' => 'Gateway payments often reflect within a few minutes. Manual transfers can take longer while admins verify payment details. If your card is in another currency, your bank may apply its own conversion.'],
            ],
        ],
        [
            'id' => 'payment',
            'nav' => 'Campaign payment',
            'title' => 'Paying for campaign packages',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'When you pay, Creators fund the selected package using wallet balance and/or other enabled payment methods. Review the order summary carefully before confirming.'],
                ['type' => 'important', 'title' => 'Secure payment protection', 'content' => 'Campaign progress is checked against your package rules before it counts toward your order. All payments go through the platform.'],
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
                    'Rewards are tied to verified task completion. Claiming a task alone does not earn a reward.',
                    'Small rewards are tracked to four decimal places, so fractions of a cent add up over time.',
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
                ['type' => 'paragraph', 'content' => 'Withdrawals move available USD from your wallet to a payout account in your own name. You can save up to three payout accounts and pick one as primary. Withdrawals go to the primary account. Supported countries and methods are shown when you add an account.'],
                ['type' => 'bullets', 'items' => [
                    'The withdrawal form shows the provider fee, any platform fee and the amount you receive before you confirm.',
                    'The minimum withdrawal can differ by country and method, because some routes have higher provider fees or minimums.',
                    'If the fee changes by more than a small margin before we send your payout, we ask you to accept the new fee or cancel. A cancelled withdrawal is returned to your wallet in full.',
                    'Your payout provider converts USD to your local currency where needed, at its own rate.',
                ]],
                ['type' => 'warning', 'title' => 'Account details', 'content' => 'You are responsible for accurate payout details. If a payout is returned because the account details were wrong, the full amount comes back to your wallet, minus the returned payout fee you accepted when adding the account (only when the provider charges us one). Contact support immediately if you notice an error.'],
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
