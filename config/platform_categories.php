<?php

/**
 * Fixed platform categories — existence source of truth.
 * Public CMS (name, images, etc.) lives in service_categories rows.
 * Do not overwrite DB content from this file.
 *
 * Ownership: Category → Product (platform_products.service_category_id).
 * ProductType remains for legacy/CMS compatibility only.
 */
return [
    'youtube' => [
        'slug' => 'youtube',
        'expected_id' => 10,
        'label' => 'YouTube',
        'products' => [
            'youtube-views',
            'youtube-likes',
            'youtube-comments',
            'youtube-watch-hours',
            'youtube-subscribers',
        ],
    ],
    'facebook' => [
        'slug' => 'facebook',
        'expected_id' => 11,
        'label' => 'Facebook',
        'products' => [
            'facebook-views',
            'facebook-likes',
            'facebook-comments',
        ],
    ],
    'instagram' => [
        'slug' => 'instagram',
        'expected_id' => 12,
        'label' => 'Instagram',
        'products' => [
            'instagram-views',
            'instagram-likes',
            'instagram-comments',
        ],
    ],
    'tiktok' => [
        'slug' => 'tiktok',
        'expected_id' => 13,
        'label' => 'TikTok',
        'products' => [
            'tiktok-views',
            'tiktok-likes',
            'tiktok-comments',
        ],
    ],
    'twitter' => [
        'slug' => 'twitter',
        'expected_id' => 14,
        'label' => 'Twitter',
        'products' => [
            'twitter-views',
            'twitter-likes',
            'twitter-comments',
        ],
    ],

    /**
     * Legacy umbrella category — kept for existing CMS/links; products are
     * owned by the platform categories above after flatten.
     */
    'social' => [
        'slug' => 'social-media',
        'expected_id' => 3,
        'label' => 'Social Media',
        'products' => [],
    ],
];
