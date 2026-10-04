<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>UI Styleguide | {{ config('app.name') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface text-text-primary font-sans min-h-screen">
    <div class="max-w-marketing mx-auto px-5 sm:px-6 py-10 space-y-16">
        <header class="space-y-2">
            <p class="text-xs uppercase tracking-widest text-text-muted">Local only · /dev/ui</p>
            <h1 class="text-3xl font-bold">Design system showcase</h1>
            <p class="text-text-secondary">Every shared <code class="text-primary">&lt;x-ui.*&gt;</code> component must appear here before production use.</p>
        </header>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold">Colors</h2>
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
                @foreach (['surface','elevated','muted','primary','accent','success','warning','danger'] as $c)
                    <div class="rounded-xl border border-border-default overflow-hidden">
                        <div class="h-16 bg-{{ $c }}"></div>
                        <p class="p-2 text-xs text-text-secondary">{{ $c }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold">Typography</h2>
            <div class="space-y-2">
                <p class="text-3xl font-bold">Heading 3xl</p>
                <p class="text-2xl font-bold">Heading 2xl</p>
                <p class="text-lg font-semibold">Heading lg</p>
                <p class="text-sm text-text-secondary">Secondary body copy</p>
                <p class="text-xs text-text-muted">Muted caption</p>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold">Buttons</h2>
            <div class="flex flex-wrap gap-3">
                <x-ui.button>Primary</x-ui.button>
                <x-ui.button variant="secondary">Secondary</x-ui.button>
                <x-ui.button variant="ghost">Ghost</x-ui.button>
                <x-ui.button variant="danger">Danger</x-ui.button>
                <x-ui.button variant="warning">Warning</x-ui.button>
                <x-ui.button variant="success" icon="check">Success</x-ui.button>
                <x-ui.button loading>Loading</x-ui.button>
                <x-ui.button disabled>Disabled</x-ui.button>
                <x-ui.button size="sm">Small</x-ui.button>
                <x-ui.button size="lg">Large</x-ui.button>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold">Inputs</h2>
            <div class="max-w-form space-y-4">
                <x-ui.input label="Email" name="demo_email" type="email" placeholder="you@example.com" />
                <x-ui.select label="Status" name="demo_status">
                    <option>Pending</option>
                    <option>Approved</option>
                </x-ui.select>
                <x-ui.textarea label="Notes" name="demo_notes" placeholder="Optional notes..." />
                <x-ui.alert type="error" title="Validation">Inline alert for form errors only.</x-ui.alert>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold">Badges</h2>
            <div class="flex flex-wrap gap-2">
                @foreach (['pending','approved','rejected','locked','completed','info'] as $s)
                    <x-ui.badge :status="$s" />
                @endforeach
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold">Cards & stats</h2>
            <x-ui.stat-grid>
                <x-ui.stat-card label="Available" value="₦120,000" icon="wallet" hint="NGN wallet" />
                <x-ui.stat-card label="Locked" value="₦8,500" icon="lock" />
                <x-ui.stat-card label="Orders" value="12" icon="orders" />
                <x-ui.stat-card label="Pending" value="3" icon="deposit" />
            </x-ui.stat-grid>
            <x-ui.card>
                <p class="text-sm text-text-secondary">Glass card body content.</p>
            </x-ui.card>
            <x-ui.card variant="solid">
                <p class="text-sm text-text-secondary">Solid card body content.</p>
            </x-ui.card>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold">Table</h2>
            <x-ui.table striped>
                <x-slot:head>
                    <x-ui.th>Reference</x-ui.th>
                    <x-ui.th>User</x-ui.th>
                    <x-ui.th>Amount</x-ui.th>
                    <x-ui.th>Status</x-ui.th>
                </x-slot:head>
                <tr class="hover:bg-muted/50">
                    <x-ui.td>DEP-001</x-ui.td>
                    <x-ui.td>demo@example.com</x-ui.td>
                    <x-ui.td>₦50,000</x-ui.td>
                    <x-ui.td><x-ui.badge status="pending" /></x-ui.td>
                </tr>
                <tr class="hover:bg-muted/50">
                    <x-ui.td>DEP-002</x-ui.td>
                    <x-ui.td>buyer@example.com</x-ui.td>
                    <x-ui.td>₦12,000</x-ui.td>
                    <x-ui.td><x-ui.badge status="approved" /></x-ui.td>
                </tr>
            </x-ui.table>
            <x-ui.table :empty="true" empty-title="No deposits yet" empty-description="Approved bank transfers will appear here." empty-icon="deposit" />
            <x-ui.table :loading="true" />
        </section>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold">Empty state</h2>
            <x-ui.card :padding="false">
                <x-ui.empty
                    icon="orders"
                    title="No orders yet"
                    description="When you purchase listings, your orders and escrow status will appear here."
                    :action="['label' => 'Browse marketplace', 'href' => '#']"
                    :secondary="['label' => 'Deposit funds', 'href' => '#']"
                />
            </x-ui.card>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold">Media & repeaters</h2>
            <p class="text-sm text-text-secondary">
                Use <code class="text-xs">x-dashboard.media-picker</code>, <code class="text-xs">x-dashboard.gallery-picker</code>,
                <code class="text-xs">x-dashboard.faq-repeater</code>, <code class="text-xs">x-dashboard.string-list-repeater</code>,
                and <code class="text-xs">x-dashboard.ajax-tabs</code>. The Media Library modal is included from the admin layout.
            </p>
            <x-ui.card class="space-y-4">
                <x-dashboard.media-picker name="demo_media_id" label="Demo image" />
                <x-dashboard.string-list-repeater name="demo_benefits" label="Benefits" :items="['Fast', 'Secure']" />
                <x-dashboard.faq-repeater name="demo_faq" label="FAQ" :items="[['q' => 'Example?', 'a' => 'Answer', 'open' => true]]" />
                <x-dashboard.ajax-tabs
                    :tabs="[
                        ['id' => 'one', 'label' => 'One', 'href' => '#'],
                        ['id' => 'two', 'label' => 'Two', 'href' => '#'],
                    ]"
                    active="one"
                />
            </x-ui.card>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold">Skeletons</h2>
            <x-ui.skeleton.page-header />
            <x-ui.skeleton.stat-grid />
            <x-ui.skeleton.card />
            <x-ui.skeleton.table :rows="4" :cols="5" />
        </section>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold">Nav link</h2>
            <div class="max-w-xs space-y-1 rounded-2xl border border-border-default bg-elevated p-3">
                <x-ui.nav-link href="#" icon="home" :active="true">Dashboard</x-ui.nav-link>
                <x-ui.nav-link href="#" icon="wallet">Wallet</x-ui.nav-link>
                <x-ui.nav-link href="#" icon="orders">Orders</x-ui.nav-link>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold">Pagination</h2>
            <p class="text-sm text-text-secondary">Production pages pass a Laravel LengthAwarePaginator. Demo shows the wrapper slot shape:</p>
            <x-ui.card>
                <p class="text-xs text-text-muted mb-3">Example call:</p>
                <code class="text-xs text-primary">&lt;x-ui.pagination :paginator="$items" /&gt;</code>
                <div class="mt-4 pt-4 border-t border-border-default text-sm text-text-secondary">
                    When <code class="text-text-muted">hasPages()</code> is true, Laravel’s default Tailwind pagination links render here.
                </div>
            </x-ui.card>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold">Breadcrumb & page header</h2>
            <x-ui.breadcrumb :items="[['Dashboard', '#'], ['Wallet', null]]" />
            <x-ui.page-header title="Wallet" subtitle="Manage your NGN balance">
                <x-slot:actions>
                    <x-ui.button href="#" icon="deposit">Deposit</x-ui.button>
                </x-slot:actions>
            </x-ui.page-header>
        </section>

        <section class="space-y-4" x-data>
            <h2 class="text-xl font-semibold">Modal & toast</h2>
            <div class="flex flex-wrap gap-3">
                <x-ui.button type="button" @click="$dispatch('open-modal', 'demo-modal')">Open modal</x-ui.button>
                <x-ui.button type="button" variant="secondary" @click="window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'success', message: 'Demo toast, auto dismiss in 5s' } }))">Fire toast</x-ui.button>
            </div>
            <x-ui.modal name="demo-modal" title="Approve deposit?" description="This action credits the user wallet." confirm-label="Approve" variant="default" />
        </section>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold">Icons ({{ count($icons) }})</h2>
            <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 gap-3">
                @foreach ($icons as $icon)
                    <div class="flex flex-col items-center gap-2 rounded-xl border border-border-default p-3 text-text-secondary">
                        <x-ui.icon :name="$icon" class="w-6 h-6" />
                        <span class="text-[10px] truncate w-full text-center">{{ $icon }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold">Layout.page example</h2>
            <x-layout.page title="Example page" subtitle="Mandatory grid: header → content → pagination" width="content" :breadcrumb="[['Admin', '#'], ['Example', null]]">
                <x-slot:actions>
                    <x-ui.button size="sm" icon="plus">New</x-ui.button>
                </x-slot:actions>
                <x-ui.card>
                    <p class="text-sm text-text-secondary">Main content slot.</p>
                </x-ui.card>
            </x-layout.page>
        </section>
    </div>

    <x-ui.toast />
</body>
</html>
