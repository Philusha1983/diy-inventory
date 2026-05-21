# 🐛 Troubleshooting Guide

This page covers common problems encountered during installation, database setup, image processing, and AI integrations, along with instructions to solve them.

---

## Database Connections

### Symptom: "Connection failed" on load
* **Check MySQL Status:** Verify that the database server is running:
  * **macOS (Homebrew):** `brew services list` or `brew services start mysql`
  * **Linux (Ubuntu):** `sudo systemctl status mysql` or `sudo systemctl start mysql`
* **Verify Credentials:** Open `config.php` and verify the values for `DB_HOST`, `DB_USER`, `DB_PASS`, and `DB_NAME`.
* **Database Existence:** Log into MySQL CLI (`mysql -u root -p`) and run `SHOW DATABASES;` to verify your database exists. If it doesn't, run the setup wizard or execute:
  `CREATE DATABASE diy_lab_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`

---

## AI Auto-Identify & vision Failures

### Symptom: UI shows "❌ Auto-Identify failed"
Check the error message in the red status card. Common issues include:

| Error Message | Potential Cause | How to Fix |
|---------------|-----------------|------------|
| *No API key configured* | The settings key is missing or blank. | Go to **User Settings → AI Configuration** → paste key → Save. |
| *Invalid API key* | The key was typed incorrectly, or has expired. | Re-generate or copy a fresh key from Google AI Studio / OpenAI. |
| *The AI model could not be found* | The Gemini API was not enabled on your Google Cloud Console project. | Open Google Cloud Console, find your project, and enable the **Gemini API**. |
| *API rate limit reached / quota exhausted* | Daily rate limit exceeded. | Wait for quota to reset (midnight Pacific Time), or attach a Google Cloud key with billing enabled. |
| *Photo exceeds server limit* | Uploaded file size is too large. | Reduce resolution or compress the photo before uploading. |
| *No images received* | The browser didn't transfer files correctly. | Re-select or drag files into the zone again. |

---

## General AI Quota & Tier Issues

### Symptom: "Free tier" quota errors occur on a paid account
Google Gemini keys from AI Studio (free) and Google Cloud Console (paid) are identical in structure (starting with `AIza...`).
* **Check project billing:** Navigate to the **[Google Cloud Console](https://console.cloud.google.com)** and verify that your project is attached to a valid billing account.
* **Enable API:** Search the library for **"Gemini API"** and verify it is explicitly enabled.
* **Generate Key:** If you enabled billing *after* creating the key, delete the old key and create a new one to apply changes.

### Symptom: Creative Engine / Brainstorm Projects fails to return ideas
* **Verify Key:** Ensure you have selected a provider and saved a key in User Settings.
* **Timeout / Rate Limits:** The Creative Engine aggregates your entire inventory and requests 5 project cards. This can take 15–40 seconds. Wait 60 seconds and try again if rate limits are hit.
* **Empty Inventory:** If your database has 0 items, the Creative Engine has nothing to evaluate. Add some components first.

---

## Image Uploads and Processing

### Symptom: Images are not saving on upload
* **Directory Permissions:** Ensure the directory is writeable by the web server:
  `mkdir -p uploads uploads/logo && chmod 755 uploads uploads/logo`
* **Server Limit Configuration:** Always run the PHP built-in server with the configuration flag:
  `php -c php.ini -S localhost:8080`
  Without the `-c php.ini` flag, PHP defaults to a `2M` upload limit, which silently drops phone camera photographs (typically 3–8 MB) before they reach the backend script.
* **PHP GD Library:** Ensure the PHP GD extension is installed and active on your system:
  `php -m | grep -i gd` (should yield `gd`).

### Symptom: Upload yields "could not be processed (GD error)"

| Cause | Symptom | How to Fix |
|-------|---------|------------|
| **CMYK Color Profile** | Product photos from Mouser, Amazon, or Digikey fail to process. | GD uses `imagecreatefromstring()` which handles CMYK profiles automatically. Verify your `image_helper.php` is up to date. |
| **HEIC/HEIF Formats** | Raw iPhone photos fail with HEIC messages. | iOS formats are not natively supported by PHP GD. Convert images to JPEG or PNG before uploading (e.g., in macOS Preview: **File → Export → JPEG**). |
| **AVIF Formats** | Modern web screenshots fail. | Save or convert AVIF images to JPEG or PNG first. |
| **Memory Exhaustion** | Large images trigger PHP memory errors. | The built-in `php.ini` sets memory limit to 512 MB. For exceptionally large RAW images, compress them locally first. |

---

## Local Scraper & Web Enrichment

### Symptom: "🌐 Enrich from Web" yields errors or no data

| Symptom | Cause | How to Fix |
|---------|-------|------------|
| *"No URLs saved for this component"* | The component is missing links. | Edit the component, paste a URL in the Product URL or Datasheet fields, and save. |
| *"Network error: ... JSON"* | The enrichment script returned a PHP error. | Verify that cURL is installed and enabled in your PHP build: `php -m | grep curl`. |
| Scraper succeeds but fields are empty | Scraped text is stored in the database cache. | Reload the page. You will see a green "Enriched" badge and a text preview of the cached content. |

---

## PHP Server & Access

### Symptom: PHP built-in server fails to start
* Check if port 8080 is in use by another application:
  `lsof -i :8080`
* Start the server on a different port:
  `php -c php.ini -S localhost:9090`

### Symptom: Can't access the app from a phone on the same Wi-Fi
* **Host Binding:** Ensure you bound the server to `0.0.0.0` (all interfaces), not `localhost`:
  `php -c php.ini -S 0.0.0.0:8080`
* **Find Server IP:** Run `ipconfig getifaddr en0` on macOS to obtain your local network IP (e.g. `192.168.1.50`). Navigate to `http://192.168.1.50:8080` on your phone.
* **Firewall Blocks:** Enable PHP traffic through the macOS firewall:
  **System Settings → Network → Firewall → Options → Allow incoming connections for php**.

---

⬅️ **[Back to README](../README.md)**
