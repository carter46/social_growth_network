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
            const product = this.selectedProduct;
            if (product?.variants?.length === 1) {
                this.variantId = String(product.variants[0].id);
            }
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
                        required
                        class="w-full rounded-lg border border-border-subtle bg-surface px-3 py-2 text-sm"
                    >
                        <option value="">Select plan…</option>
                        <template x-for="variant in (selectedProduct?.variants || [])" :key="variant.id">
                            <option
                                :value="variant.id"
                                x-text="variant.label + ' — ₦' + Number(variant.price).toLocaleString() + (variant.duration_months ? (' · ' + variant.duration_months + ' mo') : '')"
                            ></option>
                        </template>
                    </select>
                    <p x-show="selectedVariant" x-cloak class="mt-1 text-xs text-text-muted">
                        Selected price: ₦<span x-text="selectedVariant ? Number(selectedVariant.price).toLocaleString(undefined, {minimumFractionDigits: 2}) : ''"></span>
                    </p>
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
                    <span>Mark paid immediately (fulfill order &amp; create tool)</span>
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
