# GROWBRIDGE CONNECT — PRODUCTION DEPLOYMENT GUIDE

**Platform:** GROWBRIDGE CONNECT  
**Tagline:** "Connect. Engage. Automate. Grow."  
**Target Environments:** Linux VPS (Ubuntu/Debian/RHEL), cPanel, Cloud Shared Hosting  
**Runtime Requirements:** PHP 8.2+, MySQL 8.0+ / MariaDB 10.4+, SSL Certificate  

---

## 21-Point Production Deployment Checklist

### 1. Server Requirements
- **Hardware:** Minimum 1 vCPU, 2 GB RAM, 10 GB SSD.
- **Recommended:** 2 vCPU, 4 GB RAM for high concurrency.
- **Operating System:** Linux (Ubuntu 22.04 LTS+, Debian 12+, AlmaLinux 9+, CloudLinux).

### 2. PHP Version
- `PHP 8.2` or `PHP 8.3` (64-bit CLI and FPM).

### 3. Required PHP Extensions
- `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`/`imagick`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`.

### 4. MySQL Requirements
- `MySQL 8.0+` or `MariaDB 10.4+` configured with `utf8mb4_unicode_ci` and `innodb_file_per_table=1`.

### 5. Domain Configuration
- DNS `A` or `CNAME` records pointing your domain (e.g. `connect.yourbrand.com`) to your server IP.

### 6. SSL Certificate
- Free Let's Encrypt / Certbot / cPanel AutoSSL. HTTPS is required for Meta and Razorpay webhooks.

### 7. Backend Deployment
- Clone or extract project files into Document Root (e.g. `/var/www/growbridge` or `/home/user/public_html/growbridge`).
- Set web server root to `/public`.

### 8. Frontend Deployment
- Pre-built production assets are bundled in `public/build/`.
- If building manually on server: `npm run build`.

### 9. Database Setup
```bash
mysql -u root -p -e "CREATE DATABASE growbridge_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p -e "CREATE USER 'growbridge_usr'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD';"
mysql -u root -p -e "GRANT ALL PRIVILEGES ON growbridge_db.* TO 'growbridge_usr'@'localhost'; FLUSH PRIVILEGES;"
```

### 10. Environment Setup (`.env`)
```bash
cp .env.example .env
php artisan key:generate
```
Configure database credentials, `APP_URL=https://connect.yourdomain.com`, and `APP_DEBUG=false`.

### 11. Storage & Cache Permissions
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
php artisan storage:link
```

### 12. Cache Configuration
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 13. Queue Configuration
- Default: `QUEUE_CONNECTION=database` (zero external dependencies).
- Execute queue worker via systemd or Laravel cron runner: `php artisan queue:work --stop-when-empty`.

### 14. Cron Configuration
In server crontab (`crontab -e`) or cPanel Cron Jobs:
```bash
* * * * * cd /var/www/growbridge && php artisan schedule:run >> /dev/null 2>&1
```

### 15. Webhook URLs Configuration
| Channel | Webhook Callback URL | Location in Settings |
| :--- | :--- | :--- |
| **WhatsApp Cloud API** | `https://connect.yourdomain.com/webhooks/whatsapp/global` | Meta App Dashboard > WhatsApp |
| **Instagram / Messenger** | `https://connect.yourdomain.com/webhooks/meta/{workspace_token}` | Meta App Dashboard > Webhooks |
| **Heyo Phone Telephony** | `https://connect.yourdomain.com/webhooks/voice/heyo/{call_uuid}` | Heyo Phone / MyOperator Webhook v2 |
| **Razorpay Payments** | `https://connect.yourdomain.com/webhooks/razorpay` | Razorpay Dashboard > Webhooks |

### 16. Developer API Configuration
- Public endpoints served under `https://connect.yourdomain.com/api/v1/`.
- Rate limit: 60 req/min default per API key.

### 17. Email (SMTP) Configuration
Set your transactional SMTP credentials in `.env`:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourbrand.com
MAIL_FROM_NAME="Growbridge Connect"
```

### 18. AI Provider Configuration
Configure default LLM in `.env` or via Super Admin:
```env
AI_PROVIDER=openai
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini
```

### 19. Heyo Phone Telephony Configuration
```env
HEYO_API_KEY=your_heyo_api_key
HEYO_API_SECRET=your_heyo_secret
HEYO_ACCOUNT_ID=your_account_id
HEYO_PHONE_NUMBER="+919876543210"
```

### 20. Razorpay Billing Configuration
```env
BILLING_RAZORPAY_ENABLED=true
RAZORPAY_KEY_ID=rzp_live_xxxx
RAZORPAY_KEY_SECRET=xxxx
RAZORPAY_WEBHOOK_SECRET=xxxx
```

### 21. Final Verification & Go-Live
- Navigate to `https://connect.yourdomain.com/admin` and log in with Super Admin credentials.
- Verify runtime telemetry in **Admin > System Health**.
- Execute a test WhatsApp message and incoming call simulation.
