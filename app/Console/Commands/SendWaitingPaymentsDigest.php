<?php

namespace App\Console\Commands;

use App\Mail\WaitingPaymentsDigestMail;
use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendWaitingPaymentsDigest extends Command
{
    protected $signature = 'payments:send-waiting-digest';

    protected $description = 'Send daily email digest to admins for payments waiting more than 12 hours';

    public function handle(): int
    {
        $cutoff = now()->subHours(12);

        $waitingPayments = Payment::query()
            ->where('status', 'submitted')
            ->where('submitted_at', '<=', $cutoff)
            ->with(['user', 'tier'])
            ->orderBy('submitted_at', 'asc')
            ->get();

        if ($waitingPayments->isEmpty()) {
            $this->info('No payments waiting over 12 hours.');
            return Command::SUCCESS;
        }

        $adminEmails = Setting::get(
            'admin_notification_emails',
            config('invite.payments.admin_notification_emails', 'admin@invite.et')
        );

        $recipients = array_filter(array_map('trim', explode(',', $adminEmails)));

        if (empty($recipients)) {
            $this->warn('No admin recipient emails configured for digest.');
            return Command::SUCCESS;
        }

        $count = $waitingPayments->count();
        $this->info("Found {$count} payments waiting > 12 hours. Sending digest to " . implode(', ', $recipients));

        try {
            Mail::to($recipients)->send(new WaitingPaymentsDigestMail($waitingPayments));
        } catch (\Throwable $e) {
            $this->error("Failed to send digest: " . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}
