<?php

/**
 * Canonical platform product slugs per service type.
 * Products outside this list are archived (never deleted) by PlatformCatalogTrim.
 */
return [
    'retired_services' => [],

    'social_service' => [
        'youtube-views',
        'youtube-likes',
        'youtube-comments',
        'youtube-watch-hours',
        'youtube-subscribers',
        'youtube-subscribers-watch-comments',
    ],

    /**
     * Tasks each product includes, in the default order. Admins may reorder them
     * (platform_products.task_order) but never add or remove one.
     *
     * @var array<string, list<string>>
     */
    'tasks' => [
        'youtube-views' => ['watch'],
        'youtube-likes' => ['like'],
        'youtube-watch-hours' => ['watch'],
        'youtube-subscribers' => ['subscribe', 'watch'],
        'youtube-comments' => ['watch', 'comment'],
        'youtube-subscribers-watch-comments' => ['subscribe', 'watch', 'comment'],
    ],

    /**
     * Explicit slug → engagement metric, checked before the slug-suffix guess
     * (youtube-subscribers-watch-comments must not resolve to Comments).
     *
     * @var array<string, string>
     */
    'metrics' => [
        'youtube-views' => 'views',
        'youtube-likes' => 'likes',
        'youtube-watch-hours' => 'watch_hours',
        'youtube-subscribers' => 'subscribers',
        'youtube-comments' => 'comments',
        'youtube-subscribers-watch-comments' => 'subscribers',
    ],

    /** Products that must have a "Time to complete" before they can be sold. */
    'completion_time_required' => [
        'youtube-watch-hours',
        'youtube-subscribers',
        'youtube-comments',
        'youtube-subscribers-watch-comments',
    ],

    /**
     * Old Views product slugs → new slugs (bookmarks / shared links).
     *
     * @var array<string, string>
     */
    'slug_redirects' => [
        'youtube-views-lite' => 'youtube-views',
    ],
];
