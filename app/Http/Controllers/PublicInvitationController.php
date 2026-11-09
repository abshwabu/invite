<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Guest;
use App\Notifications\GuestRsvpNotification;
use App\Services\EntitlementService;
use App\Services\EthiopianCalendar;
use App\Services\QrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicInvitationController extends Controller
{
    public function __construct(
        protected EntitlementService $entitlementService,
    ) {}

    /**
     * Display the personalized invitation page for a guest.
     */
    public function show(Request $request, string $token): Response
    {
        // 1. Unguessable token check - 404 on invalid
        $guest = Guest::where('token', $token)->first();
        if (! $guest) {
            abort(404, __('Invitation not found.'));
        }

        // 2. Record first view
        if ($guest->viewed_at === null) {
            $guest->update(['viewed_at' => now()]);
        }

        $event = $guest->event()->with('category')->firstOrFail();

        // 3. Language selection (Amharic / English)
        $lang = $request->query('lang');
        if (! in_array($lang, ['en', 'am'], true)) {
            // Check cookie or browser header
            $header = $request->header('Accept-Language');
            $lang = (str_starts_with((string) $header, 'am') || str_contains((string) $header, 'am-ET')) ? 'am' : 'en';
        }
        App::setLocale($lang);

        // 4. Entitlements
        $hasRemoveBranding = $this->entitlementService->can($event->user, 'remove_branding');

        // 5. Placeholder data for CardRenderer
        $placeholderData = [
            'guest_name' => $guest->name,
            'event_title' => $event->title,
            'date' => $event->starts_at ? $event->starts_at->format('F j, Y') : '',
            'time' => $event->starts_at ? $event->starts_at->format('g:i A') : '',
            'venue' => $event->venue_name ?: '',
            'host_names' => $event->host_names ?: '',
        ];

        // 6. QR Code Generation (if invite-only)
        $qrSvg = null;
        if ($event->is_invite_only) {
            $nonce = substr($guest->token, 0, 16);
            $payload = QrService::generatePayload($event->id, $guest->id, $nonce);
            $qrSvg = QrService::generateSvg($payload, 280);
        }

        $ethiopianDate = EthiopianCalendar::fromGregorian($event->starts_at);
        $isPast = $event->starts_at->isPast();
        $isFuneral = $event->category?->slug === 'funeral';

        return Inertia::render('Public/Invitation', [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'starts_at' => $event->starts_at->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
                'timezone' => $event->timezone,
                'venue_name' => $event->venue_name,
                'venue_address' => $event->venue_address,
                'map_url' => $event->map_url,
                'host_names' => $event->host_names,
                'is_invite_only' => (bool) $event->is_invite_only,
                'rsvp_enabled' => (bool) $event->rsvp_enabled,
                'plus_ones_allowed' => (bool) $event->plus_ones_allowed,
                'design_json' => $event->design_json,
                'category' => $event->category,
                'public_slug' => $event->public_slug,
            ],
            'guest' => [
                'id' => $guest->id,
                'name' => $guest->name,
                'token' => $guest->token,
                'preferred_channel' => $guest->preferred_channel,
                'max_party_size' => $guest->max_party_size,
                'rsvp_status' => $guest->rsvp_status,
                'rsvp_party_size' => $guest->rsvp_party_size,
                'rsvp_note' => $guest->rsvp_note,
                'opted_out' => (bool) $guest->opted_out,
            ],
            'placeholderData' => $placeholderData,
            'qrSvg' => $qrSvg,
            'ethiopianDate' => $ethiopianDate,
            'hasRemoveBranding' => $hasRemoveBranding,
            'isPast' => $isPast,
            'isFuneral' => $isFuneral,
            'currentLocale' => $lang,
            'ogImageUrl' => route('public.invitation.og-image', ['token' => $guest->token]),
        ])->withViewData([
            'noindex' => true,
        ]);
    }

    /**
     * Submit or change RSVP answer.
     */
    public function rsvp(Request $request, string $token): JsonResponse|RedirectResponse
    {
        $guest = Guest::where('token', $token)->first();
        if (! $guest) {
            abort(404, __('Invitation not found.'));
        }

        $event = $guest->event;

        if (! $event->rsvp_enabled) {
            throw ValidationException::withMessages([
                'rsvp_status' => __('RSVP is disabled for this event.'),
            ]);
        }

        if ($event->status === 'closed') {
            throw ValidationException::withMessages([
                'rsvp_status' => __('RSVP is closed as this event has ended.'),
            ]);
        }

        if ($event->starts_at->isPast()) {
            throw ValidationException::withMessages([
                'rsvp_status' => __('RSVP is closed because this event has already started.'),
            ]);
        }

        $validated = $request->validate([
            'rsvp_status' => ['required', 'string', 'in:yes,no,maybe'],
            'rsvp_party_size' => ['nullable', 'integer', 'min:1', 'max:' . $guest->max_party_size],
            'rsvp_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $guest->update([
            'rsvp_status' => $validated['rsvp_status'],
            'rsvp_party_size' => $validated['rsvp_status'] === 'yes' ? ($validated['rsvp_party_size'] ?? 1) : 0,
            'rsvp_note' => $validated['rsvp_note'] ?? null,
        ]);

        // In-app notification to the event host
        if ($event->user) {
            $event->user->notify(new GuestRsvpNotification($guest));
        }

        $message = __('Thank you! Your RSVP has been recorded.');

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'guest' => $guest,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Opt-out from reminders ("Don't send me reminders").
     */
    public function optOut(Request $request, string $token): JsonResponse|RedirectResponse
    {
        $guest = Guest::where('token', $token)->first();
        if (! $guest) {
            abort(404);
        }

        $guest->update(['opted_out' => true]);

        $message = __('You have been opted out from reminders.');

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Download .ics Calendar event.
     */
    public function calendarIcs(string $token): HttpResponse
    {
        $guest = Guest::where('token', $token)->first();
        if (! $guest) {
            abort(404);
        }

        $event = $guest->event;

        $dtStart = $event->starts_at->setTimezone('UTC')->format('Ymd\THis\Z');
        $dtEnd = ($event->ends_at ?? $event->starts_at->addHours(4))->setTimezone('UTC')->format('Ymd\THis\Z');
        $now = now()->setTimezone('UTC')->format('Ymd\THis\Z');

        $summary = addcslashes($event->title, ",;");
        $desc = addcslashes($event->description ?? "Invitation for {$guest->name}", ",;");
        $location = addcslashes(($event->venue_name ?: '') . ' ' . ($event->venue_address ?: ''), ",;");

        $ics = "BEGIN:VCALENDAR\r\n"
            . "VERSION:2.0\r\n"
            . "PRODID:-//Invite//Invitation Card//EN\r\n"
            . "CALSCALE:GREGORIAN\r\n"
            . "METHOD:PUBLISH\r\n"
            . "BEGIN:VEVENT\r\n"
            . "UID:event-{$event->id}-guest-{$guest->id}@invite.et\r\n"
            . "DTSTAMP:{$now}\r\n"
            . "DTSTART:{$dtStart}\r\n"
            . "DTEND:{$dtEnd}\r\n"
            . "SUMMARY:{$summary}\r\n"
            . "DESCRIPTION:{$desc}\r\n"
            . "LOCATION:{$location}\r\n"
            . "END:VEVENT\r\n"
            . "END:VCALENDAR\r\n";

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => sprintf('attachment; filename="%s.ics"', Str::slug($event->title ?: 'invitation')),
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /**
     * Download QR pass as standalone SVG or PNG.
     */
    public function qrDownload(string $token): HttpResponse
    {
        $guest = Guest::where('token', $token)->first();
        if (! $guest) {
            abort(404);
        }

        $event = $guest->event;
        $nonce = substr($guest->token, 0, 16);
        $payload = QrService::generatePayload($event->id, $guest->id, $nonce);
        $svg = QrService::generateSvg($payload, 400);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => sprintf('attachment; filename="%s-pass.svg"', Str::slug($guest->name ?: 'qr-pass')),
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /**
     * Render personalized Open Graph preview image (1200x630 PNG).
     */
    public function ogImage(string $token): HttpResponse
    {
        $guest = Guest::where('token', $token)->first();
        if (! $guest) {
            abort(404);
        }

        $event = $guest->event;

        $width = 1200;
        $height = 630;
        $image = imagecreatetruecolor($width, $height);

        // Dark luxury background
        $bgColor = imagecolorallocate($image, 16, 16, 20); // #101014
        imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);

        // Gold border
        $gold = imagecolorallocate($image, 212, 175, 55); // #D4AF37
        imagesetthickness($image, 4);
        imagerectangle($image, 30, 30, $width - 30, $height - 30, $gold);

        // Text Colors
        $white = imagecolorallocate($image, 255, 255, 255);
        $goldLight = imagecolorallocate($image, 245, 215, 110);
        $gray = imagecolorallocate($image, 160, 160, 175);

        // Header label
        $fontPath = public_path('fonts/Montserrat-Regular.ttf');
        $hasTtf = file_exists($fontPath);

        if ($hasTtf) {
            imagettftext($image, 18, 0, 70, 95, $goldLight, $fontPath, 'CORDIAL INVITATION');
            imagettftext($image, 34, 0, 70, 180, $white, $fontPath, mb_substr($event->title, 0, 45));
            imagettftext($image, 22, 0, 70, 240, $gold, $fontPath, "Special Invitation for: " . mb_substr($guest->name, 0, 40));
            imagettftext($image, 16, 0, 70, 320, $gray, $fontPath, "Date: " . ($event->starts_at ? $event->starts_at->format('F j, Y - g:i A') : ''));
            imagettftext($image, 16, 0, 70, 360, $gray, $fontPath, "Venue: " . ($event->venue_name ?: 'Venue to be announced'));
            imagettftext($image, 14, 0, 70, 560, $goldLight, $fontPath, 'Invite • invite.et');
        } else {
            imagestring($image, 5, 70, 80, 'CORDIAL INVITATION', $goldLight);
            imagestring($image, 5, 70, 140, substr($event->title, 0, 50), $white);
            imagestring($image, 5, 70, 180, "Special Invitation for: " . substr($guest->name, 0, 45), $gold);
            imagestring($image, 4, 70, 240, "Date: " . ($event->starts_at ? $event->starts_at->format('F j, Y - g:i A') : ''), $gray);
            imagestring($image, 4, 70, 280, "Venue: " . ($event->venue_name ?: 'Venue to be announced'), $gray);
            imagestring($image, 3, 70, 560, 'Invite • invite.et', $goldLight);
        }

        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        return response($imageData, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /**
     * Public event info page GET /e/{event_slug_or_token}
     * Shows public event details without private guest information.
     */
    public function eventInfo(Request $request, string $slugOrToken): Response
    {
        $event = Event::where('public_slug', $slugOrToken)
            ->orWhere('id', is_numeric($slugOrToken) ? (int) $slugOrToken : 0)
            ->with('category')
            ->first();

        if (! $event) {
            abort(404, __('Event not found.'));
        }

        $ethiopianDate = EthiopianCalendar::fromGregorian($event->starts_at);
        $isPast = $event->starts_at->isPast();

        return Inertia::render('Public/EventInfo', [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'starts_at' => $event->starts_at->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
                'timezone' => $event->timezone,
                'venue_name' => $event->venue_name,
                'venue_address' => $event->venue_address,
                'map_url' => $event->map_url,
                'host_names' => $event->host_names,
                'category' => $event->category,
                'is_invite_only' => (bool) $event->is_invite_only,
                'rsvp_enabled' => (bool) $event->rsvp_enabled,
                'public_slug' => $event->public_slug,
            ],
            'ethiopianDate' => $ethiopianDate,
            'isPast' => $isPast,
        ]);
    }
}
