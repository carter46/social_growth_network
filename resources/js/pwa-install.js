/**
 * PWA install flow — beforeinstallprompt, iOS add-to-home-screen modal, button states.
 */
let deferredPrompt = null;
let state = 'idle';

function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
}

function isIOS() {
    return /iphone|ipad|ipod/i.test(window.navigator.userAgent)
        && !window.MSStream;
}

function shouldShowNavInstall() {
    return state === 'installing' || !!deferredPrompt || isIOS();
}

function updateNavInstallButton(btn, mode) {
    if (mode !== 'menu' && mode !== 'footer') {
        return;
    }

    if (state === 'standalone' || state === 'installed') {
        btn.classList.add('hidden');

        return;
    }

    if (shouldShowNavInstall()) {
        btn.classList.remove('hidden');
    } else {
        btn.classList.add('hidden');
    }
}

function getButtons() {
    return Array.from(document.querySelectorAll('[data-pwa-install]'));
}

function getModal() {
    return document.getElementById('pwa-install-modal');
}

function openModal(mode) {
    const modal = getModal();
    if (!modal) {
        return;
    }

    const iosPanel = modal.querySelector('[data-pwa-panel="ios"]');
    const desktopPanel = modal.querySelector('[data-pwa-panel="desktop"]');
    const mobilePanel = modal.querySelector('[data-pwa-panel="mobile"]');

    [iosPanel, desktopPanel, mobilePanel].forEach((panel) => panel?.classList.add('hidden'));

    if (isIOS() && iosPanel) {
        iosPanel.classList.remove('hidden');
    } else if (mode === 'desktop' && desktopPanel) {
        desktopPanel.classList.remove('hidden');
    } else if (mobilePanel) {
        mobilePanel.classList.remove('hidden');
    } else if (desktopPanel) {
        desktopPanel.classList.remove('hidden');
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    const modal = getModal();
    if (!modal) {
        return;
    }

    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = '';
}

function setState(next) {
    state = next;

    getButtons().forEach((btn) => {
        const mode = btn.getAttribute('data-pwa-install');
        const labelEl = btn.querySelector('[data-pwa-label]') || btn;
        const subEl = btn.querySelector('[data-pwa-sub]');

        if (state === 'standalone' || state === 'installed') {
            if (mode === 'menu' || mode === 'footer') {
                btn.classList.add('hidden');

                return;
            }

            btn.disabled = true;
            btn.classList.remove('opacity-50');
            if (labelEl) {
                labelEl.textContent = 'App installed';
            }
            if (subEl) {
                subEl.textContent = 'Installed on your device';
            }

            return;
        }

        if (mode === 'menu' || mode === 'footer') {
            updateNavInstallButton(btn, mode);
        } else {
            btn.classList.remove('hidden');
        }

        btn.disabled = state === 'installing';

        if (state === 'installing') {
            if (labelEl) {
                labelEl.textContent = 'Installing…';
            }
            if (subEl) {
                subEl.textContent = '';
            }
            btn.classList.add('opacity-70');

            return;
        }

        btn.classList.remove('opacity-70');

        if (mode === 'mobile') {
            if (labelEl) {
                labelEl.textContent = 'Download Mobile App';
            }
            if (subEl) {
                subEl.textContent = 'Install to your home screen';
            }
        } else if (mode === 'desktop') {
            if (labelEl) {
                labelEl.textContent = 'Download Desktop App';
            }
            if (subEl) {
                subEl.textContent = 'Install to your computer';
            }
        } else if (mode === 'menu' || mode === 'footer') {
            if (labelEl) {
                labelEl.textContent = 'Install app';
            }
        }
    });
}

function showInstallUI() {
    if (state === 'standalone' || state === 'installed') {
        return;
    }

    getButtons().forEach((btn) => {
        updateNavInstallButton(btn, btn.getAttribute('data-pwa-install'));
    });
}

function triggerInstall(mode) {
    if (state === 'installed' || state === 'standalone') {
        return;
    }

    if (isIOS() || (!deferredPrompt && (mode === 'mobile' || mode === 'menu' || mode === 'footer'))) {
        openModal(isIOS() ? 'ios' : mode);

        return;
    }

    if (!deferredPrompt) {
        openModal(mode);

        return;
    }

    setState('installing');
    deferredPrompt.prompt();
    deferredPrompt.userChoice
        .then((choice) => {
            deferredPrompt = null;
            setState(choice.outcome === 'accepted' ? 'installed' : 'idle');
        })
        .catch(() => {
            setState('idle');
        });
}

function initPwaInstall() {
    if (!getModal() && getButtons().length === 0) {
        return;
    }

    if (isStandalone()) {
        setState('standalone');
        getButtons().forEach((btn) => btn.classList.add('hidden'));

        return;
    }

    if (window.localStorage?.getItem('pwa-installed') === '1') {
        if (isStandalone()) {
            setState('installed');
        } else {
            // Stale flag after uninstall — trust the browser, not storage alone.
            window.localStorage?.removeItem('pwa-installed');
            setState('idle');
        }
    } else {
        setState('idle');
    }

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredPrompt = event;
        window.localStorage?.removeItem('pwa-installed');
        setState('idle');
        showInstallUI();
    });

    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        window.localStorage?.setItem('pwa-installed', '1');
        setState('installed');
        closeModal();
    });

    getButtons().forEach((btn) => {
        btn.addEventListener('click', () => {
            triggerInstall(btn.getAttribute('data-pwa-install') || 'desktop');
        });
    });

    document.getElementById('pwa-install-modal-close')?.addEventListener('click', closeModal);

    const modal = getModal();
    modal?.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeModal();
        }
    });

    if (isIOS()) {
        getButtons().forEach((btn) => {
            updateNavInstallButton(btn, btn.getAttribute('data-pwa-install'));
        });
    }
}

document.addEventListener('DOMContentLoaded', initPwaInstall);

window.PwaInstall = { trigger: triggerInstall, getState: () => state };
