<?php

/**
 * Canonical platform product slugs per service type.
 * Only social_service products remain for Social Growth Network.
 */
return [
    'retired_services' => [],

    'social_service' => [
        'youtube-views',
        'youtube-likes',
        'youtube-comments',
        'youtube-watch-hours',
        'youtube-subscribers',
        'facebook-views',
        'facebook-likes',
        'facebook-comments',
        'instagram-views',
        'instagram-likes',
        'instagram-comments',
        'tiktok-views',
        'tiktok-likes',
        'tiktok-comments',
        'twitter-views',
        'twitter-likes',
        'twitter-comments',
    ],

    /**
     * Old Views product slugs → new slugs (bookmarks / shared links).
     *
     * @var array<string, string>
     */
    'slug_redirects' => [
        'youtube-views-lite' => 'youtube-views',
        'facebook-growth-pack' => 'facebook-views',
        'instagram-growth-pack' => 'instagram-views',
        'tiktok-engagement-boost' => 'tiktok-views',
        'twitter-audience-pack' => 'twitter-views',
    ],
];
