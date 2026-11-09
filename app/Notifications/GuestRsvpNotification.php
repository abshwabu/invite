<?php

namespace App\Notifications;

use App\Models\Guest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class GuestRsvpNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Guest $guest,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'rsvp_received',
            'title' => __('RSVP Received'),
            'message' => __(':name responded ":status" for :title', [
                'name' => $this->guest->name,
                'status' => strtoupper($this->guest->rsvp_status),
                'title' => $this->guest->event?->title,
            ]),
            'meta' => [
                'event_id' => $this->guest->event_id,
                'event_title' => $this->guest->event?->title,
                'guest_id' => $this->guest->id,
                'guest_name' => $this->guest->name,
                'rsvp_status' => $this->guest->rsvp_status,
                'rsvp_party_size' => $this->guest->rsvp_party_size,
                'rsvp_note' => $this->guest->rsvp_note,
            ],
            'action_url' => route('events.show', ['event' => $this->guest->event_id]),
        ];
    }
}
