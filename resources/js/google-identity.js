const COOLDOWN_KEY = 'gis_one_tap_cooldown_until';
const SCRIPT_SRC = 'https://accounts.google.com/gsi/client';

let scriptLoading = null;
let initializedClientId = null;
let activeRoot = null;
let oneTapPrompted = false;

function csrfToken(root) {
    if (root?.dataset?.csrf) {
        return root.dataset.csrf;
    }
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

function inCooldown(root) {
    if (root.dataset.disableAfterDismiss !== '1') {
        return false;
    }
    try {
        const until = parseInt(localStorage.getItem(COOLDOWN_KEY) || '0', 10);
        return until > Date.now();
    } catch (e) {
        return false;
    }
}

function setCooldown(root) {
    if (root.dataset.disableAfterDismiss !== '1') {
        return;
    }
    const hours = Math.max(1, parseInt(root.dataset.cooldownHours || '24', 10));
    try {
        localStorage.setItem(COOLDOWN_KEY, String(Date.now() + hours * 3600 * 1000));
    } catch (e) {
        // ignore
    }
}

function showError(root, message) {
    const el = root.querySelector('[data-gis-error]');
    if (!el) {
        return;
    }
    el.textContent = message || 'Google Sign-In failed.';
    el.classList.remove('hidden');
}

async function postCredential(root, credential) {
    const payload = { credential };
    let accountType = (root.dataset.accountType || '').trim();
    if (accountType !== 'creator' && accountType !== 'agent') {
        const host = root.closest('[data-account-type]');
        accountType = (host?.dataset?.accountType || '').trim();
    }
    if (accountType === 'creator' || accountType === 'agent') {
        payload.account_type = accountType;
    }

    const res = await fetch(root.dataset.endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(root),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
    });

    let data = {};
    try {
        data = await res.json();
    } catch (e) {
        // ignore
    }

    if (!res.ok) {
        throw new Error(data.message || 'Google Sign-In failed.');
    }

    if (data.redirect) {
        window.location.href = data.redirect;
        return;
    }

    window.location.reload();
}

function ensureScript() {
    if (window.google?.accounts?.id) {
        return Promise.resolve();
    }
    if (scriptLoading) {
        return scriptLoading;
    }

    scriptLoading = new Promise((resolve, reject) => {
        const existing = document.querySelector(`script[src="${SCRIPT_SRC}"]`);
        if (existing) {
            let tries = 0;
            const timer = setInterval(() => {
                tries += 1;
                if (window.google?.accounts?.id) {
                    clearInterval(timer);
                    resolve();
                } else if (tries > 50) {
                    clearInterval(timer);
                    reject(new Error('Google Identity script failed to load.'));
                }
            }, 100);
            return;
        }

        const script = document.createElement('script');
        script.src = SCRIPT_SRC;
        script.async = true;
        script.defer = true;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Google Identity script failed to load.'));
        document.head.appendChild(script);
    });

    return scriptLoading;
}

function ensureInitialized(clientId, autoSelect) {
    if (!window.google?.accounts?.id) {
        return;
    }

    if (initializedClientId === clientId) {
        return;
    }

    initializedClientId = clientId;
    oneTapPrompted = false;

    google.accounts.id.initialize({
        client_id: clientId,
        callback: (response) => {
            const root = activeRoot || document.querySelector('[data-google-identity]');
            if (!root) {
                return;
            }
            if (!response?.credential) {
                showError(root, 'Google did not return a credential.');
                return;
            }
            postCredential(root, response.credential).catch((err) => {
                showError(root, err.message || 'Google Sign-In failed.');
            });
        },
        auto_select: !!autoSelect,
        cancel_on_tap_outside: true,
    });
}

function bindRoot(root) {
    root.addEventListener('pointerdown', () => {
        activeRoot = root;
    });
}

function initRoot(root) {
    if (root.dataset.gisReady === '1') {
        return;
    }
    if (!window.google?.accounts?.id) {
        return;
    }

    const clientId = root.dataset.clientId;
    if (!clientId) {
        return;
    }

    ensureInitialized(clientId, root.dataset.autoSelect === '1');
    bindRoot(root);
    root.dataset.gisReady = '1';

    if (root.dataset.showButton === '1') {
        const btn = root.querySelector('[data-gis-button]');
        if (btn && !btn.dataset.gisRendered) {
            btn.dataset.gisRendered = '1';
            google.accounts.id.renderButton(btn, {
                theme: 'outline',
                size: 'large',
                shape: 'rectangular',
                text: root.dataset.buttonText || 'continue_with',
                width: Math.min(btn.parentElement ? btn.parentElement.clientWidth : 320, 400),
            });
        }
    }

    if (root.dataset.showOneTap === '1' && !oneTapPrompted && !inCooldown(root)) {
        oneTapPrompted = true;
        activeRoot = root;
        google.accounts.id.prompt((notification) => {
            if (!notification) {
                return;
            }
            if (notification.isSkippedMoment && notification.isSkippedMoment()) {
                setCooldown(root);
            }
            if (notification.isDismissedMoment && notification.isDismissedMoment()) {
                setCooldown(root);
            }
        });
    }
}

export function bootGoogleIdentity(scope = document) {
    const roots = scope.querySelectorAll
        ? scope.querySelectorAll('[data-google-identity]')
        : document.querySelectorAll('[data-google-identity]');

    if (!roots.length) {
        return;
    }

    ensureScript()
        .then(() => {
            roots.forEach((root) => initRoot(root));
        })
        .catch(() => {
            // Silent — guest pages without GIS configured should not throw.
        });
}

export function initGoogleIdentity() {
    bootGoogleIdentity(document);

    window.addEventListener('dashboard-tab-navigated', () => {
        // Fresh panel HTML needs re-init; clear ready flags inside new nodes only.
        document.querySelectorAll('[data-google-identity]:not([data-gis-ready="1"])').forEach(() => {});
        bootGoogleIdentity(document);
    });
}
