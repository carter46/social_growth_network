<?php

return [
    'slug' => 'browsing-purchasing-services',
    'category_key' => 'services',
    'title' => 'Campaigns, packages & tasks',
    'intro' => 'How Creators launch campaign packages and how Agents discover tasks, submit proof, and get paid after verification.',
    'summary' => 'Browse the campaign catalog, buy a predefined package as a Creator, or claim and complete tasks as an Agent with clear proof requirements.',
    'updated_at' => '2026-09-09',
    'printable' => true,
    'related' => ['getting-started', 'billing-wallets-payments', 'keeping-account-secure'],
    'platform_actions' => [
        ['label' => 'Browse services', 'route' => 'services'],
        ['label' => 'For Creators', 'route' => 'creators'],
        ['label' => 'For Agents', 'route' => 'agents'],
    ],
    'sections' => [
        [
            'id' => 'browse',
            'nav' => 'Browse campaigns',
            'title' => 'Browse campaign services',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Open Services to explore campaign categories and predefined packages. Each product page explains deliverables, package tiers, and what Creators must provide (target URL, guidelines, and any geo or audience notes).'],
                ['type' => 'bullets', 'items' => [
                    'Packages are structured campaign offers — not one-off “do anything” gigs.',
                    'Pricing and completion rules are shown before you pay so Creators know what they are funding.',
                    'Agents see eligible tasks that match active campaigns once work is available in the marketplace.',
                ]],
            ],
        ],
        [
            'id' => 'creator-launch',
            'nav' => 'Launch as Creator',
            'title' => 'Launching a campaign as a Creator',
            'blocks' => [
                ['type' => 'checklist', 'items' => [
                    'Select the service and package tier that matches your goal.',
                    'Enter your target URL and clear completion instructions for Agents.',
                    'Review the order summary, then pay with wallet balance or another enabled payment method.',
                    'Monitor progress and proof outcomes from your dashboard after payment clears.',
                ]],
                ['type' => 'important', 'title' => 'Secure payment', 'content' => 'Campaign funds are held securely until tasks are completed and verified against your requirements. Invalid or incomplete proof can be rejected so you are not charged for work that does not meet the package rules.'],
            ],
        ],
        [
            'id' => 'agent-tasks',
            'nav' => 'Complete as Agent',
            'title' => 'Claiming and completing tasks as an Agent',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Agents discover open tasks tied to live campaigns. Only claim work you can finish correctly and on time.'],
                ['type' => 'checklist', 'items' => [
                    'Read the Creator’s instructions and proof requirements carefully before starting.',
                    'Complete the required activity on the target platform or URL exactly as specified.',
                    'Submit proof through the platform submission flow (screenshots, links, or other required evidence).',
                    'Wait for automated and/or human review before rewards are released to your earnings balance.',
                ]],
                ['type' => 'warning', 'title' => 'Quality matters', 'content' => 'Fake, recycled, or incomplete proof can be rejected. Repeated abuse may limit marketplace access.'],
            ],
        ],
        [
            'id' => 'proof',
            'nav' => 'Proof & verification',
            'title' => 'Proof submission and verification',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'Proof is how the platform confirms a task was done. Requirements vary by campaign but usually include clear screenshots, tracking URLs, timestamps, or other evidence defined by the Creator and package rules.'],
                ['type' => 'bullets', 'items' => [
                    'Approved proof releases the Agent reward for that task.',
                    'Rejected proof may allow resubmission or reassignment depending on campaign rules.',
                    'Creators can track verification progress without managing every Agent manually.',
                ]],
            ],
        ],
        [
            'id' => 'tracking',
            'nav' => 'Track progress',
            'title' => 'Tracking orders and progress',
            'blocks' => [
                ['type' => 'paragraph', 'content' => 'After payment, Creators follow campaign status and completions from the dashboard (orders and related campaign views). Agents track claimed tasks, proof status, and earnings from their Agent dashboard.'],
                ['type' => 'tip', 'title' => 'Need help?', 'content' => 'Payment or proof disputes should go through support tickets so the operations desk can review wallet and submission records.'],
            ],
        ],
    ],
];
