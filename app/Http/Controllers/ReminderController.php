<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Reminder;
use App\Services\EntitlementService;
use App\Services\QuotaService;
use App\Services\ReminderService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReminderController extends Controller
{
    public function __construct(
        protected ReminderService $reminderService,
        protected EntitlementService $entitlementService,
        protected QuotaService $quotaService,
    ) {}

    /**
     * Show reminder configuration page for the event.
     */
    public function index(Request $request, Event $event): Response
    {
        Gate::authorize('view', $event);

        $user = $request->user();
        $canReminders = $this->entitlementService->can($user, 'reminders');

        $reminders = $event->reminders()
            ->with('campaign')
            ->orderBy('scheduled_at')
            ->get();

        $availableQuota = $this->quotaService->available($user);

        // Calculate sample estimated costs for 100% attendance
        $totalGuests = $event->guests()->where('opted_out', false)->count();
        $pendingGuests = $event->guests()->where('opted_out', false)->where('rsvp_status', 'pending')->count();
        $yesGuests = $event->guests()->where('opted_out', false)->where('rsvp_status', 'yes')->count();

        // Sample preset times
        $presetTimes = [
            '3_days_before' => $this->reminderService->calculateScheduledAt($event, '3_days_before')->toIso8601String(),
            '1_day_before' => $this->reminderService->calculateScheduledAt($event, '1_day_before')->toIso8601String(),
            'morning_of' => $this->reminderService->calculateScheduledAt($event, 'morning_of')->toIso8601String(),
        ];

        return Inertia::render('Events/Reminders', [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'starts_at' => $event->starts_at?->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
                'timezone' => $event->timezone,
                'venue_name' => $event->venue_name,
                'host_names' => $event->host_names,
                'status' => $event->status,
            ],
            'reminders' => $reminders,
            'canReminders' => $canReminders,
            'availableQuota' => $availableQuota,
            'audienceCounts' => [
                'all' => $totalGuests,
                'rsvp_pending' => $pendingGuests,
                'rsvp_yes' => $yesGuests,
            ],
            'presetTimes' => $presetTimes,
        ]);
    }

    /**
     * Store a new reminder schedule.
     */
    public function store(Request $request, Event $event): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $event);

        $user = $request->user();

        if (! $this->entitlementService->can($user, 'reminders')) {
            throw ValidationException::withMessages([
                'preset' => __('Automated reminders are available on paid tiers. Please upgrade your plan to unlock reminders.'),
            ]);
        }

        $validated = $request->validate([
            'preset' => ['required', 'string', 'in:3_days_before,1_day_before,morning_of,custom'],
            'channel' => ['required', 'string', 'in:sms,email,both'],
            'target_audience' => ['required', 'string', 'in:rsvp_pending,all,rsvp_yes'],
            'custom_scheduled_at' => ['nullable', 'required_if:preset,custom', 'date'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message_body' => ['nullable', 'string', 'max:1000'],
        ]);

        $scheduledAt = $this->reminderService->calculateScheduledAt(
            $event,
            $validated['preset'],
            $validated['custom_scheduled_at'] ?? null
        );

        $reminder = Reminder::create([
            'event_id' => $event->id,
            'preset' => $validated['preset'],
            'channel' => $validated['channel'],
            'target_audience' => $validated['target_audience'],
            'scheduled_at' => $scheduledAt,
            'status' => 'pending',
            'subject' => $validated['subject'] ?? "Reminder: {$event->title}",
            'message_body' => $validated['message_body'] ?? "Reminder for {guest_name}: {event_title} is taking place on {date} at {time}. View details: {link}",
        ]);

        $cost = $this->reminderService->estimateCost($reminder);

        $message = __('Reminder scheduled for :time. Estimated cost: :cards card(s).', [
            'time' => $scheduledAt->format('M d, Y g:i A'),
            'cards' => $cost,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'reminder' => $reminder,
                'estimated_cost' => $cost,
                'message' => $message,
            ]);
        }

        return redirect()->route('events.reminders.index', $event->id)->with('success', $message);
    }

    /**
     * Cancel / delete a reminder.
     */
    public function destroy(Request $request, Event $event, Reminder $reminder): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $event);

        if ($reminder->event_id !== $event->id) {
            abort(404);
        }

        if ($reminder->status === 'pending') {
            $reminder->update(['status' => 'cancelled']);
        }

        $message = __('Reminder has been cancelled.');

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }
}
