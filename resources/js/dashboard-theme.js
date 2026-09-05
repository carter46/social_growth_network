/**
 * Dashboard theme client: Light / Dark / System with no-reload persistence.
 */
const STORAGE_KEY = 'taskpulse.dashboard.theme';
const LEGACY_STORAGE_KEY = '7th.dashboard.theme';

function systemTheme() {
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function readCache() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY) || localStorage.getItem(LEGACY_STORAGE_KEY) || 'null';
        return JSON.parse(raw);
    } catch (e) {
        return null;
    }
}

function writeCache(preference, resolved) {
    try {
        const payload = JSON.stringify({ preference, resolved, at: Date.now() });
        localStorage.setItem(STORAGE_KEY, payload);
        // Keep legacy key in sync so older boots still see the preference briefly
        localStorage.setItem(LEGACY_STORAGE_KEY, payload);
    } catch (e) {
        // ignore quota / private mode
    }
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function pickThemeExtras(resolved, extras = {}) {
    const boot = window.__dashboardTheme || {};
    const byTheme = extras.themes || boot.themes || null;

    if (byTheme && byTheme[resolved]) {
        return {
            charts: byTheme[resolved].charts || {},
            assets: byTheme[resolved].assets || {},
        };
    }

    return {
        charts: extras.charts || boot.charts || {},
        assets: extras.assets || boot.assets || {},
    };
}

function applyTheme(preference, resolved, extras = {}) {
    const html = document.documentElement;
    html.setAttribute('data-theme-preference', preference);
    html.setAttribute('data-theme', resolved);
    writeCache(preference, resolved);

    const picked = pickThemeExtras(resolved, extras);
    const themes = extras.themes || window.__dashboardTheme?.themes || null;

    window.__dashboardTheme = {
        ...(window.__dashboardTheme || {}),
        preference,
        resolved,
        charts: picked.charts,
        assets: picked.assets,
        themes,
        endpoint: window.__dashboardTheme?.endpoint,
    };

    window.dispatchEvent(
        new CustomEvent('dashboard-theme-changed', {
            detail: {
                preference,
                resolved,
                charts: window.__dashboardTheme.charts,
                assets: window.__dashboardTheme.assets,
            },
        }),
    );

    // Optional chart adapters: window.__dashboardCharts = [{ themeChanged(detail) {} }]
    (window.__dashboardCharts || []).forEach((chart) => {
        try {
            chart?.themeChanged?.(window.__dashboardTheme);
        } catch (e) {
            // ignore adapter errors
        }
    });
}

function resolvePreference(preference) {
    return preference === 'system' ? systemTheme() : preference;
}

let mediaQuery = null;
let mediaHandler = null;

function bindSystemListener(enabled) {
    if (!window.matchMedia) return;
    if (!mediaQuery) {
        mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    }
    if (mediaHandler) {
        mediaQuery.removeEventListener?.('change', mediaHandler);
        mediaQuery.removeListener?.(mediaHandler);
        mediaHandler = null;
    }
    if (!enabled) return;

    mediaHandler = () => {
        const preference = document.documentElement.getAttribute('data-theme-preference') || 'light';
        if (preference !== 'system') return;
        applyTheme('system', systemTheme());
    };

    if (mediaQuery.addEventListener) {
        mediaQuery.addEventListener('change', mediaHandler);
    } else if (mediaQuery.addListener) {
        mediaQuery.addListener(mediaHandler);
    }
}

export async function setDashboardThemePreference(preference, { persist = true } = {}) {
    const previous = {
        preference: document.documentElement.getAttribute('data-theme-preference') || 'light',
        resolved: document.documentElement.getAttribute('data-theme') || 'light',
    };

    const resolved = resolvePreference(preference);
    applyTheme(preference, resolved);
    bindSystemListener(preference === 'system');

    if (!persist) return { ok: true, preference, resolved };

    const endpoint = window.__dashboardTheme?.endpoint;
    if (!endpoint) return { ok: true, preference, resolved };

    try {
        const res = await fetch(endpoint, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                theme: preference,
                // Hint only — never authoritative for system paint; client still resolves via matchMedia.
                system_theme: preference === 'system' ? systemTheme() : undefined,
            }),
            credentials: 'same-origin',
        });

        if (!res.ok) throw new Error('Theme save failed');
        const data = await res.json();
        const nextPreference = data.preference || preference;
        // Critical: never trust server `resolved` when preference is system (server may force light).
        const nextResolved =
            nextPreference === 'system' ? systemTheme() : data.resolved || resolved;

        applyTheme(nextPreference, nextResolved, {
            themes: data.themes,
            charts: data.charts,
            assets: data.assets,
        });
        bindSystemListener(nextPreference === 'system');
        return { ok: true, ...data, resolved: nextResolved };
    } catch (e) {
        applyTheme(previous.preference, previous.resolved);
        bindSystemListener(previous.preference === 'system');
        window.dispatchEvent(
            new CustomEvent('toast', {
                detail: { type: 'error', message: 'Could not save theme preference. Please try again.' },
            }),
        );
        return { ok: false, error: e };
    }
}

export function initDashboardThemeClient() {
    const boot = window.__dashboardTheme;
    if (!boot) return;

    // Re-resolve system on the client in case SSR used light fallback.
    if (boot.preference === 'system') {
        const resolved = systemTheme();
        applyTheme('system', resolved, { themes: boot.themes, charts: boot.charts, assets: boot.assets });
        bindSystemListener(true);
    } else {
        bindSystemListener(false);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.body?.classList.contains('dashboard-shell')) {
        initDashboardThemeClient();
    }
});

window.DashboardTheme = {
    setPreference: setDashboardThemePreference,
    systemTheme,
    readCache,
};
