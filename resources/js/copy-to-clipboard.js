/**
 * Copy text to the clipboard. Tries the Clipboard API, then a temporary
 * off-screen textarea + execCommand (Safari/iOS after async work).
 * The textarea is removed immediately; secrets are never left in the DOM.
 *
 * @param {string} text
 * @returns {Promise<boolean>}
 */
export async function copyToClipboard(text) {
    const value = text == null ? '' : String(text);

    if (typeof window !== 'undefined' && window.isSecureContext && navigator.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(value);
            return true;
        } catch {
            // Lost user-gesture (common on iOS after fetch) - use fallback.
        }
    }

    return copyWithExecCommand(value);
}

/**
 * Copy text produced asynchronously (e.g. after fetch) without dropping
 * Safari/iOS's user-activation. Call this *from the click handler* and pass
 * a function that returns the string - do not await fetch yourself first.
 *
 * Uses ClipboardItem + a Promise (Safari's supported pattern), then
 * copyToClipboard if that is unavailable.
 *
 * @param {() => (string|Promise<string>)} getText
 * @returns {Promise<boolean>}
 */
export async function copyFromAsync(getText) {
    const textPromise = Promise.resolve()
        .then(getText)
        .then((value) => (value == null ? '' : String(value)));

    if (
        typeof window !== 'undefined'
        && window.isSecureContext
        && typeof ClipboardItem !== 'undefined'
        && navigator.clipboard?.write
    ) {
        try {
            await navigator.clipboard.write([
                new ClipboardItem({
                    'text/plain': textPromise.then((text) => new Blob([text], { type: 'text/plain' })),
                }),
            ]);
            return true;
        } catch {
            // Fall through: ClipboardItem unsupported or write denied.
        }
    }

    const text = await textPromise;
    return copyToClipboard(text);
}

function copyWithExecCommand(text) {
    if (typeof document === 'undefined' || !document.body) {
        return false;
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.setAttribute('aria-hidden', 'true');
    textarea.tabIndex = -1;
    textarea.style.position = 'fixed';
    textarea.style.top = '0';
    textarea.style.left = '-9999px';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();
    textarea.setSelectionRange(0, textarea.value.length);

    let ok = false;
    try {
        ok = document.execCommand('copy');
    } catch {
        ok = false;
    }

    textarea.remove();

    return ok;
}

export function copyFailedMessage() {
    return 'Unable to copy. Try again, or long-press and copy if your browser blocked it.';
}
