<div
    x-data="{
        userId: null,
        userLabel: '',
        action: '',
        loading: false,
        submitting: false,
        error: '',
        categories: [],
        services: [],
        products: [],
        categoryId: '',
        serviceId: '',
        productSlug: '',
        variantId: '',
        units: '',
        qty: {},
        targetUrl: '',
        channelUrl: '',
        commentInstructions: '',
        domainFqdn: '',
        purchasedAt: new Date().toISOString().slice(0, 10),
        markPaid: true,
        catalogUrl: {{ \Illuminate\Support\Js::from(route('admin.users.manual-purchase.catalog')) }},
        get selectedProduct() {
            return this.products.find(p => p.slug === this.productSlug) || null;
        },
        get isWebsite() {
            return false;
        },
        get selectedVariant() {
            const product = this.selectedProduct;
            if (! product) return null;
            return product.variants.find(v => String(v.id) === String(this.variantId)) || null;
        },
        get isPerUnit() {
            return !!(this.selectedVariant && this.selectedVariant.per_unit);
        },
        get companions() {
            return this.isPerUnit ? (this.selectedProduct?.companions || []) : [];
        },
        get extraUnits() {
            return this.companions.reduce((sum, row) => sum + Math.max(0, Number(this.qty[row.task] || 0) - Number(row.min)), 0);
        },
        get perUnitTotal() {
            const price = Number(this.selectedVariant?.unit_price || 0);
            return price * (Number(this.units || 0) + this.extraUnits);
        },
        reset() {
            this.userId = null;
            this.userLabel = '';
            this.action = '';
            this.loading = false;
            this.submitting = false;
            this.error = '';
            this.categoryId = '';
            this.serviceId = '';
            this.productSlug = '';
            this.variantId = '';
            this.units = '';
            this.qty = {};
            this.targetUrl = '';
            this.channelUrl = '';
            this.commentInstructions = '';
            this.domainFqdn = '';
            this.purchasedAt = new Date().toISOString().slice(0, 10);
            this.markPaid = true;
            this.products = [];
            this.services = [];
            this.categories = [];
        },
        async loadCatalog() {
            this.loading = true;
            this.error = '';
            try {
                const params = new URLSearchParams();
                if (this.categoryId) params.set('service_category_id', this.categoryId);
                if (this.serviceId) params.set('product_type_id', this.serviceId);
                const res = await fetch(this.catalogUrl + (params.toString() ? ('?' + params.toString()) : ''), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (! res.ok) throw new Error('Could not load catalog.');
                const data = await res.json();
                this.categories = data.categories || [];
                this.services = data.services || [];
                this.products = data.products || [];
                if (this.productSlug && ! this.products.some(p => p.slug === this.productSlug)) {
                    this.productSlug = '';
                    this.variantId = '';
                }
            } catch (e) {
                this.error = e.message || 'Could not load catalog.';
            } finally {
                this.loading = false;
            }
        },
        onCategoryChange() {
            this.serviceId = '';
            this.productSlug = '';
            this.variantId = '';
            this.loadCatalog();
        },
        onServiceChange() {
            this.productSlug = '';
            this.variantId = '';
            this.loadCatalog();
        },
        onProductChange() {
            this.variantId = '';
            this.units = '';
            const product = this.selectedProduct;
            if (product?.variants?.length === 1) {
                this.variantId = String(product.variants[0].id);
                this.onVariantChange();
            }
        },
        onVariantChange() {
            const variant = this.selectedVariant;
            if (variant?.per_unit) {
                this.units = String(variant.min_units || 1);
            } else {
                this.units = '';
            }
            const qty = {};
            (this.selectedProduct?.companions || []).forEach(row => { qty[row.task] = String(row.min); });
            this.qty = qty;
        },
    }"
    x-on:admin-manual-purchase.window="
        reset();
        userId = $event.detail?.id ?? null;
        userLabel = $event.detail?.label ?? '';
        action = $event.detail?.action ?? '';
        if (userId && action) {
            loadCatalog().then(() => $dispatch('open-modal', 'admin-manual-purchase'));
        }
    "
    x-on:close-modal.window="
        if ($event.detail === 'admin-manual-purchase') reset();
    "
>
    <x-dashboard.modal name="admin-manual-purchase" maxWidth="lg">
        <div class="space-y-4 p-1">
            <div>
                <h3 class="text-lg font-semibold text-text-primary">Manual purchase</h3>
                <p class="mt-1 text-sm text-text-secondary">
                    Create a platform order for
                    <span class="font-medium text-text-primary" x-text="userLabel || 'this user'"></span>.
                    Works even when checkout manual bank transfer is off. Mark paid to fulfill immediately.
                </p>
            </div>

            <p x-show="error" x-cloak class="rounded-lg border border-danger/30 bg-danger/5 px-3 py-2 text-sm text-danger" x-text="error"></p>
            <p x-show="loading" x-cloak class="text-sm text-text-muted">Loading catalog…</p>

            <form
                method="POST"
                x-bind:action="action"
                class="space-y-4"
                x-on:submit="
                    if (!action || !userId || !productSlug || !variantId) {
                        $event.preventDefault();
                        error = 'Select a product and plan.';
                        return;
                    }
                    if (isPerUnit) {
                        const u = Number(units);
                        const min = Number(selectedVariant?.min_units || 1);
                        const max = Number(selectedVariant?.max_units || 100000);
                        if (!Number.isFinite(u) || u < min || u > max) {
                            $event.preventDefault();
                            error = 'Enter units between ' + min + ' and ' + max + '.';
                            return;
                        }
                        const badRow = companions.find(row => {
                            const q = Number(qty[row.task]);
                            return !Number.isFinite(q) || q < Number(row.min) || q > Number(row.max);
                        });
                        if (badRow) {
                            $event.preventDefault();
                            error = 'Enter between ' + badRow.min + ' and ' + badRow.max + ' ' + badRow.label + '.';
                            return;
                        }
                    }
                    if (!/^https?:\/\/\S+\.\S+/i.test(targetUrl.trim())) {
                        $event.preventDefault();
                        error = 'Enter the campaign link (post, video or profile URL).';
                        return;
                    }
                    if (selectedProduct?.needs_channel_url && !/^https?:\/\/\S+\.\S+/i.test(channelUrl.trim())) {
                        $event.preventDefault();
                        error = 'Enter the channel URL agents will subscribe to.';
                        return;
                    }
                    if (selectedProduct?.needs_comment_instructions && !commentInstructions.trim()) {
                        $event.preventDefault();
                        error = 'Enter the comment instructions.';
                        return;
                    }
                    if (isWebsite && !domainFqdn.trim()) {
                        $event.preventDefault();
                        error = 'Enter the customer\'s existing domain.';
                        return;
                    }
                    submitting = true;
                "
            >
                @csrf

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-text-secondary">Category</label>
                        <select
                            x-model="categoryId"
                            x-on:change="onCategoryChange()"
                            class="w-full rounded-lg border border-border-subtle bg-surface px-3 py-2 text-sm"
                        >
                            <option value="">All categories</option>
                            <template x-for="cat in categories" :key="cat.id">
                                <option :value="cat.id" x-text="cat.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-text-secondary">Service</label>
                        <select
                            x-model="serviceId"
                            x-on:change="onServiceChange()"
                            class="w-full rounded-lg border border-border-subtle bg-surface px-3 py-2 text-sm"
                        >
                            <option value="">All services</option>
                            <template x-for="svc in services" :key="svc.id">
                                <option :value="svc.id" x-text="svc.name"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-text-secondary">Product</label>
                    <select
                        name="product_slug"
                        x-model="productSlug"
                        x-on:change="onProductChange()"
                        required
                        class="w-full rounded-lg border border-border-subtle bg-surface px-3 py-2 text-sm"
                    >
                        <option value="">Select product…</option>
                        <template x-for="product in products" :key="product.slug">
                            <option :value="product.slug" x-text="product.title"></option>
                        </template>
                    </select>
                </div>

                <div x-show="selectedProduct" x-cloak>
                    <label class="mb-1 block text-sm font-medium text-text-secondary">Plan / variant</label>
                    <select
                        name="variant_id"
                        x-model="variantId"
                        x-on:change="onVariantChange()"
                        required
                        class="w-full rounded-lg border border-border-subtle bg-surface px-3 py-2 text-sm"
                    >
                        <option value="">Select plan…</option>
                        <template x-for="variant in (selectedProduct?.variants || [])" :key="variant.id">
                            <option
                                :value="variant.id"
                                x-text="variant.label + ' — ' + (variant.per_unit
                                    ? ('From ' + window.formatMoney(variant.starting_from || 0))
                                    : window.formatMoney(variant.price)) + (variant.duration_months ? (' · ' + variant.duration_months + ' mo') : '')"
                            ></option>
                        </template>
                    </select>
                    <p x-show="selectedVariant && !isPerUnit" x-cloak class="mt-1 text-xs text-text-muted">
                        Selected price: <span x-text="selectedVariant ? window.formatMoney(selectedVariant.price) : ''"></span>
                    </p>
                    <p x-show="isPerUnit" x-cloak class="mt-1 text-xs text-text-muted">
                        <span x-text="selectedVariant ? window.formatMoney(selectedVariant.unit_price || 0, { precision: 2, max: 6 }) : ''"></span>
                        per unit · min <span x-text="selectedVariant?.min_units"></span>
                    </p>
                </div>

                <div x-show="isPerUnit" x-cloak>
                    <label class="mb-1 block text-sm font-medium text-text-secondary">
                        Units (<span x-text="selectedVariant?.unit_label_plural || 'units'"></span>)
                    </label>
                    <input
                        type="number"
                        name="units"
                        x-model="units"
                        :min="selectedVariant?.min_units || 1"
                        :max="selectedVariant?.max_units || 100000"
                        :required="isPerUnit"
                        class="w-full rounded-lg border border-border-subtle bg-surface px-3 py-2 text-sm"
                    />
                </div>

                <template x-for="row in companions" :key="productSlug + row.task">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-text-secondary" x-text="row.label"></label>
                        <input
                            type="number"
                            :name="'qty[' + row.task + ']'"
                            x-model="qty[row.task]"
                            :min="row.min"
                            :max="row.max"
                            required
                            class="w-full rounded-lg border border-border-subtle bg-surface px-3 py-2 text-sm"
                        />
                        <p class="mt-1 text-xs text-text-muted">
                            Between <span x-text="Number(row.min).toLocaleString()"></span> and <span x-text="Number(row.max).toLocaleString()"></span>. Units above the minimum are billed at the unit price.
                        </p>
                    </div>
                </template>

                <p x-show="isPerUnit" x-cloak class="text-sm text-text-secondary">
                    Total: <span class="font-semibold text-text-primary" x-text="window.formatMoney(perUnitTotal)"></span>
                    <span x-show="extraUnits > 0" x-cloak class="text-xs text-text-muted">(includes <span x-text="extraUnits.toLocaleString()"></span> extra units)</span>
                </p>

                <div x-show="selectedProduct" x-cloak>
                    <label class="mb-1 block text-sm font-medium text-text-secondary" x-text="selectedProduct?.target_is_channel ? 'Channel URL' : 'Video URL'"></label>
                    <input
                        type="url"
                        name="target_url"
                        x-model="targetUrl"
                        placeholder="https://"
                        :required="!!selectedProduct"
                        class="w-full rounded-lg border border-border-subtle bg-surface px-3 py-2 text-sm"
                    />
                    <p class="mt-1 text-xs text-text-muted">
                        <span x-show="selectedProduct?.needs_channel_url || selectedProduct?.needs_comment_instructions">Agents do: <span x-text="selectedProduct?.tasks"></span>. Each unit is one new agent completing all tasks.</span>
                        <span x-show="!(selectedProduct?.needs_channel_url || selectedProduct?.needs_comment_instructions)">The YouTube video or channel URL agents will work on.</span>
                    </p>
                </div>

                <div x-show="selectedProduct?.needs_channel_url" x-cloak>
                    <label class="mb-1 block text-sm font-medium text-text-secondary">Channel URL</label>
                    <input
                        type="url"
                        name="channel_url"
                        x-model="channelUrl"
                        placeholder="https://www.youtube.com/@channel"
                        :required="!!selectedProduct?.needs_channel_url"
                        class="w-full rounded-lg border border-border-subtle bg-surface px-3 py-2 text-sm"
                    />
                    <p class="mt-1 text-xs text-text-muted">The channel agents subscribe to. The video must be on this channel.</p>
                </div>

                <div x-show="selectedProduct?.needs_comment_instructions" x-cloak>
                    <label class="mb-1 block text-sm font-medium text-text-secondary">Comment instructions</label>
                    <textarea
                        name="comment_instructions"
                        x-model="commentInstructions"
                        rows="3"
                        maxlength="1000"
                        :required="!!selectedProduct?.needs_comment_instructions"
                        class="w-full rounded-lg border border-border-subtle bg-surface px-3 py-2 text-sm"
                    ></textarea>
                    <p class="mt-1 text-xs text-text-muted">What agents should comment about (up to 1,000 characters).</p>
                </div>

                <div x-show="isWebsite" x-cloak class="rounded-xl border border-primary/20 bg-primary/5 p-4 space-y-2">
                    <label class="block text-sm font-medium text-text-primary">Existing domain</label>
                    <input
                        type="text"
                        name="domain_fqdn"
                        x-model="domainFqdn"
                        placeholder="shop.example.com"
                        class="w-full rounded-lg border border-border-subtle bg-surface px-3 py-2 text-sm"
                    />
                    <p class="text-xs text-text-muted">Connect an existing domain or subdomain (e.g. example.com, shop.example.com). No availability check — enter what the customer already owns.</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-text-secondary">Purchase date</label>
                    <input
                        type="date"
                        name="purchased_at"
                        x-model="purchasedAt"
                        required
                        class="w-full rounded-lg border border-border-subtle bg-surface px-3 py-2 text-sm"
                    />
                    <p class="mt-1 text-xs text-text-muted">Defaults to today. Back-date if the customer paid earlier.</p>
                </div>

                <input type="hidden" name="mark_paid" value="0">
                <label class="inline-flex items-center justify-between gap-3 text-sm text-text-primary w-full">
                    <span>Mark paid immediately (creates the campaign)</span>
                    <input type="checkbox" name="mark_paid" value="1" x-model="markPaid" class="h-4 w-4 rounded border-border-subtle accent-primary">
                </label>

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <x-dashboard.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'admin-manual-purchase')">
                        Cancel
                    </x-dashboard.button>
                    <x-dashboard.button type="submit" x-bind:disabled="submitting || loading">
                        <span x-show="!submitting">Purchase</span>
                        <span x-cloak x-show="submitting">Processing…</span>
                    </x-dashboard.button>
                </div>
            </form>
        </div>
    </x-dashboard.modal>
</div>
