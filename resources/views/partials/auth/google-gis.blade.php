{{--
  Google Identity Services partial (markup only - boot lives in resources/js/google-identity.js).
  @props:
    mode: button|one_tap|both
    surface: home|login|register|link
    buttonText: optional label hint for login vs signup
    endpoint: route to POST credential (default auth.google)
--}}
@props([
    'mode' => 'button',
    'surface' => 'login',
    'buttonText' => 'continue_with',
    'endpoint' => null,
])

@php
    $gis = $googleIdentityConfig ?? \App\Services\Auth\Identity\GoogleIdentityProvider::configForFrontend();
    $endpoint = $endpoint ?: route('auth.google');
    $showButton = ($mode === 'button' || $mode === 'both') && ($gis['enabled'] ?? false);
    $showOneTap = ($mode === 'one_tap' || $mode === 'both')
        && ($gis['one_tap_enabled'] ?? false)
        && match ($surface) {
            'home' => (bool) ($gis['one_tap_show_home'] ?? true),
            'login' => (bool) ($gis['one_tap_show_login'] ?? false),
            'register' => (bool) ($gis['one_tap_show_register'] ?? false),
            default => false,
        };
@endphp

@if (($showButton || $showOneTap) && ! empty($gis['client_id']))
    <div
        data-google-identity
        data-client-id="{{ $gis['client_id'] }}"
        data-endpoint="{{ $endpoint }}"
        data-csrf="{{ csrf_token() }}"
        data-mode="{{ $mode }}"
        data-surface="{{ $surface }}"
        data-show-button="{{ $showButton ? '1' : '0' }}"
        data-show-one-tap="{{ $showOneTap ? '1' : '0' }}"
        data-auto-select="{{ ! empty($gis['auto_select_enabled']) ? '1' : '0' }}"
        data-disable-after-dismiss="{{ ! empty($gis['one_tap_disable_after_dismiss']) ? '1' : '0' }}"
        data-cooldown-hours="{{ (int) ($gis['one_tap_prompt_cooldown_hours'] ?? 24) }}"
        data-button-text="{{ $buttonText }}"
        class="w-full"
    >
        @if ($showButton)
            @if ($surface !== 'link')
                <div class="my-5 flex items-center gap-3 text-xs text-text-secondary">
                    <span class="h-px flex-1 bg-border-default"></span>
                    <span>or</span>
                    <span class="h-px flex-1 bg-border-default"></span>
                </div>
            @endif
            <div data-gis-button class="flex justify-center"></div>
            @if ($surface !== 'link')
                <p class="mt-2 text-center text-xs text-text-secondary">By continuing with Google you agree to our Terms.</p>
            @endif
        @endif
        @error('google')
            <p class="mt-2 text-sm text-danger text-center">{{ $message }}</p>
        @enderror
        <p data-gis-error class="mt-2 hidden text-sm text-danger text-center"></p>
    </div>
@endif
