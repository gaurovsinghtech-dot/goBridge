# Growbridge Connect — cPanel Production Deployment Guide

This guide details the complete production deployment procedures for Growbridge Connect on cPanel shared hosting and cPanel VPS environments.

---

## 1. System Requirements

- **Web Server:** Apache 2.4+ with `mod_rewrite`, `mod_headers`, and `mod_ssl`
- **PHP Version:** PHP 8.2 or PHP 8.3 (Selected via cPanel MultiPHP Manager)
- **PHP Extensions Required:**
  - `pdo_mysql` or `pdo_sqlite`
  - `mbstring`
  - `openssl`
  - `curl`
  - `json`
  - `fileinfo`
  - `gd` or `imagick`
  - `zip`
  - `xml` / `dom`
  - `bcmath`
  - `ctype`
  - `tokenizer`
- **Database:** MySQL 8.0+ or MariaDB 10.5+ with `utf8mb4` encoding
- **Object Storage:** AWS S3 (Production bucket configured via Admin Panel)

---

## 2. PHP INI Configuration (cPanel MultiPHP INI Editor)

In **cPanel → MultiPHP INI Editor → Basic Mode (or Home Directory)**, set the following recommended values:

```ini
max_execution_time = 300
max_input_time = 300
memory_limit = 256M
post_max_size = 64M
upload_max_filesize = 64M
allow_url_fopen = On
date.timezone = UTC
```

---

## 3. Directory Structure & DocumentRoot Setup

### Method A (Recommended for Addon Domains / Subdomains / cPanel with Custom DocumentRoot)

Point the domain's **Document Root** directly to the `/public` subfolder:

```
/home/username/growbridge/
├── app/
├── bootstrap/
├── config/
├── database/
├── public/               <── Set DocumentRoot here!
│   ├── index.php
│   ├── .htaccess
│   └── build/
├── resources/
├── routes/
├── storage/
├── vendor/
├── .env
└── artisan
```

1. Upload the project ZIP to `/home/username/growbridge` (outside `public_html`).
2. Extract the archive.
3. In **cPanel → Domains**, edit the Document Root to point to `/home/username/growbridge/public`.

---

### Method B (For Shared Hosts Restricting DocumentRoot to `public_html`)

If your hosting provider restricts the primary domain to `public_html/`:

1. Upload all project files to `/home/username/growbridge_core`.
2. Move the contents of `/home/username/growbridge_core/public` into `/home/username/public_html`.
3. In `/home/username/public_html/index.php`, update the paths to require from `../growbridge_core/`:
   ```php
   require __DIR__.'/../growbridge_core/vendor/autoload.php';
   $app = require_once __DIR__.'/../growbridge_core/bootstrap/app.php';
   ```
4. Or alternatively, use the included root [`.htaccess`](file:///E:/Growbridge%20connetc/.htaccess) inside `public_html/` which automatically redirects all traffic to `/public` and forbids direct access to `.env`, `composer.json`, `artisan`, `storage/logs`, and `vendor`.

---

## 4. Environment Configuration (`.env`)

Create your `.env` file in the project root:

```ini
APP_NAME="Growbridge Connect"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cpaneluser_growbridge
DB_USERNAME=cpaneluser_dbuser
DB_PASSWORD=your_secure_password

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=postmaster@your-domain.com
MAIL_PASSWORD=your_smtp_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=notifications@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"

# Optional AWS S3 (Or configure via Admin -> Integrations -> Storage)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=ap-south-1
AWS_BUCKET=your-private-s3-bucket
```

---

## 5. cPanel Cron Jobs Configuration

In **cPanel → Cron Jobs**, configure the two essential background workers:

### 1. Laravel Task Scheduler (Runs every minute)
```bash
* * * * * cd /home/username/public_html && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

### 2. Queue Worker (Reliable Cron Mode for Shared cPanel)
Standard shared hosting kills long-running daemon workers. Running `queue:work` with `--stop-when-empty` every minute provides 100% reliable execution without daemon termination:
```bash
* * * * * cd /home/username/public_html && /usr/local/bin/php artisan queue:work --stop-when-empty --max-time=55 --memory=128 --tries=3 >> /dev/null 2>&1
```

*(Note: Replace `/home/username/public_html` with your actual project path, and `/usr/local/bin/php` with your specific PHP 8.2+ CLI path if needed).*

---

## 6. Supervisor Configuration (For VPS / Dedicated Servers)

If you have root access and prefer a persistent daemon worker:

Create `/etc/supervisor/conf.d/growbridge-worker.conf`:

```ini
[program:growbridge-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /home/username/growbridge/artisan queue:work --sleep=3 --tries=3 --max-time=3600 --memory=256
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=cpaneluser
numprocs=2
redirect_stderr=true
stdout_logfile=/home/username/growbridge/storage/logs/worker.log
stopwaitsecs=3600
```

Run:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start growbridge-worker:*
```

---

## 7. First-Time Setup Commands

Run via cPanel Terminal or SSH:

```bash
# 1. Generate App Key (if not already set)
php artisan key:generate --force

# 2. Run Database Migrations & Seeders
php artisan migrate --force --seed

# 3. Create Storage Symlink for Public Assets
php artisan storage:link

# 4. Optimize Cache & Routes for Production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 8. Verification & Health Monitoring

Once deployed:
1. Log in to the Super Admin control panel (`/admin`).
2. Navigate to **Admin → System Health** (`/admin/system-health`).
3. Verify that:
   - Database status shows **Healthy** (with latency).
   - AWS S3 status shows **Connected** (or test credentials).
   - Scheduler heartbeat confirms cron execution within the last 5 minutes.
   - Queue pending jobs and failed jobs counters are operational.
4. Click **"Test SMTP Mail"** to verify outbound email delivery.
5. Click **"Run DB Backup"** to test on-demand database archiving.
