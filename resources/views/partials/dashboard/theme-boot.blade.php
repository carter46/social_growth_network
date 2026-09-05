@php
    $preference = $dashboardThemePreference ?? 'light';
    $resolved = $dashboardThemeResolved ?? 'light';
    $payload = $dashboardThemePayload ?? [];
@endphp
@php
    $bootNonce = '';
    if (function_exists('csp_nonce')) {
        $bootNonce = (string) csp_nonce();
    } elseif (class_exists(\Illuminate\Support\Facades\Vite::class)) {
        try {
            $bootNonce = (string) (\Illuminate\Support\Facades\Vite::cspNonce() ?? '');
        } catch (\Throwable $e) {
            $bootNonce = '';
        }
    }
@endphp
<script @if ($bootNonce !== '') nonce="{{ $bootNonce }}" @endif>
(function () {
    try {
        var STORAGE_KEY = 'taskpulse.dashboard.theme';
        var LEGACY_STORAGE_KEY = '7th.dashboard.theme';
        var preference = @json($preference);
        var serverResolved = @json($resolved);
        var payload = @json($payload);

        function systemTheme() {
            return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        // Prefer new key; fall back to legacy so existing preferences are preserved.
        try {
            if (!localStorage.getItem(STORAGE_KEY) && localStorage.getItem(LEGACY_STORAGE_KEY)) {
                localStorage.setItem(STORAGE_KEY, localStorage.getItem(LEGACY_STORAGE_KEY));
            }
        } catch (e) {}

        // DB preference is authoritative for authenticated dashboards (ignore stale localStorage preference).
        preference = preference || 'light';
        var resolved = preference === 'system' ? systemTheme() : preference;
        if (!resolved) resolved = serverResolved || 'light';

        var themes = (payload && payload.themes) || null;
        var charts = {};
        var assets = {};
        if (themes && themes[resolved]) {
            charts = themes[resolved].charts || {};
            assets = themes[resolved].assets || {};
        } else {
            charts = (payload && payload.charts) || {};
            assets = (payload && payload.assets) || {};
        }

        document.documentElement.setAttribute('data-theme', resolved);
        document.documentElement.setAttribute('data-theme-preference', preference);

        window.__dashboardTheme = {
            preference: preference,
            resolved: resolved,
            charts: charts,
            assets: assets,
            themes: themes,
            endpoint: @json(route('theme.preference')),
        };

        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({
                preference: preference,
                resolved: resolved,
                at: Date.now(),
            }));
        } catch (e) {}
    } catch (e) {
        document.documentElement.setAttribute('data-theme', 'light');
        document.documentElement.setAttribute('data-theme-preference', 'light');
    }
})();
</script>
