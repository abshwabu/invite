<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\Scanner;
use App\Services\CheckinService;
use App\Services\QrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class ScanController extends Controller
{
    public function __construct(
        protected CheckinService $checkinService,
    ) {}

    /**
     * Resolve the scanner from request headers, parameters, or active session.
     */
    protected function resolveScanner(Request $request): ?Scanner
    {
        $token = $request->header('X-Scanner-Token') ?? $request->input('scanner_token');

        if ($token) {
            return Scanner::with('event')->where('token', $token)->first();
        }

        // Check active session keys
        $allSession = $request->session()->all();
        foreach ($allSession as $key => $val) {
            if (str_starts_with($key, 'scanner_auth_') && $val === true) {
                $scannerId = (int) str_replace('scanner_auth_', '', $key);

                return Scanner::with('event')->find($scannerId);
            }
        }

        return null;
    }

    /**
     * Show scanner PWA / mobile entrance page.
     */
    public function show(Request $request, string $token): Response
    {
        $scanner = Scanner::with('event')->where('token', $token)->firstOrFail();

        if ($scanner->isRevoked()) {
            return Inertia::render('Scanners/Revoked', [
                'scannerName' => $scanner->name,
                'eventTitle' => $scanner->event?->title ?: 'Event',
            ]);
        }

        if ($scanner->isLocked()) {
            return Inertia::render('Scanners/Locked', [
                'scannerName' => $scanner->name,
                'lockedUntil' => $scanner->locked_until->toIso8601String(),
                'remainingMinutes' => $scanner->remainingLockoutMinutes(),
            ]);
        }

        $sessionKey = "scanner_auth_{$scanner->id}";
        if ($request->session()->get($sessionKey) !== true) {
            return Inertia::render('Scanners/Auth', [
                'token' => $scanner->token,
                'scannerName' => $scanner->name,
                'eventTitle' => $scanner->event?->title ?: 'Event',
                'failedAttempts' => $scanner->failed_attempts,
            ]);
        }

        $event = $scanner->event;

        return Inertia::render('Scanners/Scanner', [
            'scanner' => [
                'id' => $scanner->id,
                'name' => $scanner->name,
                'token' => $scanner->token,
            ],
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'starts_at' => $event->starts_at?->toIso8601String(),
                'venue_name' => $event->venue_name,
                'status' => $event->status,
                'is_invite_only' => (bool) $event->is_invite_only,
            ],
            'stats' => [
                'total_guests' => $event->guests()->count(),
                'checked_in' => $event->guests()->whereNotNull('checked_in_at')->count(),
            ],
        ]);
    }

    /**
     * Authenticate scanner with PIN.
     */
    public function authenticate(Request $request, string $token): RedirectResponse
    {
        $scanner = Scanner::where('token', $token)->firstOrFail();

        if ($scanner->isRevoked()) {
            abort(403, 'Scanner has been revoked.');
        }

        if ($scanner->isLocked()) {
            return back()->withErrors([
                'pin' => "Scanner is locked until {$scanner->locked_until?->format('H:i')}. Please wait {$scanner->remainingLockoutMinutes()} minute(s).",
            ]);
        }

        $validated = $request->validate([
            'pin' => ['required', 'string', 'regex:/^[0-9]{4,6}$/'],
        ], [
            'pin.regex' => 'The PIN must be 4 to 6 numeric digits.',
        ]);

        if (! Hash::check($validated['pin'], $scanner->pin_hash)) {
            $scanner->recordFailedAttempt();

            if ($scanner->isLocked()) {
                return back()->withErrors([
                    'pin' => 'Too many failed attempts. Scanner is locked for 15 minutes.',
                ]);
            }

            $remaining = 5 - $scanner->failed_attempts;

            return back()->withErrors([
                'pin' => "Invalid PIN. {$remaining} attempt(s) remaining before lockout.",
            ]);
        }

        $scanner->recordSuccessfulLogin();
        $request->session()->put("scanner_auth_{$scanner->id}", true);

        return redirect()->route('scanner.show', ['token' => $scanner->token]);
    }

    /**
     * Log out of scanner session.
     */
    public function logout(Request $request, string $token): RedirectResponse
    {
        $scanner = Scanner::where('token', $token)->firstOrFail();
        $request->session()->forget("scanner_auth_{$scanner->id}");

        return redirect()->route('scanner.show', ['token' => $scanner->token]);
    }

    /**
     * Check in an admission QR code.
     */
    public function scan(Request $request): JsonResponse
    {
        $scanner = $this->resolveScanner($request);

        if (! $scanner) {
            return response()->json([
                'status' => 'unauthorized',
                'message' => 'Scanner authentication required.',
            ], 401);
        }

        if ($scanner->isRevoked()) {
            return response()->json([
                'status' => 'revoked',
                'message' => 'Scanner has been revoked.',
            ], 403);
        }

        if ($scanner->isLocked()) {
            return response()->json([
                'status' => 'locked',
                'message' => 'Scanner is temporarily locked.',
            ], 429);
        }

        $qrPayload = $request->input('qr_payload');
        $guestId = $request->input('guest_id');

        // Fallback for manual check-in from scanner search UI
        if (! $qrPayload && $guestId) {
            $guest = Guest::where('event_id', $scanner->event_id)->find($guestId);
            if (! $guest) {
                return response()->json([
                    'status' => 'invalid',
                    'message' => 'Guest not found for this event.',
                ], 422);
            }
            $qrPayload = QrService::generatePayload($scanner->event_id, $guest->id);
        }

        if (! $qrPayload) {
            return response()->json([
                'status' => 'invalid',
                'message' => 'Missing QR payload.',
            ], 422);
        }

        $result = $this->checkinService->checkin(
            $scanner,
            $qrPayload,
            $request->input('scanned_at'),
            $request->input('device_info'),
            $request->ip()
        );

        return response()->json($result, $result['code'] ?? 200);
    }

    /**
     * Search guests for manual checkin fallback.
     */
    public function searchGuests(Request $request): JsonResponse
    {
        $scanner = $this->resolveScanner($request);

        if (! $scanner || $scanner->isRevoked() || $scanner->isLocked()) {
            return response()->json(['guests' => []], 403);
        }

        $query = (string) $request->input('query', '');

        $guests = Guest::where('event_id', $scanner->event_id)
            ->when($query !== '', function ($q) use ($query) {
                $q->where(function ($sub) use ($query) {
                    $sub->where('name', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%");
                });
            })
            ->latest()
            ->take(20)
            ->get()
            ->map(function (Guest $guest) {
                return [
                    'id' => $guest->id,
                    'name' => $guest->name,
                    'phone' => $guest->phone,
                    'party_size' => $guest->rsvp_party_size ?: $guest->max_party_size,
                    'is_checked_in' => ! is_null($guest->checked_in_at),
                    'checked_in_at' => $guest->checked_in_at?->toIso8601String(),
                    'checked_in_by' => $guest->checked_in_by,
                ];
            });

        return response()->json(['guests' => $guests]);
    }

    /**
     * Provide offline manifest of guest identifiers for local validation.
     */
    public function offlineManifest(Request $request): JsonResponse
    {
        $scanner = $this->resolveScanner($request);

        if (! $scanner || $scanner->isRevoked() || $scanner->isLocked()) {
            return response()->json(['error' => 'Unauthorized scanner'], 403);
        }

        $guests = Guest::where('event_id', $scanner->event_id)
            ->get()
            ->map(function (Guest $guest) use ($scanner) {
                return [
                    'id' => $guest->id,
                    'name' => $guest->name,
                    'party_size' => $guest->rsvp_party_size ?: $guest->max_party_size,
                    'checked_in_at' => $guest->checked_in_at?->toIso8601String(),
                    'checked_in_by' => $guest->checked_in_by,
                ];
            });

        return response()->json([
            'event_id' => $scanner->event_id,
            'event_title' => $scanner->event->title,
            'generated_at' => now()->toIso8601String(),
            'guests' => $guests,
        ]);
    }

    /**
     * Sync queued offline check-ins.
     */
    public function sync(Request $request): JsonResponse
    {
        $scanner = $this->resolveScanner($request);

        if (! $scanner || $scanner->isRevoked() || $scanner->isLocked()) {
            return response()->json(['error' => 'Unauthorized scanner'], 403);
        }

        $scans = $request->input('scans', []);
        $results = [];
        $syncedCount = 0;
        $conflictCount = 0;

        foreach ($scans as $scan) {
            $qrPayload = $scan['qr_payload'] ?? null;
            if (! $qrPayload) {
                continue;
            }

            $res = $this->checkinService->checkin(
                $scanner,
                $qrPayload,
                $scan['scanned_at'] ?? null,
                $scan['device_info'] ?? null,
                $request->ip()
            );

            $results[] = $res;
            if (($res['status'] ?? '') === 'success') {
                $syncedCount++;
            } elseif (($res['status'] ?? '') === 'conflict_resolved') {
                $syncedCount++;
                $conflictCount++;
            }
        }

        return response()->json([
            'status' => 'ok',
            'synced' => $syncedCount,
            'conflicts' => $conflictCount,
            'results' => $results,
        ]);
    }
}
