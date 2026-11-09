<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\EntitlementService;
use App\Services\EthiopianCalendar;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected EntitlementService $entitlementService,
    ) {}

    /**
     * Display the user dashboard with their events and co-hosted events.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Owned events
        $events = Event::where('user_id', $user->id)
            ->with(['category', 'template'])
            ->withCount([
                'guests',
                'guests as guests_attending_count' => fn ($q) => $q->where('rsvp_status', 'yes'),
                'guests as guests_checked_in_count' => fn ($q) => $q->whereNotNull('checked_in_at'),
            ])
            ->latest()
            ->get()
            ->map(function ($event) {
                $event->ethiopian_date = EthiopianCalendar::fromGregorian($event->starts_at);
                return $event;
            });

        // Co-hosted events
        $coHostEvents = Event::whereHas('members', fn ($q) => $q->where('user_id', $user->id))
            ->with(['category', 'template', 'user'])
            ->withCount([
                'guests',
                'guests as guests_attending_count' => fn ($q) => $q->where('rsvp_status', 'yes'),
            ])
            ->latest()
            ->get()
            ->map(function ($event) use ($user) {
                $event->ethiopian_date = EthiopianCalendar::fromGregorian($event->starts_at);
                $event->my_role = $event->members()->where('user_id', $user->id)->value('role');
                return $event;
            });

        $maxEvents = $this->entitlementService->limit($user, 'max_events');
        $activeEventsCount = $user->events()->where('status', '!=', 'cancelled')->count();
        $canCreateEvent = $maxEvents === null || $activeEventsCount < $maxEvents;
        $canThankYou = $this->entitlementService->can($user, 'thank_you');

        return Inertia::render('Dashboard', [
            'events' => $events,
            'coHostEvents' => $coHostEvents,
            'canCreateEvent' => $canCreateEvent,
            'canThankYou' => $canThankYou,
            'maxEvents' => $maxEvents,
            'activeEventsCount' => $activeEventsCount,
        ]);
    }
}
