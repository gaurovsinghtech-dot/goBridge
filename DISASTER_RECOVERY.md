# GROWBRIDGE CONNECT — DISASTER RECOVERY & INCIDENT RESPONSE MANUAL

**Platform:** GROWBRIDGE CONNECT  
**Tagline:** "Connect. Engage. Automate. Grow."  
**Standard:** Enterprise SaaS Business Continuity & Disaster Recovery (BC/DR)  

---

## 1. 15-Step Master Restoration Procedure

Follow this exact sequence when recovering from a server failure, database crash, or catastrophic data loss:

```
[1. Identify Failure] ──> [2. Maintenance Mode] ──> [3. Restore Database] ──> [4. Restore Files]
         │
         ▼
[5. Restore .env & APP_KEY] ──> [6. Permissions] ──> [7. Clear/Rebuild Cache] ──> [8. Schema Check]
         │
         ▼
[9. Verify Queues] ──> [10. Verify Cron] ──> [11. Verify Webhooks] ──> [12. Test Auth & API]
         │
         ▼
[13. Test Messaging] ──> [14. Smoke Test] ──> [15. Disable Maintenance Mode (Live)]
```

### Step-by-Step Instructions:

1. **Identify Failure:** Determine whether the incident affects the database, filesystem, web server, or external API gateways.
2. **Put Application in Maintenance Mode:**
   ```bash
   php artisan down --secret="emergency-admin-bypass-token" --render="errors::503"
   ```
3. **Restore Database:**
   ```bash
   gunzip < /home/username/backups/db_backup_latest.sql.gz | mysql -u growbridge_usr -p growbridge_db
   ```
4. **Restore Uploaded Customer Files:**
   ```bash
   tar -xzf /home/username/backups/storage_uploads_latest.tar.gz -C /home/username/public_html/
   ```
5. **Restore `.env` & Verify `APP_KEY`:**
   Ensure the restored `.env` file matches the exact `APP_KEY` used when tenant credentials were encrypted.
6. **Verify Storage Permissions:**
   ```bash
   chmod -R 775 storage bootstrap/cache
   ```
7. **Re-link Storage & Rebuild System Caches:**
   ```bash
   php artisan storage:link
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
8. **Run Pending Migrations (if applicable):**
   ```bash
   php artisan migrate --force
   ```
9. **Verify Queue Status:**
   ```bash
   php artisan queue:restart
   php artisan queue:work --once
   ```
10. **Verify Cron Scheduler:**
    ```bash
    php artisan schedule:run
    ```
11. **Verify Webhooks:** Send a test ping to `/webhooks/whatsapp/global` and `/webhooks/razorpay`.
12. **Test Admin & Client Authentication:** Verify login with Super Admin credentials.
13. **Test Messaging & Telephony:** Dispatch a test WhatsApp message and verify Heyo Phone connection test.
14. **Run Smoke Test Suite:**
    ```bash
    php artisan test --filter=ProductionSecurityHardeningTest
    ```
15. **Disable Maintenance Mode:**
    ```bash
    php artisan up
    ```

---

## 2. Disaster Recovery Scenarios & Playbooks

### Scenario 1: Database Corruption / Broken Migration
- **Symptom:** SQL syntax errors or corrupted table indexes.
- **Action:**
  1. Enable maintenance mode: `php artisan down`.
  2. Drop corrupted tables or recreate database: `mysqladmin -u root -p drop growbridge_db && mysqladmin -u root -p create growbridge_db`.
  3. Restore previous clean SQL dump.
  4. Run `php artisan config:cache` and disable maintenance mode: `php artisan up`.

### Scenario 2: Lost `.env` / `APP_KEY` Decryption Failure
- **Symptom:** Error `The payload is invalid` or `MAC is invalid` when accessing integrations.
- **Action:**
  - Retrieve original `APP_KEY` from your off-site `.env` backup vault.
  - Paste the original `APP_KEY` into `.env` and run `php artisan config:cache`.

### Scenario 3: Broken Webhook Delivery
- **Symptom:** Inbound WhatsApp or Razorpay events not registering in customer timeline.
- **Action:**
  1. Check **Admin > System Health > Webhooks**.
  2. Verify webhook URL matching in Meta App Dashboard / Razorpay dashboard.
  3. Verify Webhook Secret token in `.env` / **Settings > Integrations**.

### Scenario 4: Queue Stoppage / Delayed Automations
- **Symptom:** Workflows stuck in "waiting" or messages queued but not sending.
- **Action:**
  1. Check failed jobs table: `php artisan queue:failed`.
  2. If jobs failed due to transient network issues, retry all: `php artisan queue:retry all`.
  3. Verify cPanel cron job is running every minute.

---

## 3. Rollback Strategy for Failed Deployments

When deploying a new version to production, always follow the safe rollback procedure:

1. **Pre-Deployment Backup:** Always export a timestamped SQL dump and git commit hash before deploying updates.
2. **Immediate Reversion:**
   - If deployment fails, switch Document Root back to the previous release folder or revert git commit:
     ```bash
     git revert HEAD --no-edit
     php artisan config:cache
     php artisan route:cache
     php artisan view:cache
     ```
3. **Database Schema Rollback:**
   - If a new migration failed or caused regressions:
     ```bash
     php artisan migrate:rollback --step=1
     ```
   - If migration is non-reversible, restore the pre-deployment database dump immediately.
