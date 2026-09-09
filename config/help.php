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
            'description' => 'Create a Creator or Agent account, verify email, set up your wallet, and take your first campaign or task step.',
            'icon' => 'rocket_launch',
            'cta' => 'Explore all onboarding guides',
            'tone' => 'primary',
        ],
        [
            'key' => 'services',
            'article' => 'browsing-purchasing-services',
            'title' => 'Campaigns, packages & tasks',
            'description' => 'Launch predefined campaign packages as a Creator, or claim tasks, submit proof, and earn as an Agent.',
            'icon' => 'view_kanban',
            'cta' => 'Explore campaign guides',
            'tone' => 'warning',
        ],
        [
            'key' => 'billing',
            'article' => 'billing-wallets-payments',
            'title' => 'Billing, Wallets & Payments',
            'description' => 'Fund your Naira wallet, pay for campaigns with Monnify, receive Agent rewards, and withdraw securely.',
            'icon' => 'account_balance',
            'cta' => 'Read payment docs',
            'tone' => 'success',
        ],
        [
            'key' => 'security',
            'article' => 'keeping-account-secure',
            'title' => 'Security, Trust & Verification',
            'description' => 'Account safety, KYC, proof standards, and how to report invalid submissions or suspicious activity.',
            'icon' => 'verified_user',
            'cta' => 'View security policies',
            'tone' => 'info',
        ],
    ],

    'faqs' => [
        [
            'q' => 'How do I fund my wallet?',
            'a' => 'Open your dashboard Wallet, then use Monnify instant checkout or your dedicated reserved virtual bank account when available. Automated bank transfers usually reflect within a few minutes. Offline bank deposits are reviewed and credited by platform administrators during clearing windows.',
            'article' => 'billing-wallets-payments',
            'section' => 'funding',
            'icon' => 'account_balance_wallet',
        ],
        [
            'q' => 'How do I buy a campaign service?',
            'a' => 'Browse the Services catalog, select the package that fits your goal, enter your target URL and completion guidelines, then click Buy Now. Review the order summary and confirm payment using your funded wallet balance or another enabled checkout method.',
            'article' => 'browsing-purchasing-services',
            'section' => 'creator-launch',
            'icon' => 'shopping_cart_checkout',
        ],
        [
            'q' => 'How does secure checkout protect Creators?',
            'a' => 'When you launch a campaign, package funds are held securely until work is completed and verified against your defined proof requirements. If an Agent submits incomplete or invalid proof, submissions can be rejected and you may receive a replacement or refund per platform rules.',
            'article' => 'billing-wallets-payments',
            'section' => 'checkout',
            'icon' => 'verified_user',
        ],
        [
            'q' => 'How do Agents submit proof and receive rewards?',
            'a' => 'After claiming an eligible task, follow the Creator’s instructions carefully. Submit verifiable screenshots, tracking URLs, or other required proof through the submission flow. Once reviewed and approved, rewards release to your withdrawable earnings balance.',
            'article' => 'browsing-purchasing-services',
            'section' => 'proof',
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
