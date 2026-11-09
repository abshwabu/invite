{{ $event->title }}
{{ $event->host_names ? "Hosted by {$event->host_names}" : 'You are invited!' }}
==================================================

Dear {{ $guest->name }},

@if(!empty($customBody))
{{ $customBody }}
@else
You have been warmly invited to attend {{ $event->title }}.
@endif

Event Details:
- Date: {{ $event->starts_at ? $event->starts_at->format('l, F j, Y') : 'TBA' }}
@if($event->starts_at)
- Time: {{ $event->starts_at->format('g:i A') }} ({{ $event->timezone ?? 'UTC' }})
@endif
@if($event->venue_name)
- Venue: {{ $event->venue_name }}
@endif
@if($event->venue_address)
- Address: {{ $event->venue_address }}
@endif

View your personalized invitation card and RSVP here:
{{ $invitationUrl }}

--------------------------------------------------
To stop receiving invitations for this event, opt out here:
{{ $optOutUrl }}
