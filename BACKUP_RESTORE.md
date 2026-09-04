# GROWBRIDGE CONNECT — BACKUP & DISASTER RECOVERY GUIDE

**Platform:** GROWBRIDGE CONNECT  
**Tagline:** "Connect. Engage. Automate. Grow."  
**Standard:** Enterprise SaaS Data Retention & Zero-Downtime Restoration  

---

## 1. Backup Strategy Overview

Growbridge Connect utilizes a lightweight, reliable backup architecture optimized for standard hosting environments (cPanel, Linux VPS, Cloud Shared Hosting).

### Backup Components:
1. **MySQL Database (`growbridge_db`):** Complete schema, tenant workspaces, contacts, messages, call logs, automation flows, and billing records.
2. **Environment Configuration (`.env`):** Master app encryption key (`APP_KEY`), database credentials, third-party platform credentials.
3. **Uploaded Customer Files (`storage/app/public/`):** Chat media, voice recordings, document knowledge bases, business logos, and avatars.

### Excluded Artifacts (Do NOT backup):
- `vendor/` (re-installable via `composer install`)
- `node_modules/` (re-installable via `npm install`)
- `storage/framework/cache/` (ephemeral runtime cache)
- `storage/framework/sessions/` (ephemeral active sessions)
- `storage/logs/` (revolving logs)

---

## 2. Automated & Manual Backup Procedures

### Method A: Automated Daily Database Backup via Cron
In cPanel **Cron Jobs**, configure a daily backup job at 2:00 AM:
```bash
0 2 * * * mysqldump -u growbridge_usr -p'YOUR_STRONG_PASSWORD' growbridge_db | gzip > /home/username/backups/db_$(date +\%Y\%m\%d_\%H\%M\%S).sql.gz
```

### Method B: cPanel Backup Wizard
1. Log in to your cPanel control panel.
2. Navigate to **Files > Backup Wizard**.
3. Click **Backup > MySQL Databases** and download the `growbridge_db.sql.gz` dump.
4. Click **Home Directory** to download user uploads in `storage/app/public/`.

### Method C: Command-Line Database Export
```bash
mysqldump -u growbridge_usr -p growbridge_db > backup_full_$(date +%F).sql
tar -czf storage_uploads_$(date +%F).tar.gz storage/app/public/ .env
```

---

## 3. Disaster Recovery & Step-by-Step Restoration

Follow this procedure to restore Growbridge Connect to a clean server or recover from accidental data loss:

### Step 1: Restore Database
1. Create a clean MySQL database and user:
   ```bash
   mysql -u root -p -e "CREATE DATABASE growbridge_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```
2. Import the database backup dump:
   ```bash
   gunzip < db_backup.sql.gz | mysql -u growbridge_usr -p growbridge_db
   # OR for uncompressed SQL:
   mysql -u growbridge_usr -p growbridge_db < backup_full.sql
   ```

### Step 2: Restore Files and Environment
1. Extract application files to `public_html/` or subdomain root.
2. Restore the original `.env` file containing the matching `APP_KEY`.
   > **CRITICAL:** The `APP_KEY` must match the key used when credentials and tokens were encrypted; otherwise, stored integration tokens cannot be decrypted.
3. Extract `storage/app/public/` to restore customer attachments and voice recordings.

### Step 3: Verify Permissions
Set standard web server ownership and write permissions:
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### Step 4: Re-link Storage and Rebuild Caches
```bash
php artisan storage:link
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

---

## 4. Disaster Recovery Testing Checklist

Perform a test restore on a staging environment quarterly:
- [ ] Database imported with zero syntax errors.
- [ ] Contacts and multi-tenant workspaces intact.
- [ ] Voice call audio recordings and chat attachments accessible.
- [ ] Integrations decrypt successfully with restored `APP_KEY`.
- [ ] Cron scheduler runs scheduled automation workflows cleanly.
- [ ] Inbound webhooks process without signature verification errors.
