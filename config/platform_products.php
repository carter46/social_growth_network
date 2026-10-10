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
