<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentNeedsInfoNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Payment $payment,
        public string $infoMessage
    ) {}

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
        return (new MailMessage)
            ->subject("[Invite] Action Required: Verification for Payment {$this->payment->payment_code}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Our verification team needs more information to verify your payment.")
            ->line("Note from reviewer: {$this->infoMessage}")
            ->action('Provide Details', route('payments.show', $this->payment->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_needs_info',
            'payment_id' => $this->payment->id,
            'payment_code' => $this->payment->payment_code,
            'info_message' => $this->infoMessage,
            'title' => $notifiable->locale === 'am' ? 'ተጨማሪ መረጃ ያስፈልጋል' : 'Additional Information Needed',
            'message' => $this->infoMessage,
            'action_url' => route('payments.show', $this->payment->id),
        ];
    }
}
