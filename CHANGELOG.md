# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [1.0.0] — 2026-05-16

### 🎉 Initial public release

#### Core Inventory
- Add, edit, delete components with: name, model, category, quantity, condition (New / Used / Refurbished), specs, location, purchase price, product URL, datasheet URL, and free-text notes
- Multi-angle image upload (up to 6 photos per component); auto-resized to 1200 px max, ~100–200 KB — saves ~98% storage vs raw phone photos
- Full-text search across name, model, specs, and location; category filter dropdown
- Sortable columns: Name, Model, Category, Qty, Status, Location (ascending / descending)

#### AI Features
- **AI Auto-Identify**: drag-and-drop photos → Gemini or GPT-4o returns `name`, `model`, `category`, and `specs` as structured JSON; fields auto-fill
- **Creative Engine** (`projects.php`): analyses full inventory and returns 5 tailored project ideas with complexity rating, time estimate, skill domain, components-from-lab list, and shopping links for missing parts. Results cached in DB — returning to the page shows last results instantly at zero API cost
- **Project Blueprints** (`project_blueprint.php`): one-click generation of full build guides (wiring diagram text, BOM, and firmware/Arduino code)
- **Lab Assistant** (`chat.php`): inventory-aware chat; AI knows every component and cached product documentation. Suggested prompt chips on first load
- **Web Enrichment** (`enrich_api.php`): server-side scrape of `product_url` / `datasheet_url`; stores up to ~3,000 chars of plain text in `enriched_data`; injected into all AI prompts automatically

#### Bulk Import Hub (`bulk_import.php`)
- **CSV / Spreadsheet Import**: upload CSV or TSV from Excel, Google Sheets, Notion, etc.; auto-detects delimiter; 30+ column-header aliases auto-mapped; optional "Auto-enrich via Product URL" runs enrichment sequentially after import with live status badges
- **ZIP Upload**: flat ZIP (all photos in root = one component) or subfolder ZIP (one folder per component); optional `description.txt` per folder; AI identifies each component from its first photo; path-traversal (ZipSlip) protection
- **Image Group Wizard**: drag photos directly into browser, declare groups, add optional name hint or leave blank for full AI identification; processes groups sequentially with 3s rate-limit buffer; live per-card status (⏳ → ✅)

#### Location Manager & QR Labels
- **Location Manager** (`locations.php`): all components grouped by `location` field; per-location stats (item count, total units, category list)
- **Container QR sticker**: offline-readable QR code whose payload is plain text (Name / Model / Category / Qty / Location) — scannable without network; URL appended for one-tap browser open when online
- **Container Manifest** (`container_manifest.php`): live item table per location with B&W-printable version; includes **Verified ☐** checkbox column for physical stock audits; stats panel hidden in print output
- **QR Label Printer** (`print_labels.php`): bulk or single-item label printing; three Avery-compatible sizes (62×29 mm, 99×57 mm, 99×93 mm)

#### Dashboard Bulk Actions
- Multi-select checkboxes (per-row + select-all)
- Floating action bar: Category change, Status change, Location change, Export CSV, Print Labels, Delete (with image file cleanup)

#### Settings & Auth
- Password-gated login (`index.php`) with show/hide toggle
- AI provider toggle: **Google Gemini** ↔ **OpenAI GPT-4o** — switchable without code edits
- API key stored in DB, managed via Settings UI (`settings.php`)

#### Infrastructure
- Single-File Mandate: each page (HTML + CSS + JS) in one `.php` file for portability
- PHP built-in server supported via `php.ini` with generous upload limits
- Apache / MAMP / XAMPP supported via `.htaccess`
- All DB queries use PDO prepared statements
- `schema.sql` with `CREATE TABLE IF NOT EXISTS` — safe to re-run
- Tailwind CSS v3 compiled to local `assets/app.css` — no CDN dependency at runtime

---

## [Unreleased]

### Added — Internationalization (i18n)
- **3-language support**: English (default), Hebrew (RTL), Spanish (LTR)
- **`assets/i18n.js`** — zero-dependency ES6 `localizationController` module with:
  - Async JSON locale loader (`loadLocale(langCode)`)
  - Dot-notation key resolver with `{{param}}` interpolation (`t('key', {p:v})`)
  - Automatic RTL/LTR switching via `document.documentElement.dir`
  - DOM walker applying `data-i18n`, `data-i18n-text`, `data-i18n-placeholder`, `data-i18n-title`, `data-i18n-aria` attributes
  - `localStorage` persistence under key `diy_inventory_lang`
  - Browser language auto-detection as fallback
- **`assets/locales/en.json`** — 141-key English source dictionary across 10 namespaces (nav, common, login, settings, dashboard, inventory, brewing, projects, chat, locations)
- **`assets/locales/he.json`** — Full Hebrew translation (141/141 keys, 100% coverage)
- **`assets/locales/es.json`** — Full Spanish translation (141/141 keys, 100% coverage)
- **Language selector** in `settings.php` — dropdown with flag emojis, instant no-reload switching
- **RTL CSS block** prepended to `assets/app.css` — scoped `html[dir="rtl"]` overrides for sidebar, main margin, nav links, stat card accents, search icon, table alignment, markdown blockquotes, and form labels
- **`npm run validate:i18n`** — coverage check script; exits non-zero and lists missing keys if any locale is incomplete
- `data-i18n*` attributes added to `dashboard.php` and `settings.php` (sidebar nav, stat cards, header, search bar, buttons)

### Planned
- Location hierarchy: Area → Shelf → Container relational structure
- Multi-user support with per-user inventory
- Dark / light theme toggle
- Pagination for very large inventories (>500 items)
- Scheduled cron-based enrichment
