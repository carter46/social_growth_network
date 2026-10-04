export function sanitizeDomainLabel(raw, allowedTlds = new Set()) {
    let value = String(raw ?? '').toLowerCase();
    let error = null;
    let autoTld = null;

    if (/\s/u.test(value)) {
        error = 'Spaces are not allowed. Enter only the domain name.';
        value = value.replace(/\s+/gu, '');
    }

    if (value.includes('.')) {
        const [left, right = ''] = value.split('.', 2);
        value = left.replace(/[^a-z0-9-]/g, '');
        const maybeTld = right.split('.')[0]?.replace(/[^a-z0-9-]/g, '') ?? '';
        if (maybeTld) {
            autoTld = maybeTld;
        }
        error = error ?? 'Do not include an extension here. Choose it from the dropdown.';
    } else {
        const sanitized = value.replace(/[^a-z0-9-]/g, '');
        if (sanitized !== value) {
            error = error ?? 'Use letters, numbers, and hyphens only.';
            value = sanitized;
        }
    }

    if (value.length > 63) {
        value = value.slice(0, 63);
        error = error ?? 'Domain name cannot exceed 63 characters.';
    }

    if (value && (value.startsWith('-') || value.endsWith('-'))) {
        error = error ?? 'Domain name cannot start or end with a hyphen.';
    } else if (value && !/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/.test(value)) {
        error = error ?? 'Domain name contains invalid characters.';
    }

    if (autoTld && allowedTlds.has(autoTld)) {
        return { value, error, autoTld };
    }

    return { value, error, autoTld: null };
}

export function extractQuoteError(data) {
    if (data?.message) {
        return data.message;
    }

    if (data?.errors) {
        const flat = Object.values(data.errors).flat();
        if (flat[0]) {
            return flat[0];
        }
    }

    return 'Unable to check domain.';
}

export function formatDomainNgn(amount) {
    return new Intl.NumberFormat('en-NG', { maximumFractionDigits: 0 }).format(Number(amount) || 0);
}

/**
 * Merge Alpine helper objects while preserving getters.
 * Object spread ({...helpers}) evaluates getters once and can throw
 * (e.g. this.domainLabel.trim() before domainLabel exists), which breaks
 * the entire x-data component - blank buttons, dead x-if panels, etc.
 */
export function assignAlpineHelpers(target, ...sources) {
    for (const source of sources) {
        Object.defineProperties(target, Object.getOwnPropertyDescriptors(source));
    }

    return target;
}

export function createDomainSearchHelpers() {
    return {
        domainLabelError: '',
        domainSuggestions: [],
        selectedSuggestionTld: null,
        allowedTldSet() {
            return new Set([
                ...(this.domainTlds ?? []).map((row) => row.tld),
                ...(this.domainTldsAdvanced ?? []).map((row) => row.tld),
            ]);
        },
        onDomainLabelInput(event) {
            const raw = event?.target?.value ?? this.domainLabel ?? '';
            const { value, error, autoTld } = sanitizeDomainLabel(raw, this.allowedTldSet());
            this.domainLabel = value;
            this.domainLabelError = error ?? '';
            if (autoTld) {
                this.domainTld = autoTld;
            }
            if (event?.target && event.target.value !== value) {
                event.target.value = value;
            }
            this.invalidateQuote();
        },
        get canCheckDomain() {
            return Boolean(this.domainLabel?.trim()) && !this.domainLabelError;
        },
        get alternativeDomainSuggestions() {
            const selected = this.domainTld;
            return (this.domainSuggestions ?? []).filter((row) => row.tld !== selected);
        },
        formatSuggestionPrice(amount) {
            return formatDomainNgn(amount);
        },
        applyQuoteResponse(data, label) {
            this.domainFqdn = data.fqdn || '';
            this.domainPremium = Boolean(data.premium);
            const suggestions = Array.isArray(data.suggestions) ? [...data.suggestions] : [];

            if (data.available && data.quote_token) {
                this.domainAvailable = true;
                this.domainQuoteToken = data.quote_token;
                this.domainRetailPrice = Number(data.retail_price || 0);
                this.domainMessage = `${data.fqdn} is available.`;
                this.selectedSuggestionTld = this.domainTld;
                this.domainSuggestions = suggestions;
                return;
            }

            this.invalidateQuote(false);
            this.domainMessage = data.message || `${data.fqdn || label} is not available.`;
            this.domainSuggestions = suggestions;
        },
        selectSuggestion(row) {
            if (!row?.quote_token) {
                return;
            }

            this.domainTld = row.tld;
            this.domainFqdn = row.fqdn;
            this.domainRetailPrice = Number(row.retail_price || 0);
            this.domainPremium = Boolean(row.premium);
            this.domainQuoteToken = row.quote_token;
            this.domainAvailable = true;
            this.domainMessage = `${row.fqdn} is available.`;
            this.selectedSuggestionTld = row.tld;
        },
        invalidateQuote(clearSuggestions = true) {
            this.domainQuoteToken = '';
            this.domainFqdn = '';
            this.domainRetailPrice = 0;
            this.domainPremium = false;
            this.domainAvailable = false;
            this.domainMessage = '';
            this.selectedSuggestionTld = null;
            if (clearSuggestions) {
                this.domainSuggestions = [];
            }
        },
    };
}
