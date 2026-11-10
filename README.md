# Invite - Ethiopian Event Invitation & Card Platform

"Invite" is a modern Laravel 11 + Vue 3 Inertia + Filament 3 + SQLite application tailored for the Ethiopian market (Amharic & English bilingual support, Ge'ez / Ethiopian calendar integration, manual bank & Telebirr payments, single-use QR admission passes, tier quota management, and production hardening).

---

## Architecture Overview

- **Backend:** Laravel 11 (PHP 8.2+) with SQLite database.
- **Frontend:** Vue 3 (Composition API) + Inertia.js + Tailwind CSS.
- **Card Rendering Engine:** Shared declarative Design JSON v2 renderer (`renderDesign.js` and `<CardRenderer.vue>`) supporting SVG ornaments, color roles (`@primary`, `@accent`, etc.), paper textures, and bilingual type scales.
- **Super Admin Panel:** Filament 3 mounted on a secret path with role-based access (`owner`, `support`), mandatory 2FA, session timeout, IP allowlist, and audit logging.
- **Safety & Moderation:** Cloudflare Turnstile CAPTCHA on signup/OTP, keyword abuse filters, new account daily send caps, guest data retention purge, and public invitation abuse reporting queue.

---

## 1. Quick Setup & Installation

### Prerequisites
- PHP 8.2+ with `pdo_sqlite`, `gd`, `zip`, `mbstring`, `fileinfo`, `intl` extensions enabled.
- Node.js 18+ and npm.
- Composer 2.x.

### Step-by-Step Setup

```bash
# 1. Clone repository and install dependencies
git clone <repo-url> invite
cd invite
composer install
npm install

# 2. Environment file
cp .env.example .env
php artisan key:generate

# 3. Create SQLite database file
touch database/database.sqlite

# 4. Migrate and Seed initial data (Tiers, Categories, Payment Methods, Templates)
php artisan migrate --seed
php artisan templates:build

# 5. Link storage and set permissions
php artisan storage:link
chmod -R 775 storage database
chown -R www-data:www-data storage database # on Linux servers

# 6. Build frontend assets
npm run build
```

---

## 2. Environment Variables Reference

Key environment variables in `.env`:

```env
APP_NAME=Invite
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.et

# SQLite Database
DB_CONNECTION=sqlite

# Super Admin Secret Access Path (never use /admin)
SUPERADMIN_PATH=sa-x7k2p9q4m1

# Cloudflare Turnstile CAPTCHA
# (Leave empty in local/testing for automatic graceful bypass)
TURNSTILE_SITE_KEY=0x4AAAAAA...
TURNSTILE_SECRET_KEY=0x4AAAAAA...

# SMS Gateway Drivers (options: log, afromessage, geezsms)
SMS_GATEWAY=afromessage
AFROMESSAGE_TOKEN=your_token_here
AFROMESSAGE_SENDER_ID=INVITE
AFROMESSAGE_CALLBACK_URL=https://yourdomain.et/api/webhooks/sms/afromessage

# Privacy & Data Retention
GUEST_DATA_RETENTION_DAYS=30

# Hot Backups & Rotation
BACKUP_RETENTION_DAYS=14
BACKUP_STORAGE_PATH=/var/www/invite/storage/app/backups
BACKUP_OFFSITE_COMMAND="rsync -avz {backup_dir}/ backupuser@remote.server:/backups/invite/"

# Session & Cookies
SESSION_SECURE_COOKIE=true
```

---

## 3. Super Admin & Moderation Queue

### Creating a Super Admin
Super admin accounts can only be created via the Artisan CLI:

```bash
php artisan superadmin:create --role=owner
```

This prompts for name, email, password, and displays 8 single-use recovery codes.
On first login via `https://yourdomain.et/{SUPERADMIN_PATH}/login`, the administrator is prompted to scan a TOTP QR code with Google Authenticator or 1Password.

### Abuse Moderation Queue
When guests flag inappropriate invitations via the "Report this invitation" modal, reports feed into the Super Admin panel under **Safety & Moderation > Abuse Reports**:
- **Dismiss:** Closes report without affecting event.
- **Suspend Event:** Immediately replaces public card links with a polite suspension notice and halts message dispatch.
- **Suspend User & Events:** Disables host account, logs out active sessions, and suspends all associated events.

---

## 4. Manual Payments Verification SOP

Because online card acquiring in Ethiopia is often restricted or under integration, payments are processed manually via CBE, Awash, or Telebirr transfer.

### Standard Operating Procedure (SOP) for Administrators:
1. Open **Billing & Revenue > Payments** in the Super Admin panel.
2. Submissions appear in the navigation badge counter with waiting durations.
3. Review Payment Details:
   - Check **Payment Code** and host identity.
   - Verify **Declared Reference Number** against your Telebirr merchant portal or CBE mobile banking statement.
   - View the uploaded bank receipt image (securely signed, unguessable, non-public storage).
   - Check automated warning flags:
     - `duplicate_ref`: Warns if reference was previously used by any user.
     - `duplicate_receipt`: Warns if receipt image perceptual hash matches another submission.
     - `amount_mismatch`: Warns if declared amount differs from tier price.
4. Available Actions:
   - **Approve:** Enters verified ETB amount, activates the subscription, increments user quota, and sends approval email/SMS.
   - **Request Info:** Prompts user to upload clearer proof or fix transaction reference.
   - **Reject:** Declines payment with reason (e.g. invalid receipt, funds not received).

---

## 5. Adding Chapa / Online Payment Gateway Later

All payments are abstracted behind `App\Contracts\PaymentProvider`:

```php
interface PaymentProvider
{
    public function initiate(User $user, Tier $tier, array $options = []): Payment;
    public function submitProof(Payment $payment, array $data): Payment;
    public function approve(Payment $payment, float $amountVerified, ?SuperAdmin $admin = null, ?string $notes = null): Payment;
    public function reject(Payment $payment, string $reason, ?SuperAdmin $admin = null): Payment;
    public function refund(Payment $payment, ?SuperAdmin $admin = null, ?string $reason = null): Payment;
    public function requestInfo(Payment $payment, string $message, ?SuperAdmin $admin = null): Payment;
}
```

To integrate an online gateway like Chapa:
1. Create `App\Services\Payment\ChapaPaymentProvider implements PaymentProvider`.
2. Implement API initialization (`POST https://api.chapa.co/v1/transaction/initialize`) and webhook handler (`/api/webhooks/payments/chapa`).
3. Bind the new provider in `App\Providers\AppServiceProvider.php`:
   ```php
   $this->app->bind(PaymentProvider::class, ChapaPaymentProvider::class);
   ```

---

## 6. Background Workers, Schedulers & Production Deployment

### Supervisor Queue Worker
Deploy the configuration from `deploy/supervisor/invite-worker.conf` to `/etc/supervisor/conf.d/invite-worker.conf`:

```ini
[program:invite-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/invite/artisan queue:work --sleep=3 --tries=3 --max-time=3600 --timeout=90
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/invite/storage/logs/worker.log
```

Reload supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start invite-worker:*
```

### Scheduled Cron Tasks
Add to crontab via `crontab -e -u www-data`:

```cron
* * * * * cd /var/www/invite && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled commands include:
- `subscriptions:check-expirations` (Daily at 08:00): Sends 7-day, 1-day, and 90% quota warnings, and expires overdue plans.
- `guests:purge-retention` (Daily at 02:00): Anonymizes guest PII 30 days after event conclusion.
- `backup:database` (Daily at 03:30): Hot SQLite `VACUUM INTO` backup, compresses receipts, and rotates 14-day history.
- `quota:reconcile` (Weekly): Audit ledger reconciliation.
- `campaigns:dispatch-scheduled` (Every minute): Dispatches scheduled campaigns.
- `reminders:dispatch` (Every minute): Dispatches automated guest reminders.

---

## 7. Troubleshooting & Maintenance Runbook

### SQLite Database Locks (`SQLITE_BUSY` or `database is locked`)
- Ensure SQLite journal mode is set to WAL:
  ```bash
  sqlite3 database/database.sqlite "PRAGMA journal_mode=WAL;"
  ```
- Ensure file and directory ownership is `www-data:www-data` with permission `775`.
- `CheckinService` and `QuotaService` utilize guarded atomic transactions with 5 retry loops to safely absorb concurrent scans.

### Reconciling Subscription Quotas
If a discrepancy is suspected between subscription `cards_used` and the usage ledger:
```bash
# Report discrepancies
php artisan quota:reconcile

# Synchronize and write audited adjustments
php artisan quota:reconcile --fix
```

### Manual Database Hot Backup
```bash
php artisan backup:database
```
Backups are saved to `storage/app/backups/invite-backup-YYYY-MM-DD_HHmmss.sqlite` along with `receipts-backup-*.zip`.

---

## 8. Test Suite Verification

Run the full test suite with all 280+ tests and 13,000+ assertions:

```bash
# Feature & Unit Tests (PHP)
php artisan test

# Frontend Unit Tests (Vitest)
npx vitest run

# Production Asset Compile
npm run build
```
