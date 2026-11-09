<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Notifications\HostAlertNotification;
use Illuminate\Console\Command;

class CheckExpiringSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:check-expiring';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for subscriptions expiring within 7 days and notify users';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = now();
        $inSevenDays = $now->copy()->addDays(7);

        $expiringSubscriptions = Subscription::with(['user', 'tier'])
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$now, $inSevenDays])
            ->get();

        $this->info("Found {$expiringSubscriptions->count()} subscription(s) expiring within 7 days.");

        $notifiedCount = 0;
        foreach ($expiringSubscriptions as $sub) {
            $user = $sub->user;
            if (! $user) {
                continue;
            }

            // Check if user was already notified in the last 24 hours
            $alreadyNotified = $user->notifications()
                ->where('type', HostAlertNotification::class)
                ->where('created_at', '>=', now()->subHours(24))
                ->where('data->meta->subscription_id', $sub->id)
                ->exists();

            if ($alreadyNotified) {
                continue;
            }

            $daysLeft = (int) ceil($now->diffInDays($sub->expires_at, false));
            $daysText = $daysLeft <= 1 ? '1 day' : "{$daysLeft} days";

            $user->notify(new HostAlertNotification(
                type: 'subscription_expiring',
                title: __('Subscription Expiring Soon'),
                message: __("Your :tier plan subscription will expire in :days (:date). Renew your plan to keep creating and sending cards.", [
                    'tier' => $sub->tier?->name_en ?? 'Current',
                    'days' => $daysText,
                    'date' => $sub->expires_at->format('M d, Y'),
                ]),
                meta: [
                    'subscription_id' => $sub->id,
                    'tier_id' => $sub->tier_id,
                    'expires_at' => $sub->expires_at->toIso8601String(),
                ],
                actionUrl: route('plan.index')
            ));

            $notifiedCount++;
            $this->line("Notified user #{$user->id} ({$user->email}) about subscription #{$sub->id} expiring in {$daysText}.");
        }

        $this->info("Completed. Sent {$notifiedCount} expiration notification(s).");

        return Command::SUCCESS;
    }
}
