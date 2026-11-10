<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Services\DesignValidator;
use App\Services\EntitlementService;
use App\Services\EthiopianCalendar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Response;

class PublicThankYouController extends Controller
{
    public function __construct(
        protected EntitlementService $entitlementService,
    ) {}

    /**
     * Display the personalized thank-you page for a guest.
     */
    public function show(Request $request, string $token): Response
    {
        // 1. Unguessable token check - 404 on invalid or not found
        $guest = Guest::where('token', $token)->first();
        if (! $guest) {
            abort(404, __('Thank you card not found.'));
        }

        $event = $guest->event()->with('category')->firstOrFail();

        // 2. Suspended event check
        if ($event->status === 'suspended') {
            abort(404, __('Thank you card not found.'));
        }

        // 3. Locale resolution
        $lang = $request->query('lang');
        if (! in_array($lang, ['en', 'am'], true)) {
            $header = $request->header('Accept-Language');
            $lang = (str_starts_with((string) $header, 'am') || str_contains((string) $header, 'am-ET')) ? 'am' : 'en';
        }
        App::setLocale($lang);

        // 4. Entitlements
        $hasRemoveBranding = $this->entitlementService->can($event->user, 'remove_branding');

        // 5. Card design JSON resolution
        $design = $event->thankyou_design_json;
        if (! is_array($design) || ! DesignValidator::isValid($design)) {
            $design = $event->thankyouTemplate?->design_json ?? DesignValidator::blankDesign();
        }

        // 6. Placeholder replacement data
        $placeholderData = [
            'guest_name' => $guest->name,
            'event_title' => $event->title,
            'date' => $event->starts_at ? $event->starts_at->format('F j, Y') : '',
            'time' => $event->starts_at ? $event->starts_at->format('g:i A') : '',
            'venue' => $event->venue_name ?: '',
            'host_names' => $event->host_names ?: '',
        ];

        $ethiopianDate = EthiopianCalendar::fromGregorian($event->starts_at);

        $response = Inertia::render('Public/ThankYou', [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'starts_at' => $event->starts_at->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
                'timezone' => $event->timezone,
                'venue_name' => $event->venue_name,
                'venue_address' => $event->venue_address,
                'host_names' => $event->host_names,
                'category_slug' => $event->category?->slug,
                'thankyou_note' => $event->thankyou_note,
                'thankyou_photo_url' => $event->thankyou_photo_url,
            ],
            'guest' => [
                'name' => $guest->name,
                'token' => $guest->token,
            ],
            'design' => $design,
            'placeholderData' => $placeholderData,
            'hasRemoveBranding' => $hasRemoveBranding,
            'ethiopianDate' => $ethiopianDate,
            'locale' => $lang,
        ]);

        return $response->withViewData([
            'noindex' => true,
        ]);
    }

    /**
     * Submit an abuse report for a thank-you card.
     */
    public function report(Request $request, string $token): \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $guest = Guest::where('token', $token)->first();
        if (! $guest) {
            abort(404, __('Thank you card not found.'));
        }

        $event = $guest->event;
        if (! $event) {
            abort(404, __('Event not found.'));
        }

        $ip = $request->ip() ?? '127.0.0.1';
        $rateLimitKey = "abuse_report_limit:{$ip}";
        $recentReports = (int) \Illuminate\Support\Facades\Cache::get($rateLimitKey, 0);

        if ($recentReports >= 5) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Too many reports submitted. Please wait before submitting another report.',
                ], 429);
            }
            return back()->with('error', 'Too many reports submitted. Please wait before submitting another report.');
        }

        $validated = $request->validate([
            'reason' => 'required|string|in:spam,phishing,inappropriate,fraud,harassment,other',
            'description' => 'nullable|string|max:1000',
            'reporter_email' => 'nullable|email|max:150',
        ]);

        \App\Models\AbuseReport::create([
            'event_id' => $event->id,
            'guest_id' => $guest->id,
            'reporter_ip' => $ip,
            'reporter_email' => $validated['reporter_email'] ?? null,
            'reason' => $validated['reason'],
            'description' => $validated['description'] ?? null,
            'status' => 'pending',
        ]);

        \Illuminate\Support\Facades\Cache::put($rateLimitKey, $recentReports + 1, now()->addHour());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Thank you. Your report has been submitted to our moderation team.',
            ]);
        }

        return back()->with('success', 'Thank you. Your report has been submitted to our moderation team.');
    }
}
