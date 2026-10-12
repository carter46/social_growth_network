@props(['defaultTitle' => 'Dashboard'])

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.site-currency-meta')

    <title>@yield('title', $defaultTitle) | {{ config('app.name') }}</title>
    @include('partials.branding.head-icons')
    @include('partials.branding.social-meta')
    @include('partials.dashboard.theme-boot')
    @include('partials.dashboard.theme-tokens')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
