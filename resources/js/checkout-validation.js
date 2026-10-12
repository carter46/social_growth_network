import { formatMoney } from './money';

export function formatCheckoutAmount(amount) {
    return formatMoney(amount, { precision: 2 });
}

export function validateRegistrant(registrant = {}) {
    /** @type {Record<string, string>} */
    const errors = {};

    if (!registrant.first_name?.trim()) {
        errors.first_name = 'First name is required.';
    }
    if (!registrant.last_name?.trim()) {
        errors.last_name = 'Last name is required.';
    }

    const email = registrant.email?.trim() ?? '';
    if (!email) {
        errors.email = 'Email is required.';
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        errors.email = 'Enter a valid email address.';
    }

    if (!registrant.phone?.trim()) {
        errors.phone = 'Phone number is required.';
    }

    if (!registrant.address?.trim()) {
        errors.address = 'Street address is required.';
    }
    if (!registrant.city?.trim()) {
        errors.city = 'City is required.';
    }
    if (!registrant.state?.trim()) {
        errors.state = 'State or region is required.';
    }
    if (!registrant.zip?.trim()) {
        errors.zip = 'Postal or ZIP code is required.';
    }

    const country = registrant.country?.trim() ?? '';
    if (country.length !== 2) {
        errors.country = 'Use a 2-letter country code (e.g. NG).';
    }

    return {
        valid: Object.keys(errors).length === 0,
        errors,
    };
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
        registrantErrors: {},
        validateRegistrantFields() {
            if (!this.needsRegistrant) {
                this.registrantErrors = {};
                return true;
            }

            const result = validateRegistrant(this.registrant);
            this.registrantErrors = result.errors;
            return result.valid;
        },
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
            if (this.isDomainProduct || (this.requireDomainChoice && this.domainMode === 'buy')) {
                if (this.domainLabelError) {
                    return this.domainLabelError;
                }
                if (!this.domainQuoteToken || !this.domainAvailable) {
                    return 'Check domain availability before paying.';
                }
                if (!this.validateRegistrantFields()) {
                    return 'Complete all required registrant contact fields.';
                }
            }

            if (this.requireDomainChoice && this.domainMode === 'connect') {
                if (!this.connectScanned || !this.connectFqdn) {
                    return 'Check your existing domain before paying.';
                }
                if (!this.connectAcknowledged) {
                    return 'Confirm that you will point this domain to our nameservers.';
                }
                if (this.connectError) {
                    return this.connectError;
                }
            }

            if (this.paymentMethod === 'wallet' && this.walletShortfall > 0) {
                return `Insufficient wallet balance. Add at least ${this.walletShortfallFormatted} to continue.`;
            }

            return 'Complete all required fields before paying.';
        },
        handleCheckoutSubmit(event) {
            event.preventDefault();
            this.submitError = '';
            this.validateRegistrantFields();

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
