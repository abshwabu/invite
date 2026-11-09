<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\Guest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Event $event,
        public Guest $guest,
        public string $invitationUrl,
        public ?string $customSubject = null,
        public ?string $customBody = null
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->customSubject ?: "{$this->event->title} - Invitation from {$this->event->host_names}";
        $replyToEmail = $this->event->user?->email ?: config('mail.from.address');
        $replyToName = $this->event->host_names ?: ($this->event->user?->name ?: config('mail.from.name'));

        return new Envelope(
            subject: $subject,
            replyTo: [
                new Address($replyToEmail, $replyToName),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invitation',
            text: 'emails.invitation-text',
            with: [
                'event' => $this->event,
                'guest' => $this->guest,
                'invitationUrl' => $this->invitationUrl,
                'optOutUrl' => route('public.invitation.opt-out', ['token' => $this->guest->token]),
                'ogImageUrl' => route('public.invitation.og-image', ['token' => $this->guest->token]),
                'customBody' => $this->customBody,
            ],
        );
    }
}
