<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Event;
use App\Models\Template;
use App\Services\DesignValidator;
use App\Services\EntitlementService;
use App\Services\EthiopianCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function __construct(
        protected EntitlementService $entitlementService,
    ) {}

    /**
     * Show the 3-step event creation wizard.
     */
    public function create(Request $request): Response
    {
        $user = $request->user();
        $maxEvents = $this->entitlementService->limit($user, 'max_events');
        $activeEventsCount = $user->events()->where('status', '!=', 'cancelled')->count();
        $canCreate = $maxEvents === null || $activeEventsCount < $maxEvents;

        $categories = Category::with([
            'templates' => fn ($query) => $query->where('is_active', true)->where('kind', 'invitation')->orderBy('sort'),
        ])->orderBy('sort')->get();

        $canQrCheckin = $this->entitlementService->can($user, 'qr_checkin');

        return Inertia::render('Events/Create', [
            'categories' => $categories,
            'canCreate' => $canCreate,
            'maxEvents' => $maxEvents,
            'activeEventsCount' => $activeEventsCount,
            'canQrCheckin' => $canQrCheckin,
            'timezones' => [
                'Africa/Addis_Ababa',
                'Africa/Nairobi',
                'UTC',
                'Europe/London',
                'America/New_York',
                'Asia/Dubai',
            ],
        ]);
    }

    /**
     * Store a newly created event in storage.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        // 1. Enforce max_events limit from the tier
        $maxEvents = $this->entitlementService->limit($user, 'max_events');
        if ($maxEvents !== null) {
            $activeCount = $user->events()->where('status', '!=', 'cancelled')->count();
            if ($activeCount >= $maxEvents) {
                throw ValidationException::withMessages([
                    'title' => __("You have reached your plan limit of :limit active event(s). Please upgrade your plan to create more.", ['limit' => $maxEvents]),
                ]);
            }
        }

        // 2. Validate request
        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'host_names' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'timezone' => ['required', 'string', 'max:64'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_address' => ['nullable', 'string', 'max:500'],
            'map_url' => ['nullable', 'url', 'max:1000'],
            'is_invite_only' => ['boolean'],
            'rsvp_enabled' => ['boolean'],
            'plus_ones_allowed' => ['boolean'],
            'template_id' => ['nullable', 'exists:templates,id'],
        ]);

        $category = Category::find($validated['category_id']);
        $isFuneral = $category && $category->slug === 'funeral';

        // 3. Funeral category behavior: RSVP and plus-ones off by default
        if ($isFuneral) {
            if (! isset($request->rsvp_enabled)) {
                $validated['rsvp_enabled'] = false;
            }
            if (! isset($request->plus_ones_allowed)) {
                $validated['plus_ones_allowed'] = false;
            }
            if (! isset($request->is_invite_only)) {
                $validated['is_invite_only'] = false;
            }
        }

        // 4. Verify QR check-in entitlement if invite-only is requested
        $canQrCheckin = $this->entitlementService->can($user, 'qr_checkin');
        if (! empty($validated['is_invite_only']) && ! $canQrCheckin) {
            $validated['is_invite_only'] = false;
        }

        // 5. Initialize design JSON from template or blank
        $template = ! empty($validated['template_id']) ? Template::find($validated['template_id']) : null;
        $designJson = $template?->design_json ?? DesignValidator::blankDesign();

        $event = Event::create([
            'user_id' => $user->id,
            'category_id' => $validated['category_id'],
            'template_id' => $template?->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'] ?? null,
            'timezone' => $validated['timezone'] ?? 'Africa/Addis_Ababa',
            'venue_name' => $validated['venue_name'] ?? null,
            'venue_address' => $validated['venue_address'] ?? null,
            'map_url' => $validated['map_url'] ?? null,
            'host_names' => $validated['host_names'] ?? null,
            'is_invite_only' => $validated['is_invite_only'] ?? true,
            'rsvp_enabled' => $validated['rsvp_enabled'] ?? true,
            'plus_ones_allowed' => $validated['plus_ones_allowed'] ?? false,
            'design_json' => $designJson,
            'status' => 'draft',
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'event_id' => $event->id,
                'redirect' => route('events.design', ['event' => $event->id]),
            ]);
        }

        return redirect()->route('events.design', ['event' => $event->id])
            ->with('success', __('Event created successfully. Now customize your invitation card!'));
    }

    /**
     * Show event edit details form.
     */
    public function edit(Request $request, Event $event): Response
    {
        Gate::authorize('update', $event);

        $categories = Category::orderBy('sort')->get();
        $canQrCheckin = $this->entitlementService->can($request->user(), 'qr_checkin');

        return Inertia::render('Events/Edit', [
            'event' => $event->load('category'),
            'categories' => $categories,
            'canQrCheckin' => $canQrCheckin,
            'ethiopianDate' => EthiopianCalendar::fromGregorian($event->starts_at),
        ]);
    }

    /**
     * Update event details.
     */
    public function update(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('update', $event);

        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'host_names' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'timezone' => ['required', 'string', 'max:64'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_address' => ['nullable', 'string', 'max:500'],
            'map_url' => ['nullable', 'url', 'max:1000'],
            'is_invite_only' => ['boolean'],
            'rsvp_enabled' => ['boolean'],
            'plus_ones_allowed' => ['boolean'],
            'status' => ['nullable', 'string', 'in:draft,published,completed,cancelled'],
        ]);

        $canQrCheckin = $this->entitlementService->can($request->user(), 'qr_checkin');
        if (! empty($validated['is_invite_only']) && ! $canQrCheckin) {
            $validated['is_invite_only'] = false;
        }

        $event->update($validated);

        return redirect()->route('dashboard')->with('success', __('Event details updated successfully.'));
    }

    /**
     * Duplicate an existing event.
     */
    public function duplicate(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('duplicate', $event);

        $user = $request->user();

        // Enforce max_events limit
        $maxEvents = $this->entitlementService->limit($user, 'max_events');
        if ($maxEvents !== null) {
            $activeCount = $user->events()->where('status', '!=', 'cancelled')->count();
            if ($activeCount >= $maxEvents) {
                return back()->with('error', __("Cannot duplicate event. You have reached your plan limit of :limit active event(s).", ['limit' => $maxEvents]));
            }
        }

        $cloned = $event->replicate([
            'status',
            'created_at',
            'updated_at',
            'public_slug',
        ]);

        $cloned->title = 'Copy of ' . $event->title;
        $cloned->status = 'draft';
        $cloned->save();

        return redirect()->route('dashboard')->with('success', __('Event duplicated successfully as draft.'));
    }

    /**
     * Delete an event and all associated records.
     */
    public function destroy(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('delete', $event);

        $event->delete();

        return redirect()->route('dashboard')->with('success', __('Event deleted successfully.'));
    }
}
