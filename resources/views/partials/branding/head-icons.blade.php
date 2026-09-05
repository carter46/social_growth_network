@php
    $icons = app(\App\Services\Branding\PwaBrandingSync::class)->headIconUrls();
    $themeColor = config('pwa.manifest.theme_color', '#004AC6');
@endphp
<meta name="theme-color" content="{{ $themeColor }}">
{{-- Prefer branding media (via headIconUrls) so git-restored letter-7 public files cannot win the tab icon.
     Do not advertise favicon.ico — browsers request /favicon.ico by convention; sync keeps that file updated. --}}
<link rel="icon" href="{{ $icons['favicon'] }}" type="image/png" sizes="32x32">
<link rel="icon" href="{{ $icons['favicon16'] }}" type="image/png" sizes="16x16">
<link rel="apple-touch-icon" sizes="180x180" href="{{ $icons['apple'] }}">
<link rel="manifest" href="{{ $icons['manifest'] }}">
