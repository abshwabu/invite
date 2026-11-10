<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuotaLowWarningNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Subscription $subscription,
        public int $cardsRemaining,
        public int $totalCards
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name', 'Invite');
        $tierName = $this->subscription->tier?->name_en ?? 'Plan';
        $upgradeUrl = route('plan.index', ['tier' => $this->subscription->tier?->slug]);

        return (new MailMessage)
            ->subject("Warning: Your invitation card quota is 90% used - {$appName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("You have used 90% or more of your {$tierName} card quota.")
            ->line("Only {$this->cardsRemaining} of your {$this->totalCards} invitation cards remain available.")
            ->line("To ensure you don't run out of cards for your upcoming invitations and reminders, you can upgrade your plan or purchase an additional card package.")
            ->action('Upgrade Plan & Add Cards', $upgradeUrl)
            ->line('Thank you for organizing your events with ' . $appName . '!');
    }

    public function toArray(object $notifiable): array
    {
        $tierNameEn = $this->subscription->tier?->name_en ?? 'Plan';
        $tierNameAm = $this->subscription->tier?->name_am ?? 'ዕቅድ';

        return [
            'type' => 'quota_low_warning',
            'subscription_id' => $this->subscription->id,
            'tier_id' => $this->subscription->tier_id,
            'cards_remaining' => $this->cardsRemaining,
            'total_cards' => $this->totalCards,
            'title_en' => 'Card Quota Running Low (90% Used)',
            'title_am' => 'የካርድ ኮታዎ ሊያልቅ ተቃርቧል (90% አልቋል)',
            'message_en' => "Your invitation card quota is 90% used (only {$this->cardsRemaining} cards remaining). Upgrade your plan to send more cards.",
            'message_am' => "የካርድ ኮታዎ 90% አልቋል ({$this->cardsRemaining} ካርዶች ብቻ ይቀራሉ)። ተጨማሪ ካርዶችን ለመላክ ዕቅድዎን ያሻሽሉ።",
            'action_url' => route('plan.index', ['tier' => $this->subscription->tier?->slug]),
        ];
    }
}
