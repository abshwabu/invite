<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiringSoonNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Subscription $subscription,
        public int $daysRemaining
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
            ->subject("Warning: Your {$tierName} subscription expires in {$this->daysRemaining} day(s) - {$appName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your {$tierName} subscription on {$appName} will expire in {$this->daysRemaining} day(s).")
            ->line("Existing events, public invitation cards, and guest check-ins will continue working normally, but sending new cards will be paused once expired.")
            ->action('Renew or Upgrade Your Plan', $upgradeUrl)
            ->line('Thank you for choosing ' . $appName . '!');
    }

    public function toArray(object $notifiable): array
    {
        $tierNameEn = $this->subscription->tier?->name_en ?? 'Plan';
        $tierNameAm = $this->subscription->tier?->name_am ?? 'ዕቅድ';

        return [
            'type' => 'subscription_expiring_soon',
            'subscription_id' => $this->subscription->id,
            'tier_id' => $this->subscription->tier_id,
            'days_remaining' => $this->daysRemaining,
            'title_en' => "Subscription Expiring Soon ({$this->daysRemaining}d)",
            'title_am' => "ዕቅድዎ ሊያበቃ ጥቂት ቀናት ቀርተውታል ({$this->daysRemaining}ቀን)",
            'message_en' => "Your {$tierNameEn} subscription will expire in {$this->daysRemaining} day(s). Renew now to keep sending cards without interruption.",
            'message_am' => "የ{$tierNameAm} ዕቅድዎ በ{$this->daysRemaining} ቀን ውስጥ ያበቃል። ያለምንም መቆራረጥ ካርዶችን መላክዎን ለመቀጠል አሁኑኑ ያድሱ።",
            'action_url' => route('plan.index', ['tier' => $this->subscription->tier?->slug]),
        ];
    }
}
