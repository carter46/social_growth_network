<?php

return [

    /*
    | Prefer DB service_categories → product_types → platform_products when
    | hierarchy tables have rows. Config remains seed defaults / dual-read fallback.
    */
    'use_db_hierarchy' => env('CATALOG_USE_DB_HIERARCHY', true),

    'types' => [
        'social_service' => [
            'label' => 'Social Media Services',
            'icon' => 'analytics',
            'default_route' => 'services',
            'short_description' => 'Growth and engagement services for social platforms.',
            'hero_title' => 'Social Media Services',
            'hero_subtitle' => 'Filter Growth or Engagement packages for the outcome you want.',
            'benefits' => [
                'Growth and engagement categories',
                'Clear deliverable descriptions',
                'Protected platform payment',
            ],
            'faq' => [
                ['q' => 'How do I choose Growth vs Engagement?', 'a' => 'Use the category filter: Growth focuses on audience size; Engagement focuses on interaction.'],
            ],
        ],
    ],

    /*
    | User-facing service groups (primary navigation on /services).
    */
    'groups' => [
        'social-media' => [
            'label' => 'Social Media',
            'banner_image' => 'assets/images/Social_Media.jpg',
            'card_image' => 'assets/images/Social_Media.jpg',
            'short_description' => 'Growth and engagement services for social platforms.',
            'hero_title' => 'Social Media',
            'hero_subtitle' => 'Browse social services, then filter by Growth or Engagement.',
            'benefits' => [
                'Instagram, TikTok, YouTube, Twitter/X, and Facebook packs',
                'Clear deliverables before you buy',
                'Secure platform payment with wallet or card',
            ],
            'faq' => [
                ['q' => 'What platforms do you support?', 'a' => 'We offer growth and engagement packs for Instagram, TikTok, YouTube, Twitter/X, and Facebook.'],
            ],
            'types' => ['social_service'],
        ],
    ],

    'divisions' => [
        'social-media' => [
            'label' => 'Social Media',
            'description' => 'Growth and engagement services for social platforms.',
            'types' => ['social_service'],
        ],
    ],
];
