<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgePaymentReceipts extends Command
{
    protected $signature = 'payments:purge-receipts {--days= : Override retention days cutoff}';

    protected $description = 'Delete receipt image files for approved payments older than retention days';

    public function handle(): int
    {
        $retentionDays = (int) ($this->option('days') ?: Setting::get(
            'payment_receipt_retention_days',
            config('invite.payments.receipt_retention_days', 90)
        ));

        $cutoff = now()->subDays($retentionDays);

        $payments = Payment::query()
            ->where('status', 'approved')
            ->whereNotNull('receipt_path')
            ->where(function ($q) use ($cutoff) {
                $q->where('reviewed_at', '<=', $cutoff)
                  ->orWhere(function ($sub) use ($cutoff) {
                      $sub->whereNull('reviewed_at')
                          ->where('created_at', '<=', $cutoff);
                  });
            })
            ->get();

        $purgedCount = 0;
        foreach ($payments as $payment) {
            if ($payment->receipt_path && Storage::disk('local')->exists($payment->receipt_path)) {
                Storage::disk('local')->delete($payment->receipt_path);
            }

            $payment->update([
                'receipt_path' => null,
            ]);

            PaymentEvent::create([
                'payment_id' => $payment->id,
                'actor_type' => 'system',
                'actor_id' => null,
                'event' => 'receipt_purged',
                'meta' => [
                    'retention_days' => $retentionDays,
                ],
            ]);

            $purgedCount++;
        }

        $this->info("Purged receipt files for {$purgedCount} approved payments.");

        return Command::SUCCESS;
    }
}
