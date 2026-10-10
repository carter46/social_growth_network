<?php

/**
 * Fixed platform categories — existence source of truth.
 * Public CMS (name, images, etc.) lives in service_categories rows.
 * Do not overwrite DB content from this file.
 *
 * Ownership: Category → Product (platform_products.service_category_id).
 * ProductType remains for legacy/CMS compatibility only.
 *
 * Only categories listed here are offered. Rows for removed platforms stay in
 * the database (inactive, products archived) for order and campaign history.
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
