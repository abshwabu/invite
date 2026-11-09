<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Scanner;
use App\Services\CheckinService;
use App\Services\EntitlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ScannerController extends Controller
{
    public function __construct(
        protected EntitlementService $entitlementService,
        protected CheckinService $checkinService,
    ) {}

    /**
     * Display the scanner management screen for the event host.
     */
    public function index(Request $request, Event $event): Response
    {
        Gate::authorize('view', $event);

        $user = $request->user();
        $canQrCheckin = $this->entitlementService->can($user, 'qr_checkin');
        $maxScanners = $this->entitlementService->limit($user, 'max_scanners_per_event');

        $scanners = $event->scanners()
            ->latest()
            ->get()
            ->map(function (Scanner $scanner) {
                return [
                    'id' => $scanner->id,
                    'name' => $scanner->name,
                    'token' => $scanner->token,
                    'url' => url("/scan/{$scanner->token}"),
                    'is_revoked' => $scanner->isRevoked(),
                    'revoked_at' => $scanner->revoked_at?->toIso8601String(),
                    'is_locked' => $scanner->isLocked(),
                    'locked_until' => $scanner->locked_until?->toIso8601String(),
                    'failed_attempts' => $scanner->failed_attempts,
                    'last_used_at' => $scanner->last_used_at?->toIso8601String(),
                    'created_at' => $scanner->created_at->toIso8601String(),
                ];
            });

        $activeScannersCount = $scanners->where('is_revoked', false)->count();

        $recentLogs = $event->checkinLogs()
            ->with(['guest', 'scanner'])
            ->latest()
            ->take(30)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'result' => $log->result,
                    'guest_name' => $log->guest?->name ?: 'Unknown',
                    'scanner_name' => $log->scanner?->name ?: 'System / Host',
                    'raw_payload' => $log->raw_payload,
                    'scanned_at' => $log->scanned_at ? $log->scanned_at->toIso8601String() : $log->created_at->toIso8601String(),
                ];
            });

        // Offline conflict alerts flagged in audit logs
        $conflictLogs = AuditLog::where('action', 'offline_checkin_conflict')
            ->where('meta->event_id', $event->id)
            ->latest()
            ->take(10)
            ->get();

        $stats = [
            'total_guests' => $event->guests()->count(),
            'checked_in' => $event->guests()->whereNotNull('checked_in_at')->count(),
            'active_scanners' => $activeScannersCount,
            'scanners_limit' => $maxScanners,
            'is_invite_only' => (bool) $event->is_invite_only,
        ];

        return Inertia::render('Events/Scanners', [
            'event' => $event->only(['id', 'title', 'starts_at', 'venue_name', 'is_invite_only', 'status']),
            'scanners' => $scanners,
            'recentLogs' => $recentLogs,
            'conflictLogs' => $conflictLogs,
            'stats' => $stats,
            'entitlements' => [
                'qr_checkin' => $canQrCheckin,
                'max_scanners' => $maxScanners,
                'limit_reached' => $maxScanners !== null && $activeScannersCount >= $maxScanners,
            ],
        ]);
    }

    /**
     * Store a new scanner for the event.
     */
    public function store(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('update', $event);

        $user = $request->user();

        // 1. Feature entitlement
        if (! $this->entitlementService->can($user, 'qr_checkin')) {
            throw ValidationException::withMessages([
                'qr_checkin' => 'QR Check-in is not included in your current plan. Please upgrade to create scanners.',
            ]);
        }

        // 2. Limit check
        $maxScanners = $this->entitlementService->limit($user, 'max_scanners_per_event');
        $activeCount = $event->scanners()->whereNull('revoked_at')->count();
        if ($maxScanners !== null && $activeCount >= $maxScanners) {
            throw ValidationException::withMessages([
                'scanner_limit' => "Your plan allows up to {$maxScanners} active scanner(s) per event. Please upgrade your plan to add more.",
            ]);
        }

        // 3. Validation
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'pin' => ['required', 'string', 'regex:/^[0-9]{4,6}$/'],
        ], [
            'pin.regex' => 'The PIN must be 4 to 6 numeric digits.',
        ]);

        $event->scanners()->create([
            'name' => $validated['name'],
            'pin_hash' => Hash::make($validated['pin']),
            'token' => Str::random(32),
        ]);

        return back()->with('status', 'Scanner created successfully.');
    }

    /**
     * Revoke an existing scanner.
     */
    public function revoke(Request $request, Event $event, Scanner $scanner): RedirectResponse
    {
        Gate::authorize('update', $event);

        if ($scanner->event_id !== $event->id) {
            abort(404);
        }

        $scanner->update([
            'revoked_at' => now(),
        ]);

        return back()->with('status', "Scanner {$scanner->name} has been revoked.");
    }

    /**
     * Host manual override to check in a guest.
     */
    public function manualCheckin(Request $request, Event $event, Guest $guest): RedirectResponse
    {
        Gate::authorize('update', $event);

        if ($guest->event_id !== $event->id) {
            abort(404);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->checkinService->manualCheckin(
            $request->user(),
            $event,
            $guest,
            $validated['reason']
        );

        return back()->with('status', "Guest {$guest->name} checked in manually.");
    }

    /**
     * Host manual override to undo a guest check-in.
     */
    public function undoCheckin(Request $request, Event $event, Guest $guest): RedirectResponse
    {
        Gate::authorize('update', $event);

        if ($guest->event_id !== $event->id) {
            abort(404);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->checkinService->undoCheckin(
            $request->user(),
            $event,
            $guest,
            $validated['reason']
        );

        return back()->with('status', "Check-in undone for {$guest->name}.");
    }
}
