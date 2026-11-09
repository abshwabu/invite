<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Template;
use App\Services\AudienceService;
use App\Services\EntitlementService;
use App\Services\QuotaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ThankYouController extends Controller
{
    public function __construct(
        protected EntitlementService $entitlementService,
        protected QuotaService $quotaService,
        protected AudienceService $audienceService,
    ) {}

    /**
     * Show the Thank-You card setup and compose screen.
     */
    public function show(Request $request, Event $event): Response|RedirectResponse
    {
        Gate::authorize('view', $event);

        $user = $request->user();

        // 1. Feature gating
        if (! $this->entitlementService->can($user, 'thank_you')) {
            return redirect()->route('plan.index')->withErrors([
                'feature' => 'Thank-you cards require an upgraded tier. Please upgrade your plan to unlock thank-you cards.',
            ]);
        }

        $tier = $this->entitlementService->getHighestTier($user);
        $consumesQuota = (bool) ($tier?->thank_you_consumes_quota ?? false);
        $availableQuota = $this->quotaService->available($user);

        // 2. Audience defaults & stats
        $defaultAudience = $this->audienceService->defaultThankYouAudience($event);
        $explanation = $this->audienceService->getThankYouAudienceExplanation($event, $defaultAudience);

        $guests = $event->guests;
        $activeGuests = $guests->where('opted_out', false);

        $audienceCounts = [
            'checked_in' => $activeGuests->whereNotNull('checked_in_at')->count(),
            'rsvp_yes' => $activeGuests->where('rsvp_status', 'yes')->count(),
            'all' => $activeGuests->count(),
        ];

        // 3. Category wording
        $isFuneral = $event->category?->slug === 'funeral';
        $defaultMessageEn = $isFuneral
            ? 'Dear {guest_name}, thank you for joining us to honor the memory of {event_title}. Your presence, prayers, and support brought great comfort to our family. A note from us: {link}'
            : 'Thank you {guest_name} for joining us at {event_title}! We are truly grateful for your presence. A note from us: {link}';

        $defaultMessageAm = $isFuneral
            ? 'ውድ {guest_name}፣ በ{event_title} ዝግጅት ላይ አብረውን ስለነበሩ እና ላሳዩን አጋርነት ከልብ እናመሰግናለን። ከእኛ የተላከ መልእክት፡ {link}'
            : 'ውድ {guest_name}፣ በ{event_title} ዝግጅት ላይ አብረውን ስለተገኙ ከልብ እናመሰግናለን! መልእክታችንን ለማየት፡ {link}';

        $defaultSubject = $isFuneral
            ? 'Thank you for your presence and support - {event_title}'
            : 'Thank you for joining us! - {event_title}';

        // 4. Scheduling reference (default 12h after event ends)
        $baseEnd = $event->ends_at ?: $event->starts_at->addHours(4);
        $defaultScheduleTime = $baseEnd->copy()->addHours(12);

        // 5. Thank-you templates
        $templates = Template::where('is_active', true)
            ->where('kind', 'thank_you')
            ->orderBy('sort')
            ->get();

        return Inertia::render('Events/ThankYou', [
            'event' => $event->load(['category', 'thankyouTemplate']),
            'templates' => $templates,
            'hasDesign' => ! empty($event->thankyou_design_json),
            'defaultAudience' => $defaultAudience,
            'audienceExplanation' => $explanation,
            'audienceCounts' => $audienceCounts,
            'consumesQuota' => $consumesQuota,
            'availableQuota' => $availableQuota,
            'defaultScheduleTime' => $defaultScheduleTime->toIso8601String(),
            'isScheduledDefault' => $defaultScheduleTime->isFuture(),
            'defaults' => [
                'message_en' => $defaultMessageEn,
                'message_am' => $defaultMessageAm,
                'subject' => $defaultSubject,
            ],
            'guests' => $activeGuests->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'phone' => $g->phone,
                'email' => $g->email,
                'checked_in' => ! is_null($g->checked_in_at),
                'rsvp_status' => $g->rsvp_status,
            ])->values(),
        ]);
    }
}
