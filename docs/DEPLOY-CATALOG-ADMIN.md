# Catalog hierarchy & admin deploy notes

## Hostinger / shared hosting (Category → Product)

1. Pull latest code (includes tracked `public/build` if assets changed).
2. Run migrations (adds `platform_products.service_category_id` and attempts flatten backfill):
   ```bash
   php artisan migrate --force
   ```
3. **Required** after this deploy — ensure Category→Product ownership is filled (idempotent; does **not** clear `product_type_id`):
   ```bash
   php artisan catalog:backfill-hierarchy
   # equivalent ownership step alone:
   php artisan catalog:flatten-category-products
   ```
4. Clear caches:
   ```bash
   php artisan view:clear
   php artisan config:clear
   php artisan route:clear
   ```
5. Smoke:
   - Home “What do you want to grow?” shows **product** cards + category pills
   - `/services` lists platform categories (YouTube, Facebook, …)
   - `/services/youtube/youtube-views-lite` loads
   - Checkout works
   - Admin product edit has Category select
   - Confirm `product_type_id` is still populated on products

Optional: `CATALOG_USE_DB_HIERARCHY=true` in `.env` (default true).

## Fixed platform catalog

- Ownership: **ServiceCategory → PlatformProduct** via `service_category_id`.
- `/services/...` is a public path prefix only — not ProductType ownership.
- ProductType / Services admin remain for CMS/legacy (Phase 1 dual-write).
- Public visibility: product **published** + owning **ServiceCategory active**.  
  Until flatten runs, dual-read still shows products via active ProductType→Category when `service_category_id` is null.
- Categories / services / products are **integral** — admin cannot Add or Delete platform rows.
- Products: edit title, descriptions, prices, hero, status, sort, **Category**, and existing variant prices.
- **Sort:** categories, services, and products each use a unique global 1..N position. Run `catalog:backfill-hierarchy` after deploy to clear duplicate/zero ranks.

**Do not run `php artisan db:seed` or `ProductionSeeder` on Hostinger production.** Seeders are for fresh/local installs.

## What changed (history)

- Platform catalog cutover: Category → Product ownership (`service_category_id`).
- Legacy mid-layer ProductType kept populated for compatibility.
- Legacy `platform_categories` removed after cleanup migration.

## Rollback note

Phase 1 keeps `product_type_id` populated. Restore from DB backup if you must roll back past the `service_category_id` migration.
