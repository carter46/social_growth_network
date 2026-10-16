import { formatMoney } from './money';

export function formatCheckoutAmount(amount) {
    return formatMoney(amount, { precision: 2 });
}

/**
 * Merge Alpine helper objects while preserving getters.
 * Object spread ({...helpers}) evaluates getters once and can throw
 * before the component state exists, which breaks the entire x-data component.
 */
export function assignAlpineHelpers(target, ...sources) {
    for (const source of sources) {
        Object.defineProperties(target, Object.getOwnPropertyDescriptors(source));
    }

    return target;
}

export function validateWalletPayment(total, balance) {
    const orderTotal = Number(total) || 0;
    const walletBalance = Number(balance) || 0;

    if (orderTotal <= 0) {
        return { valid: false, message: 'Order total is invalid.' };
    }

    if (walletBalance < orderTotal) {
        return {
            valid: false,
            message: `Insufficient wallet balance. You need ${formatCheckoutAmount(orderTotal)} but only have ${formatCheckoutAmount(walletBalance)} available.`,
        };
    }

    return { valid: true, message: null };
}

export function createCheckoutValidationHelpers() {
    return {
        submitError: '',
        get walletShortfall() {
            if (this.paymentMethod !== 'wallet') {
                return 0;
            }

            return Math.max(0, (Number(this.total) || 0) - (Number(this.walletBalance) || 0));
        },
        get walletShortfallFormatted() {
            return formatCheckoutAmount(this.walletShortfall);
        },
        buildSubmitBlockedMessage() {
            if (this.paymentMethod === 'wallet' && this.walletShortfall > 0) {
                return `Insufficient wallet balance. Add at least ${this.walletShortfallFormatted} to continue.`;
            }

            return 'Complete all required fields before paying.';
        },
        handleCheckoutSubmit(event) {
            event.preventDefault();
            this.submitError = '';

            if (!this.canSubmit || this.walletShortfall > 0) {
                this.submitError = this.buildSubmitBlockedMessage();
                if (typeof window.hideDashboardPageLoader === 'function') {
                    window.hideDashboardPageLoader();
                }
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { type: 'danger', message: this.submitError },
                }));
                return;
            }

            if (this.paymentMethod === 'wallet') {
                const walletCheck = validateWalletPayment(this.total, this.walletBalance);
                if (!walletCheck.valid) {
                    this.submitError = walletCheck.message;
                    if (typeof window.hideDashboardPageLoader === 'function') {
                        window.hideDashboardPageLoader();
                    }
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { type: 'danger', message: this.submitError },
                    }));
                    return;
                }
            }

            if (typeof window.showDashboardPageLoader === 'function') {
                window.showDashboardPageLoader('checkout-submit');
            }

            event.target.submit();
        },
    };
}
