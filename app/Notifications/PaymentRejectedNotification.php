<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Payment $payment,
        public string $reason
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
        $appName = config('app.name', 'Teri');
        $tierName = $this->payment->tier->name_en;

        return (new MailMessage)
            ->subject("[{$appName}] Payment Review Update: {$this->payment->payment_code}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your payment for the {$tierName} package could not be verified.")
            ->line("Reason: {$this->reason}")
            ->line("You can resubmit a corrected receipt or reference number directly from your payment status page.")
            ->action('View Payment & Resubmit', route('payments.show', $this->payment->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_rejected',
            'payment_id' => $this->payment->id,
            'payment_code' => $this->payment->payment_code,
            'reason' => $this->reason,
            'title' => $notifiable->locale === 'am' ? 'የክፍያ ማረጋገጫ አልተሳካም' : 'Payment Not Verified',
            'message' => $notifiable->locale === 'am'
                ? "ለ{$this->payment->payment_code} የተላከው ክፍያ ሊረጋገጥ አልቻለም፡ {$this->reason}"
                : "Your payment verification for {$this->payment->payment_code} was not approved: {$this->reason}",
            'action_url' => route('payments.show', $this->payment->id),
        ];
    }
}
