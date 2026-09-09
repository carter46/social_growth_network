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
            'title' => 'Campaigns & Tasks',
            'description' => 'Launch packages as a Creator or claim and complete tasks as an Agent.',
            'icon' => 'view_kanban',
            'cta' => 'Open guide',
            'tone' => 'amber',
            'badge' => 'Core flow',
        ],
        [
            'key' => 'billing',
            'article' => 'billing-wallets-payments',
            'title' => 'Billing & Payments',
            'description' => 'Fund your wallet, pay for campaigns, earn rewards, and withdraw.',
            'icon' => 'account_balance',
            'cta' => 'Open guide',
            'tone' => 'emerald',
            'badge' => 'Wallet',
        ],
        [
            'key' => 'security',
            'article' => 'keeping-account-secure',
            'title' => 'Security & Trust',
            'description' => 'KYC, proof standards, account safety, and reporting abuse.',
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
        [
            'key' => 'agents',
            'article' => 'for-agents',
            'title' => 'For Agents',
            'description' => 'Discover tasks, submit proof correctly, and get paid after approval.',
            'icon' => 'task_alt',
            'cta' => 'Open guide',
            'tone' => 'rose',
            'badge' => 'Agents',
        ],
    ],

    'faqs' => [
        [
            'q' => 'How do I fund my wallet?',
            'a' => 'Open your dashboard Wallet, then use Monnify payment or your dedicated reserved virtual bank account when available. Automated bank transfers usually reflect within a few minutes. Offline bank deposits are reviewed and credited by platform administrators during clearing windows.',
            'article' => 'billing-wallets-payments',
            'section' => 'funding',
            'icon' => 'account_balance_wallet',
        ],
        [
            'q' => 'How do I buy a campaign service?',
            'a' => 'Browse the Services catalog, select the package that fits your goal, enter your target URL and completion guidelines, then click Buy Now. Review the order summary and confirm payment using your funded wallet balance or another enabled payment method.',
            'article' => 'browsing-purchasing-services',
            'section' => 'creator-launch',
            'icon' => 'shopping_bag',
        ],
        [
            'q' => 'How does secure payment protect Creators?',
            'a' => 'When you launch a campaign, package funds are held securely until work is completed and verified against your defined proof requirements. If an Agent submits incomplete or invalid proof, submissions can be rejected and you may receive a replacement or refund per platform rules.',
            'article' => 'billing-wallets-payments',
            'section' => 'payment',
            'icon' => 'verified_user',
        ],
        [
            'q' => 'How do Agents submit proof and receive rewards?',
            'a' => 'After claiming an eligible task, follow the Creator’s instructions carefully. Submit verifiable screenshots, tracking URLs, or other required proof through the submission flow. Once reviewed and approved, rewards release to your withdrawable earnings balance.',
            'article' => 'for-agents',
            'section' => 'submit-proof',
            'icon' => 'upload_file',
        ],
        [
            'q' => 'What are the KYC and withdrawal requirements?',
            'a' => 'Basic activity usually requires a verified email. Higher-tier payouts and larger withdrawal volumes may require KYC (government-issued identification and related verification) to reduce fraud and protect the marketplace. Withdrawal requests are processed to verified bank accounts within scheduled clearing cycles.',
            'article' => 'keeping-account-secure',
            'section' => 'kyc',
            'icon' => 'badge',
        ],
    ],
];
