/**
 * Site currency formatting. The currency comes from <meta name="site-currency"> (set by the layouts)
 * and defaults to USD.
 */
function siteCurrency() {
    if (typeof document !== 'undefined') {
        const meta = document.querySelector('meta[name="site-currency"]');
        if (meta) {
            return {
                code: meta.getAttribute('content') || 'USD',
                symbol: meta.dataset.symbol || '$',
                locale: meta.dataset.locale || 'en-US',
            };
        }
    }

    return { code: 'USD', symbol: '$', locale: 'en-US' };
}

/**
 * formatMoney(12.5) → "$12.50"; formatMoney(0.0025) → "$0.0025".
 * At cent precision a sub-cent part is shown up to 4 decimals (the ledger scale).
 */
export function formatMoney(amount, { precision = 2, max = null } = {}) {
    const currency = siteCurrency();
    const n = Number(amount);
    const value = Number.isFinite(n) ? n : 0;
    const maximumFractionDigits = Math.max(precision, max ?? (precision === 2 ? 4 : precision));
    const formatted = Math.abs(value).toLocaleString(currency.locale, {
        minimumFractionDigits: precision,
        maximumFractionDigits,
    });

    return (value < 0 ? '-' : '') + currency.symbol + formatted;
}

export function formatNumber(amount, { precision = 0, max = null } = {}) {
    const n = Number(amount);
    const value = Number.isFinite(n) ? n : 0;

    return value.toLocaleString(siteCurrency().locale, {
        minimumFractionDigits: precision,
        maximumFractionDigits: Math.max(precision, max ?? precision),
    });
}

export function currencySymbol() {
    return siteCurrency().symbol;
}

if (typeof window !== 'undefined') {
    window.formatMoney = formatMoney;
}
