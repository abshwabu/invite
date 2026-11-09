<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Event;
use App\Models\ShortLink;
use App\Services\AuditLogService;
use App\Services\EntitlementService;
use App\Services\EthiopianCalendar;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EventDashboardController extends Controller
{
    public function __construct(
        protected EntitlementService $entitlementService,
    ) {}

    /**
     * Show event overview dashboard.
     */
    public function show(Request $request, Event $event): Response
    {
        Gate::authorize('view', $event);

        $user = $request->user();

        // 1. Core Event Totals
        $guestsTotal = $event->guests()->count();
        $rsvpYes = $event->guests()->where('rsvp_status', 'yes')->count();
        $rsvpNo = $event->guests()->where('rsvp_status', 'no')->count();
        $rsvpMaybe = $event->guests()->where('rsvp_status', 'maybe')->count();
        $rsvpPending = $event->guests()->where('rsvp_status', 'pending')->count();

        // Expected headcount using party size of attending guests (min 1 per guest)
        $expectedHeadcount = $event->guests()
            ->where('rsvp_status', 'yes')
            ->get()
            ->sum(fn ($g) => max(1, (int) $g->rsvp_party_size));

        $openedCount = $event->guests()->whereNotNull('viewed_at')->count();

        // Delivery stats from event campaigns
        $campaignIds = $event->campaigns()->pluck('id');

        $sentCount = Delivery::whereIn('campaign_id', $campaignIds)
            ->whereIn('status', ['sent', 'delivered'])
            ->count();

        $deliveredCount = Delivery::whereIn('campaign_id', $campaignIds)
            ->where('status', 'delivered')
            ->count();

        $failedCount = Delivery::whereIn('campaign_id', $campaignIds)
            ->where('status', 'failed')
            ->count();

        // 2. Check-in totals & recent arrivals
        $checkedInCount = $event->guests()->whereNotNull('checked_in_at')->count();
        $checkinPercentage = $guestsTotal > 0 ? (int) round(($checkedInCount / $guestsTotal) * 100) : 0;

        $recentArrivals = $event->guests()
            ->whereNotNull('checked_in_at')
            ->with('checkedInByScanner')
            ->orderBy('checked_in_at', 'desc')
            ->take(10)
            ->get()
            ->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'party_size' => max(1, (int) ($g->rsvp_party_size ?: $g->max_party_size)),
                'checked_in_at' => $g->checked_in_at->toIso8601String(),
                'checked_in_time' => $g->checked_in_at->format('g:i:s A'),
                'scanner_name' => $g->checkedInByScanner?->name,
            ]);

        // 3. Chart Data: Sends over time (by date & channel)
        $sendsTimeline = $this->buildSendsTimeline($campaignIds);

        // 4. Chart Data: Link clicks from custom & campaign short links
        $linkClicks = ShortLink::whereIn('campaign_id', $campaignIds)
            ->orderBy('clicks', 'desc')
            ->take(10)
            ->get()
            ->map(fn ($link) => [
                'code' => $link->code,
                'target_url' => $link->target_url,
                'clicks' => $link->clicks,
            ]);

        $totalLinkClicks = $linkClicks->sum('clicks');

        // 5. Configured Reminders
        $reminders = $event->reminders()
            ->with('campaign')
            ->orderBy('scheduled_at')
            ->get();

        // 6. Entitlements
        $canReminders = $this->entitlementService->can($user, 'reminders');
        $canQrCheckin = $this->entitlementService->can($user, 'qr_checkin');
        $canThankYou = $this->entitlementService->can($user, 'thank_you');

        $ethiopianDate = EthiopianCalendar::fromGregorian($event->starts_at);

        return Inertia::render('Events/Dashboard', [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'status' => $event->status,
                'starts_at' => $event->starts_at?->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
                'timezone' => $event->timezone,
                'venue_name' => $event->venue_name,
                'venue_address' => $event->venue_address,
                'map_url' => $event->map_url,
                'host_names' => $event->host_names,
                'is_invite_only' => (bool) $event->is_invite_only,
                'rsvp_enabled' => (bool) $event->rsvp_enabled,
                'category' => $event->category,
                'public_slug' => $event->public_slug,
                'ethiopian_date' => $ethiopianDate,
            ],
            'totals' => [
                'guests' => $guestsTotal,
                'sent' => $sentCount,
                'delivered' => $deliveredCount,
                'failed' => $failedCount,
                'opened' => $openedCount,
                'rsvp_yes' => $rsvpYes,
                'rsvp_no' => $rsvpNo,
                'rsvp_maybe' => $rsvpMaybe,
                'rsvp_pending' => $rsvpPending,
                'expected_headcount' => $expectedHeadcount,
                'checked_in' => $checkedInCount,
                'checkin_percentage' => $checkinPercentage,
                'total_link_clicks' => $totalLinkClicks,
            ],
            'recentArrivals' => $recentArrivals,
            'charts' => [
                'sendsTimeline' => $sendsTimeline,
                'rsvpBreakdown' => [
                    'labels' => [__('Attending (Yes)'), __('Declined (No)'), __('Maybe'), __('Pending')],
                    'data' => [$rsvpYes, $rsvpNo, $rsvpMaybe, $rsvpPending],
                    'colors' => ['#10B981', '#EF4444', '#F59E0B', '#9CA3AF'],
                ],
                'linkClicks' => $linkClicks,
            ],
            'reminders' => $reminders,
            'entitlements' => [
                'can_reminders' => $canReminders,
                'can_qr_checkin' => $canQrCheckin,
                'can_thank_you' => $canThankYou,
            ],
        ]);
    }

    /**
     * Poll endpoint for live check-in monitor (polled every 5s).
     */
    public function liveCheckins(Request $request, Event $event): JsonResponse
    {
        Gate::authorize('view', $event);

        $guestsTotal = $event->guests()->count();
        $checkedInCount = $event->guests()->whereNotNull('checked_in_at')->count();
        $checkinPercentage = $guestsTotal > 0 ? (int) round(($checkedInCount / $guestsTotal) * 100) : 0;

        $expectedHeadcount = $event->guests()
            ->where('rsvp_status', 'yes')
            ->get()
            ->sum(fn ($g) => max(1, (int) $g->rsvp_party_size));

        $recentArrivals = $event->guests()
            ->whereNotNull('checked_in_at')
            ->with('checkedInByScanner')
            ->orderBy('checked_in_at', 'desc')
            ->take(15)
            ->get()
            ->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'party_size' => max(1, (int) ($g->rsvp_party_size ?: $g->max_party_size)),
                'checked_in_at' => $g->checked_in_at->toIso8601String(),
                'checked_in_time' => $g->checked_in_at->format('g:i:s A'),
                'scanner_name' => $g->checkedInByScanner?->name ?? 'Scanner',
            ]);

        return response()->json([
            'checked_in_count' => $checkedInCount,
            'total_guests' => $guestsTotal,
            'percentage' => $checkinPercentage,
            'expected_headcount' => $expectedHeadcount,
            'recent_arrivals' => $recentArrivals,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Manually close the event.
     */
    public function closeEvent(Request $request, Event $event): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $event);

        $event->update(['status' => 'closed']);

        AuditLogService::log(
            action: 'event.closed_manually',
            actor: $request->user(),
            subject: $event,
            meta: ['status' => 'closed']
        );

        $message = __('Event has been closed. Scanner admission and RSVP changes are now disabled.');

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }

    /**
     * Reopen a closed event.
     */
    public function reopenEvent(Request $request, Event $event): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $event);

        $event->update(['status' => 'published']);

        AuditLogService::log(
            action: 'event.reopened',
            actor: $request->user(),
            subject: $event,
            meta: ['status' => 'published']
        );

        $message = __('Event has been reopened.');

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }

    /**
     * Build aggregated timeline of delivery sends.
     *
     * @param \Illuminate\Support\Collection<int, int> $campaignIds
     * @return array{labels: array<int, string>, sms: array<int, int>, email: array<int, int>}
     */
    protected function buildSendsTimeline($campaignIds): array
    {
        $deliveries = Delivery::whereIn('campaign_id', $campaignIds)
            ->whereNotNull('created_at')
            ->select(
                DB::raw("date(created_at) as send_date"),
                'channel',
                DB::raw("count(*) as count")
            )
            ->groupBy('send_date', 'channel')
            ->orderBy('send_date')
            ->get();

        $dates = $deliveries->pluck('send_date')->unique()->sort()->values();

        // If no deliveries yet, show past 7 days with zeros
        if ($dates->isEmpty()) {
            $dates = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->format('Y-m-d'));
        }

        $smsMap = $deliveries->where('channel', 'sms')->pluck('count', 'send_date');
        $emailMap = $deliveries->where('channel', 'email')->pluck('count', 'send_date');

        $labels = [];
        $smsData = [];
        $emailData = [];

        foreach ($dates as $date) {
            $labels[] = Carbon::parse($date)->format('M d');
            $smsData[] = (int) ($smsMap[$date] ?? 0);
            $emailData[] = (int) ($emailMap[$date] ?? 0);
        }

        return [
            'labels' => $labels,
            'sms' => $smsData,
            'email' => $emailData,
        ];
    }
}
