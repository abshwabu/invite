<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Setting;
use Illuminate\Console\Command;

class ExpireAbandonedPayments extends Command
{
    protected $signature = 'payments:expire-abandoned {--days= : Override abandoned days cutoff}';

    protected $description = 'Mark awaiting_proof payments as abandoned after configured days';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: Setting::get('payment_abandoned_after_days', config('invite.payments.abandoned_after_days', 7)));
        $cutoff = now()->subDays($days);

        $abandoned = Payment::query()
            ->where('status', 'awaiting_proof')
            ->where('created_at', '<=', $cutoff)
            ->get();

        $count = 0;
        foreach ($abandoned as $payment) {
            $payment->update([
                'status' => 'abandoned',
            ]);

            PaymentEvent::create([
                'payment_id' => $payment->id,
                'actor_type' => 'system',
                'actor_id' => null,
                'event' => 'abandoned',
                'meta' => [
                    'reason' => "Expired after {$days} days without submitted proof",
                ],
            ]);

            $count++;
        }

        $this->info("Marked {$count} abandoned payments.");

        return Command::SUCCESS;
    }
}
