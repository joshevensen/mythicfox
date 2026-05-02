# Build checklist

Live progress tracker. Check the box when a task's `status` is `complete` (i.e. all acceptance criteria pass and the work is committed). Each line links to the task file.

**Total: 40 / 67 tasks complete**

Sizes: `S` < 1h, `M` 1–4h, `L` 4h+.

---

## Phase 00 — Foundation (6 / 6)

- [x] **00-001** `S` — [Switch local development DB from SQLite to PostgreSQL](00-foundation/00-001-postgres-local-dev.md)
- [x] **00-002** `S` — [Strip Fortify down to single-user login (disable 2FA, registration, password reset)](00-foundation/00-002-fortify-single-user-config.md)
- [x] **00-003** `S` — [Add `user:create` and `user:reset-password` Artisan commands](00-foundation/00-003-user-management-artisan-commands.md)
- [x] **00-004** `S` — [Add app config keys and `.env.example` entries for TCGPlayer, DO Spaces, and brand](00-foundation/00-004-app-config-and-env-keys.md)
- [x] **00-005** `S` — [Install Browsershot and smalot/pdfparser composer packages](00-foundation/00-005-install-build-dependencies.md)
- [x] **00-006** `M` — [Confirm Pest baseline and add GitHub Actions CI](00-foundation/00-006-baseline-tests-and-ci.md)

## Phase 10 — Catalog (10 / 10)

- [x] **10-001** `S` — [Create Product model and migration with default pricing rules](10-catalog/10-001-product-model.md)
- [x] **10-002** `S` — [Create Set model and migration with nullable per-set pricing overrides](10-catalog/10-002-set-model.md)
- [x] **10-003** `M` — [Create Card model and migration keyed on tcgplayer_id](10-catalog/10-003-card-model.md)
- [x] **10-004** `S` — [Create Inventory model and migration with calculated/override/last-exported price columns](10-catalog/10-004-inventory-model.md)
- [x] **10-005** `L` — [Implement PricingCustomExport CSV importer (catalog seed + market-price refresh)](10-catalog/10-005-pricing-custom-export-importer.md)
- [x] **10-006** `L` — [Implement MyPricing CSV importer with bootstrap and reconciliation modes](10-catalog/10-006-mypricing-importer.md)
- [x] **10-007** `M` — [Implement dual-input pricing algorithm service](10-catalog/10-007-pricing-algorithm.md)
- [x] **10-008** `M` — [Implement pricing-rules resolver and inventory recompute service](10-catalog/10-008-pricing-rules-resolver-and-recompute.md)
- [x] **10-009** `M` — [Implement MyPricing CSV exporter (round-trip back to TCGPlayer)](10-catalog/10-009-mypricing-exporter.md)
- [x] **10-010** `S` — [Add catalog domain seeders and shared factory states for downstream phases](10-catalog/10-010-catalog-seeders-and-factories.md)

## Phase 20 — Orders (12 / 12)

- [x] **20-001** `M` — [Create `files` table, model, and storage path helper](20-orders/20-001-files-table-and-model.md)
- [x] **20-002** `M` — [Create `orders` table and Eloquent model](20-orders/20-002-orders-table-and-model.md)
- [x] **20-003** `M` — [Create `order_items` table and Eloquent model](20-orders/20-003-order-items-table-and-model.md)
- [x] **20-004** `M` — [Implement OrderList CSV parser](20-orders/20-004-orderlist-csv-parser.md)
- [x] **20-005** `M` — [Implement ShippingExport CSV parser](20-orders/20-005-shipping-export-csv-parser.md)
- [x] **20-006** `M` — [Implement PullSheet CSV parser (with `Order Quantity` split)](20-orders/20-006-pullsheet-csv-parser.md)
- [x] **20-007** `L` — [Implement PackingSlips PDF parser (smalot/pdfparser)](20-orders/20-007-packing-slip-pdf-parser.md)
- [x] **20-008** `L` — [Four-source merge + immutable-snapshot order import](20-orders/20-008-four-source-merge-and-import.md)
- [x] **20-009** `M` — [Inventory decrement service for new order line items](20-orders/20-009-inventory-decrement-on-import.md)
- [x] **20-010** `S` — [Validate `tcgplayer_order_number` against `TCGPLAYER_SELLER_ID`](20-orders/20-010-seller-id-validation.md)
- [x] **20-011** `M` — [Re-import and replay handling (idempotency hardening)](20-orders/20-011-reimport-replay-handling.md)
- [x] **20-012** `S` — [Factories and seeders for the orders domain](20-orders/20-012-factories-and-seeders.md)

## Phase 30 — Components (12 / 13)

- [x] **30-001** `S` — [Wire brand color tokens into Tailwind theme (light + dark)](30-components/30-001-tailwind-theme-brand-tokens.md)
- [x] **30-002** `M` — [Install PrimeVue + PrimeIcons and register Mythic Fox Aura preset](30-components/30-002-primevue-install-and-preset.md)
- [x] **30-003** `S` — [Make dark mode the default with persistence across sessions](30-components/30-003-dark-mode-default-and-persistence.md)
- [x] **30-004** `M` — [Build MfAppLayout root layout (top nav + page container slots)](30-components/30-004-app-shell-layout.md)
- [x] **30-005** `M` — [Build MfTopNav (sticky top navigation with mobile hamburger drawer)](30-components/30-005-mftopnav.md)
- [x] **30-006** `S` — [Build MfPageHeader (title + breadcrumbs + action button slot)](30-components/30-006-mfpageheader.md)
- [x] **30-007** `L` — [Build MfTable (lazy DataTable wrapper with URL state, mobile-row slot)](30-components/30-007-mftable.md)
- [x] **30-008** `M` — [Build MfFilterPanel + MfFilterChip + MfSearchInput](30-components/30-008-mffilterpanel.md)
- [x] **30-009** `M` — [Build MfFormField + MfMoneyInput + MfQtyInput + MfDatePicker](30-components/30-009-mfformfield-and-inputs.md)
- [x] **30-010** `M` — [Build MfStatusPill + MfMoney + MfDate + MfMonospaceId + MfCardIdentity](30-components/30-010-mfstatuspill-and-display.md)
- [x] **30-011** `S` — [Build MfEmptyState (and confirm MfTable's skeleton loading)](30-components/30-011-mfemptystate-and-loading.md)
- [x] **30-012** `M` — [Build MfConfirmDialog + MfToast + MfErrorBanner feedback components](30-components/30-012-mfconfirm-toast-error-banner.md)
- [ ] **30-013** `M` — [Build MfFileDropzone (drag-drop + click-to-browse uploader)](30-components/30-013-mffiledropzone.md)

## Phase 40 — Public pages (0 / 4)

- [ ] **40-001** `M` — [Public layout shell + brand styling for public-facing pages](40-public-pages/40-001-public-layout-and-brand-styling.md)
- [ ] **40-002** `L` — [Public homepage at `/` — hero, about, what-you-get, what-buyers-say](40-public-pages/40-002-public-homepage.md)
- [ ] **40-003** `M` — [Login page at `/login` — email + password](40-public-pages/40-003-login-page.md)
- [ ] **40-004** `S` — [Public footer + sitemap.xml + robots.txt](40-public-pages/40-004-public-footer-and-shared-chrome.md)

## Phase 50 — Admin pages (0 / 6)

- [ ] **50-001** `M` — [Admin layout / authenticated app shell with MfTopNav](50-admin-pages/50-001-admin-layout-app-shell.md)
- [ ] **50-002** `M` — [Dashboard page at `/dashboard` — greeting + quick-action tiles](50-admin-pages/50-002-dashboard-page.md)
- [ ] **50-003** `L` — [Settings — Pricing Rules section (per-product + per-set modals)](50-admin-pages/50-003-settings-pricing-rules.md)
- [ ] **50-004** `M` — [Settings — File History section (paginated audit log)](50-admin-pages/50-004-settings-file-history.md)
- [ ] **50-005** `M` — [Settings — Seller Stats Scraper section (status card + Refresh now)](50-admin-pages/50-005-settings-seller-stats-scraper.md)
- [ ] **50-006** `L` — [Add Cards page at `/add-cards` — scoped bulk-entry workflow](50-admin-pages/50-006-add-cards-page.md)

## Phase 60 — Data pages (0 / 8)

- [ ] **60-001** `L` — [Orders index page — table, filters, sort, default 90-day window](60-data-pages/60-001-orders-index-page.md)
- [ ] **60-002** `M` — [Orders page — Import modal (four-file dropzone, queues importer)](60-data-pages/60-002-orders-import-modal.md)
- [ ] **60-003** `M` — [Orders page — packing-slip + TCGPlayer actions (per-row + bulk)](60-data-pages/60-003-orders-packing-slip-actions.md)
- [ ] **60-004** `L` — [Catalog browse page — aggregated table with expand-rows + stale-data indicator](60-data-pages/60-004-catalog-browse-page.md)
- [ ] **60-005** `M` — [Catalog page — PricingCustomExport upload modal](60-data-pages/60-005-catalog-pricingcustomexport-upload.md)
- [ ] **60-006** `L` — [Inventory page — required-filter table with inline qty/override edits + per-row + bulk actions](60-data-pages/60-006-inventory-page-table.md)
- [ ] **60-007** `L` — [Inventory page — Export Pricing flow (recompute → preview modal → CSV download)](60-data-pages/60-007-inventory-export-pricing-modal.md)
- [ ] **60-008** `M` — [Shared composable for URL-driven table state (pagination, sort, filters, chips)](60-data-pages/60-008-table-url-state-composable.md)

## Phase 70 — Jobs (0 / 5)

- [ ] **70-001** `L` — [Render packing slip via HTML + print CSS (browser-printed, no PDF artifact)](70-jobs/70-001-packing-slip-renderer.md)
- [ ] **70-002** `M` — [Packing slip multi-sheet support (Sheet X of N, 20-card chunking)](70-jobs/70-002-packing-slip-multi-sheet.md)
- [ ] **70-003** `L` — [RefreshSellerStats scraper job (daily, Browsershot, failure tracking)](70-jobs/70-003-seller-stats-scraper-job.md)
- [ ] **70-004** `M` — [Weekly file-cleanup job (90-day import retention, audit row preserved)](70-jobs/70-004-files-purge-job.md)
- [ ] **70-005** `M` — [Nightly DB backup job (pg_dump → DO Spaces)](70-jobs/70-005-db-backup-job.md)

## Phase 80 — Deploy (0 / 3)

- [ ] **80-001** `L` — [Configure Laravel Forge site (deploy script, queue daemon, scheduler, SSL, env vars)](80-deploy/80-001-forge-deployment-config.md)
- [ ] **80-002** `L` — [Provision DigitalOcean droplet (Chrome, PHP extensions, PostgreSQL, pg_dump)](80-deploy/80-002-droplet-provisioning.md)
- [ ] **80-003** `M` — [Create DigitalOcean Spaces bucket and IAM credentials (private, signed-URL only)](80-deploy/80-003-do-spaces-bucket.md)

---

## How to use this file

When a task is completed:

1. Flip the box from `[ ]` to `[x]` on its line.
2. Update the per-phase running tally at the top of the section (e.g. `(0 / 6)` → `(1 / 6)`).
3. Update the total at the top of the file.
4. Set `status: complete` in the task's frontmatter.

The autonomous build agent does all four steps in the same commit when it finishes a task. If you complete a task manually, do the same.
