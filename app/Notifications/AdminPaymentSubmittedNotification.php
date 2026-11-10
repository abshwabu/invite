<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminPaymentSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(public Payment $payment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $flagNote = '';
        if (!empty($this->payment->flags)) {
            $flagNote = ' [FLAGGED: ' . implode(', ', array_keys(array_filter($this->payment->flags))) . ']';
        }

        return (new MailMessage)
            ->subject("[Admin Alert] Payment Submitted: {$this->payment->payment_code}{$flagNote}")
            ->greeting('Hello Admin,')
            ->line("A new manual payment has been submitted for review.")
            ->line("User: {$this->payment->user->name} ({$this->payment->user->email})")
            ->line("Tier: {$this->payment->tier->name_en} ({$this->payment->amount_expected} ETB)")
            ->line("Reference: " . ($this->payment->reference_number ?: 'None (Receipt uploaded)'))
            ->line("Payment Code: {$this->payment->payment_code}")
            ->line($flagNote ? "Security Flags: {$flagNote}" : 'Status: Normal Queue');
    }
}
