# Contributing to DIY Lab Inventory

Thank you for your interest! This is a personal open-source project — contributions of all sizes are welcome.

---

## 🐛 Reporting Bugs

1. Search [existing issues](https://github.com/Philusha1983/diy-inventory/issues) first
2. If not found, open a **Bug Report** using the issue template
3. Include: PHP version, MySQL version, browser, steps to reproduce, and any error output from the PHP server terminal

## 💡 Suggesting Features

Open a **Feature Request** issue. The project follows a "single-file mandate" for UI pages — if your idea fits that pattern it's much more likely to be merged quickly.

## 🔧 Setting Up a Dev Environment

```bash
# 1. Clone the repo
git clone https://github.com/Philusha1983/diy-inventory.git
cd diy-inventory

# 2. Set up the database
#    Create a database called diy_lab_db in phpMyAdmin or MySQL CLI, then:
mysql -u root -p diy_lab_db < schema.sql

# 3. Configure database credentials
cp db.php.example db.php
# Edit db.php with your MySQL host/user/pass

# 4. Start the PHP built-in server
php -c php.ini -S localhost:8080

# 5. Open http://localhost:8080 — default password is 1234 (change in index.php)

# Optional — rebuild Tailwind CSS (only needed if you change HTML classes)
npm install
npm run build:css
```

## 📐 Code Style

- **Single-File Mandate**: Each page (`dashboard.php`, `chat.php`, etc.) keeps its own HTML, CSS `<style>`, and `<script>` in one file. Do not split into separate CSS/JS files.
- **PHP**: Follow PSR-2 naming conventions. All DB queries must use PDO prepared statements — never raw string interpolation.
- **JavaScript**: Vanilla JS only — no jQuery or extra frameworks. `fetch()` + `async/await` for AJAX.
- **CSS**: Tailwind utility classes for layout; inline `<style>` blocks for custom animations and print media queries.
- **Security**: Always `htmlspecialchars()` output, validate uploads by MIME type + extension, never expose raw PDO errors.

## 🔀 Submitting a Pull Request

1. Fork the repo and create a branch: `git checkout -b feat/my-feature`
2. Make your changes — keep PRs focused on a single concern
3. Test locally with a real MySQL database (not SQLite)
4. Ensure PHP shows no warnings: `php -l yourfile.php`
5. Open a PR against `main` with a clear description of what changed and why

## 📋 Areas Most Wanted

- Multi-user / multi-lab support (separate tables per user)
- Location hierarchy (Area → Shelf → Container)
- Dark/light theme toggle
- Mobile-responsive improvements
- Scheduled cron-based enrichment

Please open an issue to discuss major changes **before** building them — saves everyone time.
