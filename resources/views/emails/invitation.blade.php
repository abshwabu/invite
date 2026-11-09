<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $event->title }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }
        .header {
            background-color: #4f46e5;
            color: #ffffff;
            padding: 28px 24px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.025em;
        }
        .header p {
            margin: 6px 0 0 0;
            font-size: 15px;
            opacity: 0.9;
        }
        .body {
            padding: 32px 28px;
        }
        .salutation {
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 16px;
        }
        .card-preview {
            margin: 24px 0;
            text-align: center;
            border-radius: 8px;
            overflow: hidden;
            background-color: #f1f5f9;
            border: 1px solid #e2e8f0;
        }
        .card-preview img {
            max-width: 100%;
            height: auto;
            display: block;
            margin: 0 auto;
        }
        .event-meta {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            margin: 24px 0;
            border: 1px solid #e2e8f0;
        }
        .meta-item {
            margin-bottom: 12px;
            display: flex;
            align-items: baseline;
        }
        .meta-item:last-child {
            margin-bottom: 0;
        }
        .meta-label {
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            min-width: 80px;
        }
        .meta-value {
            font-size: 15px;
            color: #1e293b;
        }
        .btn-wrapper {
            text-align: center;
            margin: 32px 0 24px 0;
        }
        .btn {
            display: inline-block;
            background-color: #4f46e5;
            color: #ffffff !important;
            font-weight: 600;
            font-size: 16px;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(79, 70, 229, 0.25);
        }
        .footer {
            background-color: #f8fafc;
            padding: 20px 24px;
            text-align: center;
            font-size: 13px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
        }
        .footer a {
            color: #64748b;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $event->title }}</h1>
            <p>{{ $event->host_names ? "Hosted by {$event->host_names}" : 'You are invited!' }}</p>
        </div>

        <div class="body">
            <div class="salutation">
                Dear {{ $guest->name }},
            </div>

            @if(!empty($customBody))
                <div style="margin-bottom: 20px; white-space: pre-line; color: #334155;">
                    {{ $customBody }}
                </div>
            @else
                <p style="margin-bottom: 20px; color: #334155;">
                    You have been warmly invited to attend <strong>{{ $event->title }}</strong>. Please open the link below to view your personalized invitation card and let us know if you can make it.
                </p>
            @endif

            @if(!empty($ogImageUrl))
                <div class="card-preview">
                    <a href="{{ $invitationUrl }}" target="_blank">
                        <img src="{{ $ogImageUrl }}" alt="Invitation Card Preview" />
                    </a>
                </div>
            @endif

            <div class="event-meta">
                <div class="meta-item">
                    <span class="meta-label">Date:</span>
                    <span class="meta-value">{{ $event->starts_at ? $event->starts_at->format('l, F j, Y') : 'TBA' }}</span>
                </div>
                @if($event->starts_at)
                    <div class="meta-item">
                        <span class="meta-label">Time:</span>
                        <span class="meta-value">{{ $event->starts_at->format('g:i A') }} ({{ $event->timezone ?? 'UTC' }})</span>
                    </div>
                @endif
                @if($event->venue_name)
                    <div class="meta-item">
                        <span class="meta-label">Venue:</span>
                        <span class="meta-value">{{ $event->venue_name }}</span>
                    </div>
                @endif
                @if($event->venue_address)
                    <div class="meta-item">
                        <span class="meta-label">Address:</span>
                        <span class="meta-value">{{ $event->venue_address }}</span>
                    </div>
                @endif
            </div>

            <div class="btn-wrapper">
                <a href="{{ $invitationUrl }}" class="btn" target="_blank">
                    View My Invitation
                </a>
            </div>

            <p style="font-size: 13px; color: #94a3b8; text-align: center; margin-top: 16px;">
                Can't click the button? Copy and paste this URL into your browser:<br>
                <a href="{{ $invitationUrl }}" style="color: #4f46e5; word-break: break-all;">{{ $invitationUrl }}</a>
            </p>
        </div>

        <div class="footer">
            <p style="margin: 0 0 8px 0;">Sent via Invite &bull; Effortless Ethiopian & International Event Invitations</p>
            <p style="margin: 0;">
                If you no longer wish to receive invitations for this event, you can <a href="{{ $optOutUrl }}">opt out here</a>.
            </p>
        </div>
    </div>
</body>
</html>
