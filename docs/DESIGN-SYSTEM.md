# Design System (TaskPulse visual language)

## Governance (read first)

**Authenticated dashboards use `<x-dashboard.*>` as the public API.**  
`<x-ui.*>` are shared primitives / internals (wrappers and marketing/dev surfaces may still call them).

Raw Tailwind is allowed only for:
- Page-specific layout grids unique to one screen
- Marketing hero sections (decorative blurs/gradients)
- One-off illustration placement

**Marketing typography:** marketing layouts/pages may use `font-display` (**Plus Jakarta Sans**) for brand display. Dashboard and admin stay **Inter only** (`font-sans`). Do not use Public Sans or Poppins.

### PR checklist
- [ ] Authenticated pages use `<x-dashboard.*>` (not new raw `x-ui.*` on dashboard blades)
- [ ] No per-page flash paragraphs — session flashes via toast (`x-dashboard.toast` / layout)
- [ ] Page width via `<x-layout.page width="...">` only (marketing uses `max-w-marketing`)
- [ ] Icons via `<x-dashboard.icon>` / `<x-ui.icon>` — no Material Symbols / icon fonts
- [ ] New shared primitives added under `components/ui` + dashboard wrapper when needed; demo on `/dev/ui`
- [ ] Confirmations use `<x-dashboard.modal>` — never `confirm()`

### Banned patterns
- `material-symbols-outlined`
- `onsubmit="return confirm(...)"`
- Bare "No items found" — use `<x-dashboard.empty>` / `<x-dashboard.empty-state>`
- `hidden` on nav without mobile drawer alternative
- `font-display` / Public Sans on **admin/dashboard** (Inter only; Plus Jakarta Sans OK on marketing via `font-display`)
- Ad-hoc `max-w-5xl` / `max-w-7xl` / `max-w-3xl` — use width tokens (`max-w-marketing`, `max-w-content-*`, `max-w-form`, `max-w-auth`)

---

## TaskPulse color language

| Role | Light | Dark |
|------|-------|------|
| Page surface | `#F8F9FF` | `#0B1220` (cool navy) |
| Secondary surface | `#EFF4FF` | `#111827` |
| Cards / elevated | `#FFFFFF` | `#152238` |
| Borders | `#C3C6D7` | `#2A3548` |
| Text | `#0B1C30` / `#434655` / `#737686` | `#E8EEF9` / `#A8B0C0` / `#8B93A7` |
| **Primary (actions)** | `#004AC6` (hover `#2563EB`) | `#2563EB` |
| **Accent (links / companion)** | `#2563EB` | `#B4C5FF` |
| **Success / verified** | `#006243` | `#68DBA9` |
| **Error** | `#BA1A1A` | `#BA1A1A` |
| Warning | `#F59E0B` | `#F59E0B` |

**Rules:** Blue = primary brand/actions. Green = success/verified only. Do not flood the UI with blue or green. Marketing may stay structurally dark; retheme with navy + blue, not green washes.

Typography: **Inter** (`font-sans`) body; **Plus Jakarta Sans** (`font-display`) marketing headings.

---

## Theme system

| Piece | Role |
|-------|------|
| `config/dashboard-themes.php` | **SSOT** for light/dark tokens, brand colors, charts, assets |
| `ThemeManager` | Resolve preference, payload, generate CSS variables (`--th-*`) |
| `partials/dashboard/theme-tokens.blade.php` | Injects CSS from config (dashboard paint) |
| `partials/dashboard/theme-boot.blade.php` | Early `data-theme` + dual theme payload |
| `resources/js/dashboard-theme.js` | Client resolve; localStorage `taskpulse.dashboard.theme` (falls back to legacy `7th.dashboard.theme`) |
| `PUT /theme-preference` | Persist preference; optional `system_theme` hint for assets/charts |
| `:root` in `app.css` | Marketing/auth dark TaskPulse fallbacks (not dashboard light/dark) |

Preferences: `light` \| `dark` \| `system`. Resolved themes: `light` \| `dark`.

Empty states may use optional theme assets via `<x-dashboard.asset>` / `empty-state` `assetKey`.

---

## Semantic theme tokens

| Token | Use |
|-------|-----|
| `bg-surface` | Page background |
| `bg-card` / `glass-card` | Glass cards |
| `bg-card-solid` | Solid admin cards |
| `bg-muted` | Zebra rows, subtle fills |
| `bg-elevated` | Dropdowns, modals, sidebars |
| `text-text-primary` | Headings, body |
| `text-text-secondary` | Labels |
| `text-text-muted` | Placeholders |
| `border-border-default` | Borders |
| `border-border-subtle` | Glass borders |
| `bg-overlay` | Modal / drawer scrim |
| `shadow-panel` | Elevated panels |

- Brand (theme CSS vars): `primary`, `primary-hover`, `accent` (interactive companion blue), `success` (verified/positive green), `warning`, `danger`.

---

## Layout widths

| `width` prop | Token | Use |
|--------------|-------|-----|
| `content-sm` | `max-w-content-sm` | Narrow prose |
| `content-md` | `max-w-content-md` | Ticket threads |
| `content` | `max-w-content` | Default dashboard |
| `content-lg` | `max-w-content-lg` | Analytics |
| `full` | `max-w-content-full` | Data tables |
| `form` | `max-w-form` | Create/edit forms |
| `auth` | `max-w-auth` | Login, OTP |
| *(marketing shell)* | `max-w-marketing` (1150px / `71.875rem`) | Marketing header, footer, and public pages |

Marketing containers use `max-w-marketing mx-auto` with horizontal padding `px-5 sm:px-6` (~20–24px) so content never touches screen edges. Do not invent a second marketing width token.

---

## Page grid (mandatory)

```
PageHeader → Toast (layout) → Breadcrumb → Content → Pagination
```

```blade
<x-layout.page title="Wallet" subtitle="..." width="content" :breadcrumb="[...]">
    <x-slot:actions>...</x-slot:actions>
    {{-- content --}}
    <x-slot:pagination>
        <x-dashboard.pagination :paginator="$items" />
    </x-slot:pagination>
</x-layout.page>
```

Section spacing: `space-y-section` (1.5rem).

---

## Dashboard components (`resources/views/components/dashboard/`)

Public API for authenticated user + admin pages:

| Component | Notes |
|-----------|-------|
| `button` / `input` / `select` / `textarea` | Form primitives; hint + `aria-invalid` |
| `checkbox` / `radio` / `toggle` / `search` | ARIA-aware controls |
| `card` / `alert` / `badge` / `modal` | Theme tokens; modal has focus trap |
| `table` / `th` / `td` | Sticky header, skeleton, empty-state |
| `empty` / `empty-state` | Icon or theme asset + title + description + CTAs |
| `pagination` | Paginator wrapper |
| `page-header` | Title / subtitle / actions |
| `stats-card` / `stat-card` / `stat-grid` | Metrics |
| `icon` / `toast` / `asset` | Icons, flashes, theme logos |
| `theme-switcher` | Light / Dark / System |
| `sidebar` | Nav landmark wrapper for `$slot` |
| `filter-bar` / `action-bar` / `section` | Page chrome helpers |
| `media-picker` / `gallery-picker` / `media-library-modal` | Media Library selection (no path text inputs) |
| `faq-repeater` / `string-list-repeater` | Structured content editors |
| `ajax-tabs` | Progressive-enhancement tab nav (`X-Dashboard-Tab`) |

Primitives live under `resources/views/components/ui/` and are wrapped by dashboard components where needed.

Layouts: `x-layout.page`, dashboard shells via `layouts.dashboard-user` / `layouts.dashboard-admin` + `partials/dashboard/shell-*`.

### Button loading

```blade
<form x-data="{ submitting: false }" @submit="submitting = true">
    <x-dashboard.button type="submit" x-bind:disabled="submitting" icon="check">Save</x-dashboard.button>
</form>
```

---

## Future themes

Add a theme key under `config/dashboard-themes.php` (`tokens`, `rgb`, `charts`, `assets`). Paint updates automatically via `ThemeManager::dashboardThemeStylesheet()`. Do not hand-duplicate `--th-*` blocks in `app.css` for dashboard themes.
