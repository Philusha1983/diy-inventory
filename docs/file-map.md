# 🗂 File Map & Project Structure

This document details the layout of the repository and the specific role of every file in the **DIY Lab Inventory & AI Orchestrator** codebase.

---

## 📁 Repository Structure

```text
diy-inventory/
├── index.php                    # Login / password gate
├── logout.php                   # Session teardown + redirect to login
├── dashboard.php                # Main inventory dashboard (sorting, bulk actions, print labels)
├── add_item.php                 # Add & edit components (AI identify + AJAX submit + enrichment)
├── item_details.php             # Single component detail + image gallery + print label button
├── delete_item.php              # Image-aware deletion handler
├── bulk_action.php              # Bulk operations handler (category/status/location/delete/CSV)
├── bulk_import.php              # Bulk Import Hub — choose CSV, ZIP, or Image Wizard
├── bulk_import_csv.php          # CSV/TSV import — column auto-map + optional URL enrichment
├── bulk_import_zip.php          # ZIP upload — flat ZIP or subfolder-per-component, AI pipeline
├── bulk_import_folder.php       # Folder-based import — browse a local folder structure via the browser
├── bulk_import_wizard.php       # Image Group Wizard — drag photos, group, AI identify
├── bulk_import_wizard_worker.php# Wizard AI worker — receives images, calls Gemini, inserts item
├── bulk_import_worker.php       # Legacy folder worker — processes ONE folder per call
├── locations.php                # Location Manager — grouped view, QR stickers, manifest links
├── container_manifest.php       # Per-location manifest — live table + B&W printable sheet
├── print_labels.php             # Bulk QR label printer — small/medium/large Avery sizes
├── enrich_api.php               # Web scraper — fetches & caches product documentation
├── projects.php                 # Creative Engine — AI project discovery (cached in DB)
├── project_blueprint.php        # AI-generated build guides
├── chat.php                     # Lab Assistant chat UI
├── chat_api.php                 # Chat backend — injects inventory + enrichment context
├── settings.php                 # User Settings hub — Language, Personalization (logo upload), Change Password, AI config
├── site_config.php              # Shared branding loader — fetches lab_name, lab_tagline, lab_mini_tagline, lab_logo_url once per request
├── identify_api.php             # AI vision endpoint (used by add_item.php)
├── ai_helper.php                # Central AI proxy — call_ai_api() + enrichment context builder
├── image_helper.php             # Image processing — imagecreatefromstring, resize full+thumb (PHP 8.5 compatible)
├── db.php                       # PDO database connection (git-ignored, copy from db.php.example)
├── db.php.example               # Safe credential template — copy to db.php and fill in your values
├── schema.sql                   # Database schema + seed data (incl. lab_name, lab_tagline, lab_mini_tagline, lab_logo_url, lab_password)
├── php.ini                      # PHP upload/memory limits for built-in server
├── .htaccess                    # PHP limits for Apache deployments
├── tailwind.config.js           # Tailwind CSS content scan config
├── package.json                 # npm scripts: build:css, watch:css, validate:i18n
├── CHANGELOG.md                 # Changelog — all releases and unreleased work
├── CONTRIBUTING.md              # Contributor guide — setup, PR process, code style
├── SECURITY.md                  # Security policy and vulnerability reporting
├── DEPLOYMENT.md                # Full deployment guide — VPS, shared hosting, migration
├── assets/app.css               # Global stylesheet — Tailwind base + theme tokens + WCAG light-mode + RTL overrides
├── assets/input.css             # Tailwind source file — compiled to app.css via npm run build:css
├── assets/i18n.js               # Localisation engine — async locale loader, t(), RTL sidebar patching, BiDi isolation
├── assets/locales/en.json       # English locale — 142 translation keys across 9 namespaces
├── assets/locales/he.json       # Hebrew locale — 142 keys, full RTL support
├── assets/locales/es.json       # Spanish locale — 142 keys
├── assets/locales/uk.json       # Ukrainian locale — 142 keys
├── contrast_audit.js            # Dev utility — audits all page colours against WCAG 2.1 AA ratios
├── tests/pre_merge_check.js     # Pre-merge test suite — 47 automated checks covering i18n, RTL CSS, locale parity
├── tests/user_settings_check.js # User Settings QA suite — 109 automated checks: logo upload, personalization fields, bcrypt security, dynamic branding, logout endpoint, HTTP smoke tests
├── uploads/                     # Component photos (auto-created, git-ignored)
├── uploads/logo/                # Lab logo uploads (auto-created, git-ignored)
└── docs/                        # Original design & phase documentation
```

> **Single-File Mandate:** Every page contains its own HTML, CSS, and JS in one file to keep the architecture slim and portable.

---

## 🗺 Detailed Code Map

| File | Role / Purpose |
|------|----------------|
| `index.php` | Login page with session-based password gate. |
| `db.php` | PDO connection wrapper — handles setup redirect and loads configuration. |
| `install/index.php` | Web-based WordPress-like Setup Wizard. |
| `package.php` | Production packaging script (creates `diy-inventory.zip`). |
| `ai_helper.php` | Central AI proxy: `call_ai_api($prompt, $images)` + `build_enrichment_context()`. |
| `dashboard.php` | Inventory list with search, filter, column sorting, bulk actions, and statistics. |
| `add_item.php` | Add/Edit form + AI Auto-Identify drag-drop zone. |
| `image_helper.php` | `process_image()` — resizes uploads using PHP GD (PHP 8.5 compatible). |
| `item_details.php` | Single component view with interactive image gallery and "Enrich from Web" button. |
| `delete_item.php` | Deletes database records + physical image files. |
| `bulk_action.php` | Handles bulk actions: bulk categories, status, location, delete, and CSV export. |
| `enrich_api.php` | Scrapes a product/datasheet URL and caches plain-text content for AI prompts. |
| `identify_api.php` | API endpoint called by the Auto-Identify client. |
| `bulk_import_folder.php` | Folder-based import — browse server-side directory paths. |
| `settings.php` | **User Settings** hub — Language, Personalization, Change Password, and AI Configuration. |
| `logout.php` | Teardown PHP session cookies and redirect to the login screen. |
| `site_config.php` | Shared branding loader — reads configuration keys from database once per request. |
| `projects.php` | Creative Engine — sends inventory + enrichment context to AI, renders project cards. |
| `project_blueprint.php` | Generates and renders build guides for selected projects. |
| `chat.php` | Lab Assistant chat UI (AJAX polling). |
| `chat_api.php` | Chat backend — injects inventory and scraped data context. |
| `schema.sql` | Database schema + seed data (default settings, categories, default password hash). |
| `php.ini` | Raises PHP upload limits (`25M`) for built-in server. |
| `.htaccess` | Overrides PHP upload limits for Apache-based hosts. |
| `assets/app.css` | Global stylesheet (Tailwind compilation output + custom design tokens). |
| `assets/i18n.js` | Zero-dependency client-side translation engine (supports async loading and RTL layout patching). |
| `assets/locales/en.json` | English locale — translation keys across namespaces. |
| `assets/locales/he.json` | Hebrew locale — complete Hebrew translation keys with RTL mappings. |
| `assets/locales/es.json` | Spanish locale — complete translation keys. |
| `assets/locales/uk.json` | Ukrainian locale — complete translation keys. |
| `contrast_audit.js` | Development utility measuring page color contrast against WCAG 2.1 AA. |
| `tests/pre_merge_check.js` | Pre-merge test suite — translation, locale parity, and RTL styling checks. |
| `tests/user_settings_check.js` | QA test suite — automated setting modification, bcrypt, logo uploading, and HTTP smoke tests. |
| `tests/install_validation_test.php` | Automated Setup Wizard validation suite checking environment rules and masking. |
| `uploads/` | Auto-created storage directory for component photos. |
| `uploads/logo/` | Auto-created storage directory for uploaded lab branding logos. |
| `db.php.example` | Safe template containing database configuration placeholders. |

---

⬅ **[Back to README](../README.md)**
