/**
 * Services marketplace: filter/sort/search without full page reloads.
 */
export function registerServicesMarketplace(Alpine) {
    Alpine.data('servicesMarketplace', (config = {}) => ({
        endpoint: config.endpoint || '/services',
        category: config.category || '',
        sort: config.sort || 'popular',
        budget: config.budget || '',
        q: config.q || '',
        totalVisible: Number(config.totalVisible || 0),
        groups: Array.isArray(config.groups) ? config.groups : [],
        loading: false,
        _abort: null,
        _seq: 0,
        _onPopState: null,

        get categoryLabel() {
            if (!this.category) {
                return 'All Services';
            }
            const match = this.groups.find((g) => g.slug === this.category);

            return match?.label || this.category;
        },

        buildUrl(overrides = {}) {
            const state = {
                category: this.category,
                sort: this.sort,
                budget: this.budget,
                q: this.q,
                page: null,
                ...overrides,
            };
            const params = new URLSearchParams();
            if (state.q) {
                params.set('q', state.q);
            }
            if (state.category) {
                params.set('category', state.category);
            }
            if (state.sort && state.sort !== 'popular') {
                params.set('sort', state.sort);
            }
            if (state.budget) {
                params.set('budget', state.budget);
            }
            if (state.page && Number(state.page) > 1) {
                params.set('page', String(state.page));
            }
            const qs = params.toString();

            return qs ? `${this.endpoint}?${qs}` : this.endpoint;
        },

        syncFromUrl(href) {
            const url = new URL(href, window.location.origin);
            this.category = url.searchParams.get('category') || '';
            this.sort = url.searchParams.get('sort') || 'popular';
            this.budget = url.searchParams.get('budget') || '';
            this.q = url.searchParams.get('q') || '';
        },

        setCategory(slug) {
            this.category = slug || '';
            this.apply();
        },

        setBudget(value) {
            this.budget = value || '';
            this.apply();
        },

        setSort(value) {
            this.sort = value || 'popular';
            this.apply();
        },

        reset() {
            this.category = '';
            this.budget = '';
            this.sort = 'popular';
            this.q = '';
            this.apply(true);
        },

        search(event) {
            if (event?.preventDefault) {
                event.preventDefault();
            }
            const input = event?.target?.querySelector?.('[name="q"]');
            if (input) {
                this.q = input.value.trim();
            }
            this.apply();
        },

        onResultsClick(event) {
            const panel = this.$refs.results;
            if (!panel) {
                return;
            }

            const resetBtn = event.target.closest?.('[data-services-action="reset"]');
            if (resetBtn && panel.contains(resetBtn)) {
                event.preventDefault();
                this.reset();
                return;
            }

            const link = event.target.closest?.('a');
            if (!link || !link.href || !panel.contains(link)) {
                return;
            }
            let url;
            try {
                url = new URL(link.href, window.location.origin);
            } catch {
                return;
            }
            const endpointPath = new URL(this.endpoint, window.location.origin).pathname;
            if (url.origin !== window.location.origin || url.pathname !== endpointPath) {
                return;
            }
            event.preventDefault();
            this.syncFromUrl(url.href);
            this.fetch(url.href, false);
        },

        async apply(replaceHistory = false) {
            await this.fetch(this.buildUrl(), replaceHistory);
        },

        async fetch(href, replaceHistory = false) {
            const panel = this.$refs.results;
            if (!panel) {
                window.location.href = href;
                return;
            }

            const seq = ++this._seq;
            if (this._abort) {
                this._abort.abort();
            }
            this._abort = new AbortController();
            this.loading = true;
            panel.setAttribute('aria-busy', 'true');

            try {
                const res = await fetch(href, {
                    headers: {
                        'X-Services-Filter': '1',
                        Accept: 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    signal: this._abort.signal,
                });
                if (seq !== this._seq) {
                    return;
                }
                if (!res.ok) {
                    window.location.href = href;
                    return;
                }
                const html = await res.text();
                if (seq !== this._seq) {
                    return;
                }
                panel.innerHTML = html;
                if (replaceHistory) {
                    history.replaceState({ servicesFilter: true }, '', href);
                } else {
                    history.pushState({ servicesFilter: true }, '', href);
                }
            } catch (e) {
                if (e?.name === 'AbortError') {
                    return;
                }
                window.location.href = href;
            } finally {
                if (seq === this._seq) {
                    this.loading = false;
                    panel.removeAttribute('aria-busy');
                }
            }
        },

        init() {
            this._onPopState = () => {
                this.syncFromUrl(window.location.href);
                this.fetch(window.location.href, true);
            };
            window.addEventListener('popstate', this._onPopState);
        },

        destroy() {
            if (this._abort) {
                this._abort.abort();
            }
            if (this._onPopState) {
                window.removeEventListener('popstate', this._onPopState);
            }
        },
    }));
}
