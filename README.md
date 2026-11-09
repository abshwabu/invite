# Invite - Event Invitation Card Platform

"Invite" is a Laravel 11 + Vue 3 Inertia + Filament + SQLite application for designing, personalizing, and delivering event invitation cards via SMS and Email with Ethiopian calendar support, QR check-ins, and tier quota management.

---

## SMS & Email Delivery Pipeline

### 1. SMS Gateway Configuration
The application supports multi-driver SMS dispatch via `App\Contracts\SmsGateway`:
- `log`: Default for local development and testing (records messages to logs and test spy).
- `afromessage`: Production driver for Ethiopia's Afro Message SMS API (`https://api.afromessage.com/api/send`).
- `geezsms`: Stub driver for GeezSMS provider.

Configure your environment in `.env`:
```env
SMS_GATEWAY=log # options: log, afromessage, geezsms
SMS_SENDER_ID=INVITE
AFROMESSAGE_TOKEN=your_token_here
AFROMESSAGE_SENDER_ID=INVITE
AFROMESSAGE_CALLBACK_URL=https://yourdomain.com/api/webhooks/sms/afromessage
```

### 2. Delivery Status Webhooks
Inbound status webhooks are handled at:
```http
POST /api/webhooks/sms/{provider}
```
Webhook updates are idempotent. If a message permanently fails or bounces, the user's reserved quota is automatically restored via `QuotaService::refund()`.

---

## Background Queue Workers (Supervisor Configuration)

All campaign messages are dispatched asynchronously as individual `App\Jobs\SendDeliveryJob` jobs with exponential backoff retries (3 attempts).

### Supervisor Setup
Create `/etc/supervisor/conf.d/invite-worker.conf`:

```ini
[program:invite-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/invite/artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --timeout=90
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/invite/storage/logs/worker.log
stopwaitsecs=3600
```

Apply changes:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start invite-worker:*
```

---

## Short Link Redirection
Personalized invitations sent in SMS are formatted as short links (`/s/{code}`) backed by the `short_links` table with atomic click tracking.

---

## Testing

Run the full Pest feature test suite:
```bash
php artisan test
```
