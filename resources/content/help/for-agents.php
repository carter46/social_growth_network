<?php

return [
    'slug' => 'for-agents',
    'category_key' => 'agents',
    'title' => 'For Agents',
    'intro' => 'Discover eligible tasks, complete Creator requirements, submit proof, and earn rewards after verification.',
    'summary' => 'Agents claim campaign tasks, follow instructions carefully, submit verifiable proof, and receive payouts once work is approved.',
    'updated_at' => '2026-09-09',
    'hero_image' => 'assets/images/homeslider3.jpg',
    'printable' => true,
    'related' => ['getting-started', 'browsing-purchasing-services', 'keeping-account-secure'],
    'platform_actions' => [
        ['label' => 'Apply as Agent', 'route' => 'register.agent'],
        ['label' => 'For Agents page', 'route' => 'agents'],
        ['label' => 'Help Center', 'route' => 'help'],
    ],
    'sections' => [
        [
            'id' => 'agent-overview',
            'nav' => 'Overview',
            'title' => 'What Agents do',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'As an Agent you browse available campaign tasks, claim work you can finish correctly, complete the required activity, and submit proof through the Platform.'],
                ['type' => 'bullets', 'items' => [
                    'Only claim tasks you can complete on time and as written.',
                    'Rewards unlock after proof approval — not when you claim a task.',
                    'Never ask Creators for off-platform payment.',
                ]],
            ],
        ],
        [
            'id' => 'claiming',
            'nav' => 'Claim tasks',
            'title' => 'Finding and claiming tasks',
            'blocks' => [
                ['type' => 'checklist', 'items' => [
                    'Open your Agent dashboard marketplace after registration and verification.',
                    'Read the Creator instructions and proof rules before claiming.',
                    'Claim only what you can complete with genuine activity.',
                ]],
            ],
        ],
        [
            'id' => 'submit-proof',
            'nav' => 'Submit proof',
            'title' => 'Submitting proof',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Proof usually means screenshots, tracking URLs, or other evidence defined by the campaign. Capture the full required screen, keep text readable, and avoid edited or reused images.'],
                ['type' => 'warning', 'title' => 'Rejected proof', 'content' => 'Fake, incomplete, or unrelated proof can be rejected and may limit marketplace access if abuse continues.'],
            ],
        ],
        [
            'id' => 'earnings',
            'nav' => 'Earnings',
            'title' => 'Earnings and withdrawals',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Approved rewards credit toward your withdrawable balance. Withdrawals may require KYC and follow scheduled clearing cycles. See Billing, wallets & payments for funding and withdrawal details.'],
            ],
        ],
    ],
];
