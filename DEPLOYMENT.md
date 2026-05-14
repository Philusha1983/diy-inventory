# 🌐 Deployment Guide — DIY Lab Inventory System

> **Three paths covered:**
> - [Path A — Fresh Install on a VPS](#-path-a--fresh-install-on-a-web-server) (full control, root access)
> - [Path B — Migrate Local → VPS](#-path-b--migrate-from-local-to-web-server) (move existing data to a VPS)
> - [Path C — Shared Hosting (DirectAdmin / cPanel)](#-path-c--shared-hosting-directadmin--cpanel) (no root access, standard web hosting)

---

## 📋 Prerequisites & Recommended Providers

### Minimum Server Requirements

| Resource | Minimum | Recommended |
|----------|---------|-------------|
| **OS** | Ubuntu 22.04 LTS | Ubuntu 24.04 LTS |
| **RAM** | 512 MB | 1 GB |
| **Disk** | 10 GB | 20 GB |
| **PHP** | 8.0 | 8.2+ |
| **MySQL** | 8.0 | 8.0+ |

### Recommended VPS Providers

| Provider | Entry Plan | Notes |
|----------|-----------|-------|
| [DigitalOcean](https://digitalocean.com) | $6/month (1GB RAM) | Simple UI, great docs |
| [Hetzner](https://hetzner.com) | €4/month (2GB RAM) | Best value in Europe |
| [Linode (Akamai)](https://linode.com) | $5/month (1GB RAM) | Reliable, good support |
| [Vultr](https://vultr.com) | $6/month (1GB RAM) | Fast global CDN |

> For a personal lab inventory, the smallest available plan (512MB–1GB RAM) is more than sufficient.

---

## 🆕 Path A — Fresh Install on a Web Server

### Step 1 — Create and Connect to Your VPS

After creating a Ubuntu 22.04/24.04 VPS with your chosen provider:

```bash
# Connect via SSH (replace with your server IP)
ssh root@YOUR_SERVER_IP
```

> **Tip:** Add your SSH key during VPS creation to avoid password prompts.

---

### Step 2 — Update the System

```bash
apt update && apt upgrade -y
```

---

### Step 3 — Install the LAMP Stack

```bash
# Install Apache, MySQL, PHP and required extensions
apt install -y apache2 mysql-server php php-mysql php-curl php-json php-mbstring php-xml libapache2-mod-php

# Enable Apache mod_rewrite (needed for .htaccess)
a2enmod rewrite

# Start and enable services
systemctl start apache2 mysql
systemctl enable apache2 mysql
```

Verify installation:
```bash
php --version      # Should show PHP 8.x
mysql --version    # Should show MySQL 8.x
apache2 -v         # Should show Apache 2.4.x
```

---

### Step 4 — Secure MySQL

```bash
mysql_secure_installation
```

Follow the prompts:
- Set a **strong root password** — note it down
- Remove anonymous users → **Y**
- Disallow root login remotely → **Y**
- Remove test database → **Y**
- Reload privilege tables → **Y**

---

### Step 5 — Create the Database and User

```bash
mysql -u root -p
```

Inside MySQL:
```sql
-- Create the database
CREATE DATABASE diy_lab_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create a dedicated app user (don't use root for the app)
CREATE USER 'diylab_user'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON diy_lab_db.* TO 'diylab_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

> Replace `STRONG_PASSWORD_HERE` with a real password (mix of letters, numbers, symbols).

---

### Step 6 — Upload the Application Files

**Option A — From GitHub (if you've pushed the repo):**
```bash
cd /var/www/html
git clone https://github.com/YOUR_USERNAME/diy-inventory.git diy-lab
```

**Option B — From your local machine via SCP:**
```bash
# Run this on your LOCAL Mac terminal (not the server)
scp -r "/Users/philipsl/Desktop/diy inventory/" root@YOUR_SERVER_IP:/var/www/html/diy-lab
```

**Option C — rsync (faster for large uploads):**
```bash
rsync -avz --exclude 'uploads/*' --exclude '.git' \
  "/Users/philipsl/Desktop/diy inventory/" \
  root@YOUR_SERVER_IP:/var/www/html/diy-lab/
```

> The `--exclude 'uploads/*'` flag skips local component photos — you'll upload those separately if migrating.

---

### Step 7 — Configure `db.php`

On the **server**, edit the database credentials:
```bash
nano /var/www/html/diy-lab/db.php
```

Update to match your server setup:
```php
<?php
$host = '127.0.0.1';
$db   = 'diy_lab_db';
$user = 'diylab_user';         // the user you created in Step 5
$pass = 'STRONG_PASSWORD_HERE'; // your chosen password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
```

---

### Step 8 — Import the Database Schema

```bash
mysql -u diylab_user -p diy_lab_db < /var/www/html/diy-lab/schema.sql
```

---

### Step 9 — Set Correct File Permissions

```bash
# Give Apache ownership
chown -R www-data:www-data /var/www/html/diy-lab

# Directories need execute permission
find /var/www/html/diy-lab -type d -exec chmod 755 {} \;

# Files need read permission
find /var/www/html/diy-lab -type f -exec chmod 644 {} \;

# uploads/ needs to be writable by Apache
chmod 775 /var/www/html/diy-lab/uploads
```

---

### Step 10 — Configure Apache Virtual Host

Create a new site configuration:
```bash
nano /etc/apache2/sites-available/diy-lab.conf
```

Paste the following (replace `yourdomain.com` with your actual domain or server IP):
```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    DocumentRoot /var/www/html/diy-lab

    <Directory /var/www/html/diy-lab>
        AllowOverride All
        Require all granted
    </Directory>

    # Block direct access to sensitive helper files
    <FilesMatch "^(db|ai_helper|chat_api|identify_api|delete_item)\.php$">
        Require all denied
    </FilesMatch>

    ErrorLog ${APACHE_LOG_DIR}/diy-lab-error.log
    CustomLog ${APACHE_LOG_DIR}/diy-lab-access.log combined
</VirtualHost>
```

Enable the site and disable the default:
```bash
a2ensite diy-lab.conf
a2dissite 000-default.conf
systemctl reload apache2
```

---

### Step 11 — Enable HTTPS with Let's Encrypt (Free SSL)

> ⚠️ HTTPS is **mandatory** for a public server — it protects your API key and session cookie in transit.

```bash
# Install Certbot
apt install -y certbot python3-certbot-apache

# Obtain and install SSL certificate (replace with your domain)
certbot --apache -d yourdomain.com -d www.yourdomain.com
```

Follow the prompts. Certbot will:
- Verify domain ownership automatically
- Install the certificate
- Configure Apache to redirect HTTP → HTTPS
- Set up auto-renewal (via systemd timer)

Test auto-renewal:
```bash
certbot renew --dry-run
```

---

### Step 12 — Raise PHP Upload Limits for Apache

Edit the PHP configuration:
```bash
nano /etc/php/8.2/apache2/php.ini   # Adjust version number if needed
```

Find and update these values:
```ini
upload_max_filesize = 25M
post_max_size = 30M
memory_limit = 256M
max_execution_time = 120
```

Restart Apache to apply:
```bash
systemctl restart apache2
```

---

### Step 13 — Configure Your API Key

Open the app in your browser:
```
https://yourdomain.com/settings.php
```

1. Select **Gemini** or **OpenAI**
2. Paste your API key
3. Click **Save Configuration**

---

### ✅ Fresh Install Checklist

- [ ] Server created and SSH accessible
- [ ] Apache, MySQL, PHP installed
- [ ] Database and app user created
- [ ] App files uploaded
- [ ] `db.php` updated with correct credentials
- [ ] Schema imported
- [ ] File permissions set
- [ ] Virtual host configured and enabled
- [ ] HTTPS certificate installed
- [ ] PHP upload limits raised
- [ ] API key saved via UI
- [ ] Login with password `1234` works
- [ ] **Change the default password** in `index.php`

---

---

## 🚚 Path B — Migrate from Local to Web Server

This path assumes you have a working local instance and want to move it to a live server while keeping all your existing data and component photos.

### Step 1 — Prepare the Server

Follow **Path A Steps 1–5** to set up Apache, MySQL, and create the database/user on the new server.

---

### Step 2 — Export Your Local Database

On your **local Mac**:
```bash
# Export the full database (data + structure)
mysqldump -u root diy_lab_db > ~/Desktop/diy_lab_backup.sql

# Verify the file was created
ls -lh ~/Desktop/diy_lab_backup.sql
```

---

### Step 3 — Export Your Component Photos

```bash
# Create a compressed archive of the uploads folder
cd "/Users/philipsl/Desktop/diy inventory"
tar -czf ~/Desktop/diy_lab_uploads.tar.gz uploads/

# Check the archive size
ls -lh ~/Desktop/diy_lab_uploads.tar.gz
```

---

### Step 4 — Transfer Everything to the Server

```bash
# Upload the application files (run on your local Mac)
rsync -avz --exclude '.git' --exclude 'php.ini' \
  "/Users/philipsl/Desktop/diy inventory/" \
  root@YOUR_SERVER_IP:/var/www/html/diy-lab/

# Upload the database dump
scp ~/Desktop/diy_lab_backup.sql root@YOUR_SERVER_IP:/tmp/

# Upload the photos archive
scp ~/Desktop/diy_lab_uploads.tar.gz root@YOUR_SERVER_IP:/tmp/
```

---

### Step 5 — Import Data on the Server

SSH into the server, then:

```bash
# Import the database
mysql -u diylab_user -p diy_lab_db < /tmp/diy_lab_backup.sql

# Extract photos to the uploads directory
cd /var/www/html/diy-lab
tar -xzf /tmp/diy_lab_uploads.tar.gz

# Clean up temp files
rm /tmp/diy_lab_backup.sql /tmp/diy_lab_uploads.tar.gz
```

---

### Step 6 — Update `db.php` on the Server

```bash
nano /var/www/html/diy-lab/db.php
```

Update credentials to match the server's MySQL user:
```php
$host = '127.0.0.1';
$db   = 'diy_lab_db';
$user = 'diylab_user';
$pass = 'STRONG_PASSWORD_HERE';
```

---

### Step 7 — Set Permissions

```bash
chown -R www-data:www-data /var/www/html/diy-lab
find /var/www/html/diy-lab -type d -exec chmod 755 {} \;
find /var/www/html/diy-lab -type f -exec chmod 644 {} \;
chmod 775 /var/www/html/diy-lab/uploads
```

---

### Step 8 — Configure Apache and HTTPS

Follow **Path A Steps 10–12** to set up the virtual host, enable HTTPS, and raise PHP upload limits.

---

### Step 9 — Verify the Migration

Open the app in your browser and confirm:

| Check | Expected result |
|-------|----------------|
| Login page loads | ✅ Dark login screen |
| Dashboard shows your items | ✅ All components from local DB present |
| Component detail pages show photos | ✅ Images load from `uploads/` |
| AI Settings shows saved provider | ✅ Key was migrated with the DB |
| Auto-Identify works | ✅ Upload a photo and identify |
| Lab Assistant chat works | ✅ AI responds with inventory context |

---

### ✅ Migration Checklist

- [ ] Server set up (Apache, MySQL, PHP)
- [ ] Database created with app user
- [ ] Local DB exported and imported on server
- [ ] Photos exported and extracted on server
- [ ] App files uploaded via rsync
- [ ] `db.php` credentials updated for server
- [ ] File permissions corrected
- [ ] Virtual host configured
- [ ] HTTPS certificate installed
- [ ] PHP upload limits raised
- [ ] All inventory items visible on server
- [ ] Photos loading correctly
- [ ] All 4 AI workflows tested
- [ ] Default password changed

---

## 🖥 Path C — Shared Hosting (DirectAdmin / cPanel)

This path covers deploying on a **regular shared web hosting** account managed by a control panel like **DirectAdmin**, **cPanel**, or **Plesk**. You don’t have root/SSH access — everything is done through the panel UI and FTP.

> **Compatibility note:** Your DirectAdmin panel must run **PHP 8.0 or newer**. The app uses `str_starts_with()` and named arguments that don’t exist in PHP 7.x. **Check and upgrade your PHP version first (Step 1).**

---

### Step 1 — Set PHP Version to 8.0+ in DirectAdmin

1. Log in to your **DirectAdmin** panel
2. Click **"PHP Settings"** (under Account Manager)
3. In the **PHP Version** dropdown, select **PHP 8.1** or **PHP 8.2**
4. Click **Save**

> **cPanel users:** Go to **"MultiPHP Manager"** or **"Select PHP Version"** → choose PHP 8.1 or 8.2.

> ⚠️ If PHP 8.x is not available, contact your hosting provider and ask them to add it. Most modern hosts offer it.

---

### Step 2 — Create a MySQL Database

In DirectAdmin:
1. Click **"MySQL Management"** (or **"Databases"** in cPanel)
2. Click **"Create new database"**
3. Fill in:
   - **Database name:** `diy_lab_db` (DirectAdmin will prefix it with your account name, e.g. `username_diy_lab_db`)
   - **Username:** `diy_user`
   - **Password:** choose a strong password
4. Click **Create** — note down the **full database name**, **username**, and **password**

---

### Step 3 — Import the Database Schema via phpMyAdmin

1. In DirectAdmin, click **"phpMyAdmin"** (under Extra Features)
2. Select your newly created database from the left sidebar
3. Click the **"Import"** tab at the top
4. Click **"Choose File"** and select `schema.sql` from the project folder
5. Click **"Go"** — you should see a success message

If migrating from your local instance (with existing data), use your exported `diy_lab_backup.sql` instead of `schema.sql`.

---

### Step 4 — Upload Application Files via FTP

**Get your FTP credentials** from DirectAdmin under **"FTP Management"**.

**Recommended FTP clients:**
- [FileZilla](https://filezilla-project.org/) (free, Windows/Mac/Linux)
- [Cyberduck](https://cyberduck.io/) (free, Mac)

**Connection settings:**
| Field | Value |
|-------|-------|
| Host | `yourdomain.com` or your server IP |
| Username | Your FTP username |
| Password | Your FTP password |
| Port | `21` (FTP) or `22` (SFTP if available) |

**What to upload:**
- Upload **all project files** to the `public_html/` folder (or a subfolder like `public_html/diy-lab/`)
- Do **not** upload `php.ini` — it’s only for the local PHP built-in server
- The `uploads/` folder must exist — create it manually in the File Manager if needed

> **Subfolder install:** If you upload to `public_html/diy-lab/`, the app will be at `https://yourdomain.com/diy-lab/`. If you upload directly into `public_html/`, it will be at `https://yourdomain.com/`.

---

### Step 5 — Update `db.php` with Hosting Credentials

Edit `db.php` **before** uploading (on your local machine), or edit it via DirectAdmin’s **File Manager** after upload:

```php
<?php
$host = 'localhost';              // Almost always 'localhost' on shared hosting
$db   = 'username_diy_lab_db';   // Full DB name with hosting prefix
$user = 'username_diy_user';     // Full DB username with hosting prefix
$pass = 'YOUR_DB_PASSWORD';      // The password you set in Step 2
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
```

> **Important:** On shared hosting, DirectAdmin/cPanel **automatically prefixes** both the database name and the username with your account name. Always copy the exact values shown in the panel — don’t guess.

---

### Step 6 — Override PHP Upload Limits via .htaccess

On shared hosting you can’t edit the main `php.ini`, but you can override specific values using `.htaccess`. The project already includes a `.htaccess` file with the correct settings — verify it contains:

```apache
php_value upload_max_filesize 25M
php_value post_max_size 30M
php_value memory_limit 256M
php_value max_execution_time 120
```

If your host **does not allow** `php_value` in `.htaccess` (some restrict this), try a `php.ini` file in `public_html/`:

```ini
upload_max_filesize = 25M
post_max_size = 30M
memory_limit = 256M
max_execution_time = 120
```

> If neither works, contact your hosting support and ask them to raise `upload_max_filesize` to 25M for your account.

---

### Step 7 — Set the `uploads/` Folder Permissions

In DirectAdmin, use the **File Manager**:
1. Navigate to `public_html/diy-lab/uploads/`
2. Right-click the folder → **"Change Permissions"** (or **"chmod"**)
3. Set to **755** (owner: read/write/execute, group/others: read/execute)

> If images still fail to save, try **775**. Never set it to 777 on a public server.

---

### Step 8 — Enable HTTPS (SSL)

Most hosting providers offer a free **Let’s Encrypt SSL** directly from the panel:

**In DirectAdmin:**
1. Click **"SSL Certificates"** (under Account Manager)
2. Select **"Let’s Encrypt"**
3. Tick your domain and `www.` subdomain
4. Click **Save**
5. Wait 1–2 minutes for the certificate to be issued

**In cPanel:**
1. Go to **"SSL/TLS"** → **"Let’s Encrypt SSL"**
2. Select your domain → click **"Install"**

---

### Step 9 — Force HTTPS Redirect

Add these lines to the **top** of your `.htaccess` file (before the `php_value` lines):

```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

### Step 10 — Configure Your API Key

Open the app in your browser:
```
https://yourdomain.com/settings.php
```

1. Select **Gemini** or **OpenAI**
2. Paste your API key
3. Click **Save Configuration**

---

### Step 11 — Migrating Existing Data to Shared Hosting

If moving from your local instance:

**Export local database:**
```bash
mysqldump -u root diy_lab_db > ~/Desktop/diy_lab_backup.sql
```

**Import via phpMyAdmin:**
1. Open phpMyAdmin → select your database
2. Click **Import** → upload `diy_lab_backup.sql` → click **Go**

> phpMyAdmin has a default upload limit (usually 8–50MB). If your export is larger, compress it first: `gzip diy_lab_backup.sql` and import the `.sql.gz` file.

**Upload photos:**
- Zip your `uploads/` folder locally
- Upload the zip via FTP or File Manager
- Extract it inside `public_html/diy-lab/`

---

### ✅ Shared Hosting Checklist

- [ ] PHP version set to 8.1 or 8.2 in panel
- [ ] MySQL database and user created
- [ ] Schema (or backup) imported via phpMyAdmin
- [ ] App files uploaded via FTP (excluding local `php.ini`)
- [ ] `db.php` updated with correct hosting credentials (with prefix)
- [ ] `.htaccess` includes PHP upload limit overrides
- [ ] `uploads/` folder permission set to 755
- [ ] SSL certificate issued and HTTPS redirect active
- [ ] API key saved via `settings.php`
- [ ] Login works at `https://yourdomain.com`
- [ ] All 4 AI workflows tested
- [ ] Default password `1234` changed

---

### ⚠️ Known Limitations on Shared Hosting

| Limitation | Impact | Workaround |
|-----------|--------|------------|
| No SSH / root access | Can’t use `mysqldump` from server | Use phpMyAdmin export/import |
| `php_value` may be blocked | Upload limits can’t be raised via `.htaccess` | Use per-directory `php.ini` or contact host |
| Execution time cap (30–60s) | Large AI requests may time out | Use Gemini — it’s faster than OpenAI for this use case |
| Shared IP / outbound restrictions | Some hosts block outbound cURL calls | Contact host to confirm outbound HTTPS is allowed |
| No cron access (some hosts) | Can’t automate DB backups | Use phpMyAdmin manual export or a backup plugin |

---

## 🔒 Post-Deployment Security Hardening

After going live, apply these additional protections:

### 1. Change the Default Password

Edit `index.php` on the server and replace the hardcoded password check:
```bash
nano /var/www/html/diy-lab/index.php
```

Find the line:
```php
if ($_POST['password'] === '1234') {
```

Replace with a hashed password:
```php
// Generate this hash once on your machine: php -r "echo password_hash('YOUR_NEW_PASSWORD', PASSWORD_DEFAULT);"
$stored_hash = '$2y$10$REPLACE_WITH_YOUR_GENERATED_HASH';
if (password_verify($_POST['password'], $stored_hash)) {
```

### 2. Protect `db.php` from Direct Web Access

Your `.htaccess` already includes this, but verify it's working:
```bash
curl -I https://yourdomain.com/db.php
# Should return: 403 Forbidden
```

### 3. Keep PHP and System Updated

```bash
# Set up unattended security updates
apt install -y unattended-upgrades
dpkg-reconfigure --priority=low unattended-upgrades
```

### 4. Configure a Firewall

```bash
# Allow only SSH, HTTP, HTTPS
ufw allow OpenSSH
ufw allow 'Apache Full'
ufw enable
ufw status
```

### 5. Set Up Database Backups

Create a daily backup script:
```bash
nano /etc/cron.daily/diy-lab-backup
```

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/diy-lab"
mkdir -p "$BACKUP_DIR"
mysqldump -u diylab_user -pSTRONG_PASSWORD_HERE diy_lab_db \
  | gzip > "$BACKUP_DIR/db_$(date +%Y%m%d).sql.gz"
# Keep only last 30 days
find "$BACKUP_DIR" -name "*.sql.gz" -mtime +30 -delete
```

```bash
chmod +x /etc/cron.daily/diy-lab-backup
```

---

## 🐛 Common Deployment Issues

| Problem | Cause | Fix |
|---------|-------|-----|
| **403 Forbidden** on all pages | Apache can't read files | `chown -R www-data:www-data /var/www/html/diy-lab` |
| **500 Internal Server Error** | PHP error or `.htaccess` problem | Check `/var/log/apache2/diy-lab-error.log` |
| **"Connection failed"** | Wrong DB credentials in `db.php` | Verify user/pass in `db.php` matches MySQL user |
| **Images not loading** | `uploads/` not writable | `chmod 775 uploads/` and `chown www-data uploads/` |
| **AI returns no response** | cURL not installed | `apt install php-curl && systemctl restart apache2` |
| **Upload fails silently** | PHP limits not updated | Edit `/etc/php/8.x/apache2/php.ini` and restart Apache |
| **HTTPS not working** | Certificate not issued | Ensure domain DNS points to server IP before running Certbot |
| **`db.php` accessible from web** | `.htaccess` not loaded | Ensure `AllowOverride All` in virtual host config |

---

## 📊 Architecture Diagram

```
User Browser (HTTPS)
        │
        ▼
  Apache 2 Web Server  (:443)
        │
        ├── /var/www/html/diy-lab/
        │     ├── index.php        ← Session auth gate
        │     ├── dashboard.php
        │     ├── add_item.php     ──► identify_api.php ──► ai_helper.php
        │     ├── chat.php         ──► chat_api.php     ──► ai_helper.php
        │     ├── projects.php     ──────────────────────► ai_helper.php
        │     └── uploads/         ← Component photos
        │
        ├── MySQL 8  (localhost:3306)
        │     └── diy_lab_db
        │           ├── inventory  ← All components
        │           └── settings   ← API key (encrypted at rest by MySQL)
        │
        └── ai_helper.php  ──► Gemini API (api.generativelanguage.googleapis.com)
                           └─► OpenAI API (api.openai.com)
```

---

## 🔗 Quick Reference

| Task | Command |
|------|---------|
| Restart Apache | `systemctl restart apache2` |
| Reload Apache (no downtime) | `systemctl reload apache2` |
| Check Apache errors | `tail -f /var/log/apache2/diy-lab-error.log` |
| Check Apache access log | `tail -f /var/log/apache2/diy-lab-access.log` |
| Restart MySQL | `systemctl restart mysql` |
| Renew SSL certificate | `certbot renew` |
| Manual DB backup | `mysqldump -u diylab_user -p diy_lab_db > backup.sql` |
| Check PHP version | `php --version` |
| Check open ports | `ufw status` |

---

*Back to [README.md](README.md)*
