<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Would you like the install button to appear on all pages?
      Set true/false
    |--------------------------------------------------------------------------
    */

    'install-button' => false,

    /*
    |--------------------------------------------------------------------------
    | PWA Manifest Configuration
    |--------------------------------------------------------------------------
    |  php artisan erag:update-manifest
    */

    'manifest' => [
        'name' => 'Social Growth Network',
        'short_name' => 'Social Growth',
        'background_color' => '#F8F9FF',
        'display' => 'standalone',
        'description' => 'Social media growth and engagement services with secure payment.',
        'theme_color' => '#004AC6',
        'start_url' => '/',
        'scope' => '/',
        'icons' => [
            [
                'src' => '/icons/icon-512x512.png',
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src' => '/icons/icon-192x192.png',
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src' => '/icons/icon-512x512-maskable.png',
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'maskable',
            ],
            [
                'src' => '/icons/icon-192x192-maskable.png',
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'maskable',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Configuration
    |--------------------------------------------------------------------------
    | Toggles the application's debug mode based on the environment variable
    */

    'debug' => env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Livewire Integration
    |--------------------------------------------------------------------------
    | Set to true if you're using Livewire in your application to enable
    | Livewire-specific PWA optimizations or features.
    */

    'livewire-app' => false,

    /*
    |--------------------------------------------------------------------------
    | Static icon fallbacks (when admin media is not on disk yet)
    |--------------------------------------------------------------------------
    | Used by branding:sync-pwa before the green placeholder is written.
    */
    /*
    | Legacy seed paths — no longer used by PwaBrandingSync (letter-7 / static
    | assets must not silently replace Admin branding media).
    */
    'default_icon_paths' => [],
];
