# 📋 Usage Guide

This guide describes how to operate the **DIY Lab Inventory & AI Orchestrator** in your daily maker workflow.

---

## 1. Add Your First Component

* Navigate to **"Add Component"** in the sidebar.
* **Option A (AI auto-identify):** Drag and drop one or more photos of the physical component directly into the drop-zone → click **"Auto-Identify with AI"**. The AI parses the images and populates the name, model, category, and technical specs. Review and edit as necessary, then click Save.
* **Option B (Manual entry):** Manually type the name, model, category, quantity, condition, location, purchase price, product URLs, and custom specifications, then click Save.

---

## 2. Browse and Manage Your Inventory

* The **Dashboard** displays your complete component list in a responsive table.
* **Sort columns**: Click any table header (e.g., Name, Category, Location) to sort ascending or descending. The active sort column will highlight.
* **Search & Filter**: Type queries in the search bar or select dropdown filters to find components instantly.
* **Bulk Actions**: Select multiple components via checkboxes. A floating operations bar will slide up from the bottom, permitting you to:
  * Bulk-change location
  * Bulk-change category
  * Bulk-change condition/status
  * Export selection to CSV
  * Print QR stickers for all selected items
  * Bulk-delete (deletes database records and associated image files automatically)

---

## 3. Creative Engine — Discover Projects

* Click **"Creative Engine"** in the sidebar.
* Click **"Brainstorm Projects"**. The AI analyzes your active inventory stock and returns 5 buildable projects tailored to what you own.
* Each project card includes:
  * Estimated difficulty, domain, and completion time.
  * Which components from your inventory are utilized.
  * Which items are missing, along with quick Amazon and AliExpress purchase links.
* Click **"Generate Blueprint"** on any project to produce a complete step-by-step assembly manual, wiring diagram layout, BOM, and firmware code.
* **Auto-Caching**: Brainstormed project ideas are cached in the database. Caching allows you to navigate away and return to your results instantly at zero API cost. Click **↺ Regenerate** at any time to discover fresh ideas.

---

## 4. Chat with Your Lab Assistant

* Navigate to **"Lab Assistant"** in the sidebar.
* The chatbot is fully aware of your current inventory (and any cached product specifications you have scraped).
* Ask questions like:
  * *"What can I make with my extra Raspberry Pi and some LEDs?"*
  * *"Show me all components stored in Container A"*
  * *"Suggest a wiring guide for a standard SG90 servo motor"*

---

## 5. Bulk Import Hub

Open **Bulk Import** from the dashboard header. Three browser-based import options are available:

### 📊 CSV / Spreadsheet Import
1. Export your existing parts catalog from Google Sheets, Microsoft Excel, or Notion as a `.csv` file.
2. Upload the file. Delimiters (comma, tab, semicolon, pipe) are automatically recognized.
3. Column headers are auto-mapped based on 30+ common aliases (e.g. `qty` ⇄ `quantity`, `bin` ⇄ `location`). Adjust any unmapped columns using the dropdown lists.
4. Check **"Auto-enrich via Product URL"** to optionally scrape pages and query the AI to enrich data during insertion.
5. Click **Import to Inventory**.

### 🗜️ ZIP Upload
1. Package component photos in a standard ZIP archive:
   * **Flat ZIP (one component):** Place all photos in the ZIP root. The component is named after the ZIP.
   * **Subfolder ZIP (many components):** Put each component in its own subfolder, containing photos and a `description.txt`.
2. Upload the ZIP. The server extracts the folders securely and processes the AI import pipeline.

### 🧙 Image Group Wizard
1. Open the Image Wizard.
2. Drag and drop a batch of images into the drop-zone. Each batch becomes a component.
3. Add a textual name hint (e.g., "555 timer") to help guide the AI vision models.
4. Add more batches as needed, and click **"🤖 Import All via AI"**. The wizard processes them sequentially with a rate-limiting buffer.

---

## 6. Location Manager & Container Manifests

### Location Manager (`locations.php`)
* Click **"📍 Locations"** in the sidebar.
* The system clusters your components by their `location` column.
* View aggregate metrics (number of components, physical units) and category charts per container.

### Container Manifest (`container_manifest.php`)
* Click **"📄 Manifest"** on any location card to open the live inventory sheet.
* The manifest lists all components in the container.
* Click **"🖨️ Print Manifest"** to generate a clean A4 printout formatted with a **Verified ☐** checkbox grid, ideal for physical inventory audits.

### QR Code Labels & Sticker Sheets
* Click **"🏷️ QR Sticker"** on a location card, or select components on the dashboard and click **"🏷️ Print Labels"**.
* Choose a layout: Small (Avery 62×29 mm), Medium (Avery 99×57 mm), or Large (Avery 99×93 mm).
* QR codes are fully **self-contained/offline-readable** (storing name, model, quantity, and location as plain text), with a fallback URL for online access.

---

## 7. Web Scraper Data Enrichment

* Open any component detail page and click **"🌐 Enrich from Web"**.
* The server scrapes the raw text content of the product URL or datasheet link, stripping HTML.
* The scraped data (~3,000 characters maximum) is stored in the database.
* The assistant, Creative Engine, and blueprints automatically ingest this cached data in future prompts, dramatically increasing response accuracy.

---

⬅️ **[Back to README](../README.md)**
