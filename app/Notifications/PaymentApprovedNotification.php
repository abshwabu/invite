<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(public Payment $payment) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if (config('invite.payments.notify_user_email', true) && !empty($notifiable->email)) {
            $channels[] = 'mail';
        }
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tierName = $notifiable->locale === 'am' && $this->payment->tier->name_am
            ? $this->payment->tier->name_am
            : $this->payment->tier->name_en;

        $quota = number_format($this->payment->tier->card_quota);

        return (new MailMessage)
            ->subject("[Invite] Payment Approved: {$tierName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your {$tierName} package is active. You have {$quota} cards.")
            ->line("Payment Code: {$this->payment->payment_code}")
            ->line("Amount Verified: " . number_format((float) $this->payment->amount_verified, 2) . " ETB")
            ->action('Open Dashboard', route('dashboard'))
            ->line('Thank you for choosing Invite for your event celebrations!');
    }

    public function toArray(object $notifiable): array
    {
        $tierName = $notifiable->locale === 'am' && $this->payment->tier->name_am
            ? $this->payment->tier->name_am
            : $this->payment->tier->name_en;

        $quota = number_format($this->payment->tier->card_quota);

        return [
            'type' => 'payment_approved',
            'payment_id' => $this->payment->id,
            'payment_code' => $this->payment->payment_code,
            'tier_name' => $tierName,
            'cards_quota' => $this->payment->tier->card_quota,
            'title' => $notifiable->locale === 'am' ? 'ክፍያዎ ጸድቋል' : 'Payment Approved',
            'message' => $notifiable->locale === 'am'
                ? "የ{$tierName} ጥቅልዎ ነቅቷል። {$quota} ካርዶች አሉዎት።"
                : "Your {$tierName} package is active. You have {$quota} cards.",
            'action_url' => route('dashboard'),
        ];
    }
}
