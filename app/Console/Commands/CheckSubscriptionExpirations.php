<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Notifications\QuotaLowWarningNotification;
use App\Notifications\SubscriptionExpiringSoonNotification;
use Illuminate\Console\Command;

class CheckSubscriptionExpirations extends Command
{
    protected $signature = 'subscriptions:check-expirations';

    protected $description = 'Check subscription expirations, send 7-day and 1-day warnings, low-quota warnings, and mark expired subscriptions';

    public function handle(): int
    {
        $now = now();

        // 1. Mark expired active subscriptions
        $expiredCount = Subscription::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->update(['status' => 'expired']);

        if ($expiredCount > 0) {
            $this->info("Marked {$expiredCount} subscription(s) as expired.");
        }

        // 2. Fetch remaining active subscriptions
        $activeSubscriptions = Subscription::where('status', 'active')
            ->with(['user', 'tier'])
            ->get();

        $warningCount = 0;

        foreach ($activeSubscriptions as $sub) {
            if (! $sub->user) {
                continue;
            }

            // Check expiration warnings if subscription has an expiry date
            if ($sub->expires_at && $sub->expires_at->isFuture()) {
                $hoursRemaining = $now->diffInHours($sub->expires_at);

                // 1-day warning (within 24 hours)
                if ($hoursRemaining <= 24) {
                    if (! $sub->hasSentWarning('expiring_1d')) {
                        $sub->user->notify(new SubscriptionExpiringSoonNotification($sub, 1));
                        $sub->recordWarning('expiring_1d');
                        $sub->recordWarning('expiring_7d');
                        $warningCount++;
                    }
                }
                // 7-day warning (between 24 hours and 7 days)
                elseif ($hoursRemaining <= (7 * 24)) {
                    if (! $sub->hasSentWarning('expiring_7d')) {
                        $daysRemaining = max(2, (int) ceil($hoursRemaining / 24));
                        $sub->user->notify(new SubscriptionExpiringSoonNotification($sub, $daysRemaining));
                        $sub->recordWarning('expiring_7d');
                        $warningCount++;
                    }
                }
            }

            // 3. Low Quota Warning (cards remaining <= 10% of total)
            if ($sub->cards_total > 0) {
                $cardsRemaining = max(0, $sub->cards_total - $sub->cards_used);
                $ratioRemaining = $cardsRemaining / $sub->cards_total;

                if ($ratioRemaining <= 0.10 && ! $sub->hasSentWarning('quota_low')) {
                    $sub->user->notify(new QuotaLowWarningNotification($sub, $cardsRemaining, $sub->cards_total));
                    $sub->recordWarning('quota_low');
                    $warningCount++;
                }
            }
        }

        $this->info("Dispatched {$warningCount} subscription and quota warning notifications.");

        return Command::SUCCESS;
    }
}
