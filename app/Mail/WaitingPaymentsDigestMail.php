<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class WaitingPaymentsDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Collection $payments
    ) {}

    public function envelope(): Envelope
    {
        $count = $this->payments->count();

        return new Envelope(
            subject: "[Admin Digest] {$count} payments waiting for verification (> 12h)"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.waiting-payments-digest',
        );
    }
}
