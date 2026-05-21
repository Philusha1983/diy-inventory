# ✨ Detailed Features

This page provides an in-depth breakdown of the features and capabilities of the **DIY Lab Inventory & AI Orchestrator**.

---

## Features Matrix

| Feature | Description |
|---------|-------------|
| 🔐 **Secure Login** | Password-protected gate. No user accounts needed for a personal lab. |
| 📦 **Inventory CRUD** | Add, edit, delete components with name, model, category, quantity, condition, specs, location, purchase price, and product/datasheet URLs. |
| 📸 **Multi-Angle Image Upload** | Upload several photos per component. Images are **automatically resized and compressed** on upload (max 1200px, ~100–200 KB). Two versions are stored: a full-res viewer image and a small thumbnail. Saves ~98% storage vs raw phone photos. |
| 🤖 **AI Auto-Identify** | Drag-and-drop photos → AI returns a structured JSON with `name`, `model`, `category`, and `specs`. Fields are auto-filled. |
| 📂 **Flexible Bulk Import** | Three browser-based import methods — no server access needed: **CSV/Spreadsheet** (auto-maps columns, optional AI enrichment via Product URL), **ZIP upload** (flat ZIP = one component; subfolder ZIP = many components; AI identifies from photos), **Image Group Wizard** (drag photos, declare groups, AI identifies each). |
| 🏷️ **Location Manager** | Dedicated `locations.php` dashboard groups all components by location. Shows per-location stats, expandable item lists, and generates a **Container QR sticker** (offline-readable) or a full **printable manifest** for each location. |
| 📄 **QR Code Labels** | Offline-ready QR stickers for individual components (print from item detail or bulk-select on dashboard). QR payload contains Name, Model, Category, Qty, Location as plain text — readable without a network connection. URL appended for one-tap browser open when online. |
| 🖨️ **Print Manifest** | Per-container A4 printout with all items, quantities, and a **Verified ☐** checkbox column for physical stock audits. Strict black-on-white CSS — works on any B&W printer. |
| 🔃 **Column Sorting** | Click any column header (Name, Model, Category, Qty, Status, Location) to sort ascending or descending. Active column highlights in purple. Preserves active search and filter. |
| ☑️ **Bulk Actions** | Select multiple items with checkboxes (per-row + select-all). A floating action bar appears with: **Category change**, **Status change**, **Location change**, **Export CSV**, **Print Labels**, and **Delete** (with image cleanup). |
| 🔗 **Product Enrichment** | Attach a product URL or datasheet URL to any component. Click "Enrich from Web" to scrape and cache the page content. This documentation is automatically injected into AI prompts for richer, more accurate suggestions. |
| 💡 **Creative Engine** | Click **Brainstorm Projects** to have the AI analyse your entire inventory and return 5 tailored project ideas with complexity, duration, skill domain, and missing-part shopping links. Results are **cached in the DB** — navigating away and returning shows the last ideas instantly at zero API cost. Click **Regenerate** any time for fresh suggestions. |
| 📐 **Project Blueprints** | One-click generation of a full technical guide (wiring, BOM, and firmware code) for any suggested project. |
| 💬 **Lab Assistant Chat** | A context-aware chat interface. The AI knows your inventory — including cached product documentation — and answers questions like "what can I build with my extra LEDs?". |
| ⚙️ **User Settings** | A comprehensive settings hub with four ordered sections: **Language** selector, **Personalization** (Lab Name, Tag Line, Mini Tag Line, Logo), **Change Password** (bcrypt-secured), and **AI Configuration** (provider + API key). All settings are persisted in the database — no code editing required. |
| 🎨 **Lab Personalization** | Customise your lab's identity from the UI: set a **Lab Name**, **Tag Line** (login screen subtitle), **Mini Tag Line** (sidebar label), and **Logo** (upload a file or paste a URL). Uploaded logos are centre-cropped to a square and resized to 256×256 px by PHP GD, then stored in `uploads/logo/`. Changes propagate instantly to all pages including the login gate. Default branding falls back to the built-in gradient icon. |
| 🔑 **Secure Password Management** | Change the lab password directly from User Settings — no file editing required. The current password is verified before accepting a change; the new password is hashed with **`PASSWORD_BCRYPT`** and stored in the database. A live "passwords match" hint guides the user during entry. |
| 🌗 **Light / Dark Theme** | Toggle between dark (default) and light mode via the sidebar switch. Preference is persisted in `localStorage` across sessions and page reloads — survives logout. All colours meet **WCAG 2.1 AA** contrast requirements in both themes. |
| 🌍 **Multi-Language UI** | Full internationalisation (i18n) across all pages — switch between **English 🇬🇧**, **Hebrew 🇮🇱 (RTL)**, **Spanish 🇪🇸**, and **Ukrainian 🇺🇦** from the Settings page. Language persists in `localStorage`. Hebrew activates complete RTL layout mirroring (sidebar, margins, flex order, text alignment). Add new languages with a single JSON file. |
| 📱 **Mobile Responsive** | Full hamburger-menu sidebar, card-based inventory view (with checkboxes for bulk selection), and adaptive layouts for phones and tablets. |
| 🧙‍♂️ **Web-based Setup Wizard** | Interactive WordPress-like installation flow checking server prerequisites, testing database credentials asynchronously, auto-creating the database, importing tables, configuring `config.php`, and setting up administrator credentials securely with bcrypt. |
| 📦 **Production Packaging** | Built-in packaging script (`package.php`) that compiles the codebase into a production-ready ZIP archive (`diy-inventory.zip`) while automatically filtering out developer files, testing suites, local configuration, and logs. |

---

⬅️ **[Back to README](../README.md)**
