<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Guest;
use App\Services\EntitlementService;
use App\Services\PhoneNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GuestController extends Controller
{
    public function __construct(
        protected EntitlementService $entitlementService,
    ) {}

    /**
     * Display guest list UI for an event.
     */
    public function index(Request $request, Event $event): Response
    {
        Gate::authorize('view', $event);

        $query = $event->guests();

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter: RSVP Status
        if ($rsvp = $request->input('rsvp_status')) {
            if (in_array($rsvp, ['pending', 'yes', 'no', 'maybe'], true)) {
                $query->where('rsvp_status', $rsvp);
            }
        }

        // Filter: Checked In
        if ($request->has('checked_in') && $request->input('checked_in') !== null && $request->input('checked_in') !== '') {
            $isCheckedIn = filter_var($request->input('checked_in'), FILTER_VALIDATE_BOOLEAN);
            if ($isCheckedIn) {
                $query->whereNotNull('checked_in_at');
            } else {
                $query->whereNull('checked_in_at');
            }
        }

        // Filter: Preferred Channel
        if ($channel = $request->input('channel')) {
            if (in_array($channel, ['sms', 'email', 'both'], true)) {
                $query->where('preferred_channel', $channel);
            }
        }

        $guests = $query->orderBy('name')->get();

        // Calculate statistics
        $allGuests = $event->guests;
        $stats = [
            'total' => $allGuests->count(),
            'attending' => $allGuests->where('rsvp_status', 'yes')->count(),
            'declined' => $allGuests->where('rsvp_status', 'no')->count(),
            'maybe' => $allGuests->where('rsvp_status', 'maybe')->count(),
            'pending' => $allGuests->where('rsvp_status', 'pending')->count(),
            'checked_in' => $allGuests->whereNotNull('checked_in_at')->count(),
        ];

        $user = $request->user();
        $maxGuests = $this->entitlementService->limit($user, 'max_guests_per_event');
        $canCsvImport = $this->entitlementService->can($user, 'csv_import');
        $canCoHosts = $this->entitlementService->can($user, 'co_hosts');
        $canSms = $this->entitlementService->can($user, 'sms_channel');
        $canEmail = $this->entitlementService->can($user, 'email_channel');
        $canQrCheckin = $this->entitlementService->can($user, 'qr_checkin');

        $members = $event->members()->with('user')->get();

        return Inertia::render('Events/Guests', [
            'event' => $event->load(['category']),
            'guests' => $guests,
            'stats' => $stats,
            'limits' => [
                'max_guests_per_event' => $maxGuests,
                'current_guests_count' => $stats['total'],
            ],
            'features' => [
                'csv_import' => $canCsvImport,
                'co_hosts' => $canCoHosts,
                'sms_channel' => $canSms,
                'email_channel' => $canEmail,
                'qr_checkin' => $canQrCheckin,
            ],
            'members' => $members,
            'filters' => $request->only(['search', 'rsvp_status', 'checked_in', 'channel']),
        ]);
    }

    /**
     * Add a single guest manually.
     */
    public function store(Request $request, Event $event): JsonResponse|RedirectResponse
    {
        Gate::authorize('manageGuests', $event);

        // 1. Enforce max_guests_per_event tier limit
        $this->ensureWithinGuestLimit($request->user(), $event, 1);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'preferred_channel' => ['required', 'string', 'in:sms,email,both'],
            'max_party_size' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        if (empty($validated['phone']) && empty($validated['email'])) {
            throw ValidationException::withMessages([
                'phone' => __('At least a phone number or email address is required.'),
            ]);
        }

        // Normalize phone if provided
        $normalizedPhone = null;
        if (! empty($validated['phone'])) {
            $normalizedPhone = PhoneNormalizer::normalize($validated['phone']);
            if (! $normalizedPhone) {
                throw ValidationException::withMessages([
                    'phone' => __('Invalid Ethiopian mobile number. Expected format: 09XXXXXXXX, 07XXXXXXXX or +2519XXXXXXXX.'),
                ]);
            }
        }

        // Duplicate check within this event
        $duplicate = $event->guests()
            ->where(function ($q) use ($normalizedPhone, $validated) {
                if ($normalizedPhone) {
                    $q->where('phone', $normalizedPhone);
                }
                if (! empty($validated['email'])) {
                    if ($normalizedPhone) {
                        $q->orWhere('email', $validated['email']);
                    } else {
                        $q->where('email', $validated['email']);
                    }
                }
            })
            ->first();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' => __("A guest with this phone/email already exists (:name).", ['name' => $duplicate->name]),
            ]);
        }

        $guest = $event->guests()->create([
            'name' => $validated['name'],
            'phone' => $normalizedPhone,
            'email' => $validated['email'] ?? null,
            'preferred_channel' => $this->sanitizeChannel($request->user(), $validated['preferred_channel']),
            'max_party_size' => $validated['max_party_size'] ?? 1,
            'rsvp_status' => 'pending',
            'token' => Str::random(32),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'guest' => $guest,
                'message' => __('Guest added successfully.'),
            ]);
        }

        return back()->with('success', __('Guest added successfully.'));
    }

    /**
     * Bulk paste guests (format per line: "Name, phone/email" or "Name, phone, email, party_size").
     */
    public function bulkPaste(Request $request, Event $event): JsonResponse|RedirectResponse
    {
        Gate::authorize('manageGuests', $event);

        $validated = $request->validate([
            'text' => ['required', 'string'],
            'on_duplicate' => ['required', 'string', 'in:skip,merge'],
        ]);

        $lines = preg_split('/\r\n|\r|\n/', trim($validated['text']));
        $lines = array_filter(array_map('trim', $lines), fn ($line) => ! empty($line));

        if (empty($lines)) {
            throw ValidationException::withMessages([
                'text' => __('No valid guest lines found to import.'),
            ]);
        }

        $user = $request->user();
        $maxGuests = $this->entitlementService->limit($user, 'max_guests_per_event');
        $currentCount = $event->guests()->count();

        $addedCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $errors = [];

        foreach ($lines as $index => $line) {
            $lineNum = $index + 1;
            $parts = array_map('trim', str_getcsv($line));

            if (empty($parts[0])) {
                continue;
            }

            $name = $parts[0];
            $phone = null;
            $email = null;
            $partySize = 1;

            // Parse remaining parts
            for ($i = 1; $i < count($parts); $i++) {
                $val = $parts[$i];
                if (empty($val)) continue;

                if (is_numeric($val) && strlen($val) <= 2) {
                    $partySize = max(1, (int) $val);
                } elseif (filter_var($val, FILTER_VALIDATE_EMAIL)) {
                    $email = strtolower($val);
                } elseif (PhoneNormalizer::isValid($val)) {
                    $phone = PhoneNormalizer::normalize($val);
                }
            }

            if (! $phone && ! $email) {
                $errors[] = __("Line :line: Missing valid phone or email for ':name'.", ['line' => $lineNum, 'name' => $name]);
                continue;
            }

            // Duplicate check within event
            $existing = $event->guests()
                ->where(function ($q) use ($phone, $email) {
                    if ($phone) $q->where('phone', $phone);
                    if ($email) {
                        if ($phone) $q->orWhere('email', $email);
                        else $q->where('email', $email);
                    }
                })
                ->first();

            if ($existing) {
                if ($validated['on_duplicate'] === 'merge') {
                    $existing->update([
                        'name' => $name,
                        'phone' => $phone ?: $existing->phone,
                        'email' => $email ?: $existing->email,
                        'max_party_size' => $partySize,
                    ]);
                    $updatedCount++;
                } else {
                    $skippedCount++;
                }
                continue;
            }

            // Check tier limit before adding new guest
            if ($maxGuests !== null && ($currentCount + $addedCount + 1) > $maxGuests) {
                $errors[] = __("Reached plan limit of :limit guests per event. Additional guests were skipped.", ['limit' => $maxGuests]);
                break;
            }

            $defaultChannel = $phone && $email ? 'both' : ($phone ? 'sms' : 'email');

            $event->guests()->create([
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'preferred_channel' => $this->sanitizeChannel($user, $defaultChannel),
                'max_party_size' => $partySize,
                'rsvp_status' => 'pending',
                'token' => Str::random(32),
            ]);

            $addedCount++;
        }

        $message = __("Bulk paste processed: :added added, :updated merged, :skipped skipped.", [
            'added' => $addedCount,
            'updated' => $updatedCount,
            'skipped' => $skippedCount,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'added' => $addedCount,
                'updated' => $updatedCount,
                'skipped' => $skippedCount,
                'errors' => $errors,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message)->with('import_errors', $errors);
    }

    /**
     * CSV/XLSX file import (gated by csv_import tier feature).
     */
    public function import(Request $request, Event $event): JsonResponse|RedirectResponse
    {
        Gate::authorize('manageGuests', $event);

        $user = $request->user();

        // 1. Enforce csv_import entitlement
        if (! $this->entitlementService->can($user, 'csv_import')) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('CSV/XLSX import is not included in your current plan. Please upgrade to use bulk file imports.'),
                ], 403);
            }
            return back()->with('error', __('CSV/XLSX import is not included in your current plan. Please upgrade to use bulk file imports.'));
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
            'on_duplicate' => ['required', 'string', 'in:skip,merge'],
        ]);

        $file = $request->file('file');
        $content = file_get_contents($file->getRealPath());

        // Strip UTF-8 BOM if present (\xEF\xBB\xBF)
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        $lines = array_filter($lines, fn ($line) => trim($line) !== '');

        if (empty($lines)) {
            throw ValidationException::withMessages([
                'file' => __('Uploaded CSV file is empty.'),
            ]);
        }

        // Detect Header Row
        $header = array_map(fn ($col) => strtolower(trim($col)), str_getcsv(array_shift($lines)));

        $colName = -1;
        $colPhone = -1;
        $colEmail = -1;
        $colParty = -1;
        $colChannel = -1;

        foreach ($header as $i => $h) {
            if (in_array($h, ['name', 'full name', 'guest', 'guest name', 'ስም'], true)) {
                $colName = $i;
            } elseif (in_array($h, ['phone', 'mobile', 'telephone', 'ስልክ', 'cell'], true)) {
                $colPhone = $i;
            } elseif (in_array($h, ['email', 'e-mail', 'ኢሜይል'], true)) {
                $colEmail = $i;
            } elseif (in_array($h, ['party', 'party size', 'guests', 'seats', 'ብዛት'], true)) {
                $colParty = $i;
            } elseif (in_array($h, ['channel', 'preferred channel'], true)) {
                $colChannel = $i;
            }
        }

        // If no recognizable headers, fallback to column index: 0=name, 1=phone, 2=email, 3=party
        if ($colName === -1) $colName = 0;
        if ($colPhone === -1 && count($header) > 1) $colPhone = 1;
        if ($colEmail === -1 && count($header) > 2) $colEmail = 2;
        if ($colParty === -1 && count($header) > 3) $colParty = 3;

        $maxGuests = $this->entitlementService->limit($user, 'max_guests_per_event');
        $currentCount = $event->guests()->count();

        $addedCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $errors = [];

        foreach ($lines as $index => $line) {
            $rowNum = $index + 2; // account for header
            $row = str_getcsv($line);

            if (empty($row) || ! isset($row[$colName]) || trim($row[$colName]) === '') {
                continue;
            }

            $name = trim($row[$colName]);
            $rawPhone = isset($row[$colPhone]) ? trim($row[$colPhone]) : null;
            $rawEmail = isset($row[$colEmail]) ? trim($row[$colEmail]) : null;
            $rawParty = isset($row[$colParty]) ? trim($row[$colParty]) : 1;
            $rawChannel = isset($row[$colChannel]) ? strtolower(trim($row[$colChannel])) : null;

            $phone = null;
            if (! empty($rawPhone)) {
                $phone = PhoneNormalizer::normalize($rawPhone);
                if (! $phone && empty($rawEmail)) {
                    $errors[] = __("Row :row: Invalid phone number ':phone' for ':name'.", ['row' => $rowNum, 'phone' => $rawPhone, 'name' => $name]);
                    continue;
                }
            }

            $email = null;
            if (! empty($rawEmail)) {
                if (filter_var($rawEmail, FILTER_VALIDATE_EMAIL)) {
                    $email = strtolower($rawEmail);
                } elseif (! $phone) {
                    $errors[] = __("Row :row: Invalid email ':email' for ':name'.", ['row' => $rowNum, 'email' => $rawEmail, 'name' => $name]);
                    continue;
                }
            }

            if (! $phone && ! $email) {
                $errors[] = __("Row :row: Missing both valid phone and email for ':name'.", ['row' => $rowNum, 'name' => $name]);
                continue;
            }

            $partySize = is_numeric($rawParty) ? max(1, (int) $rawParty) : 1;

            // Duplicate check
            $existing = $event->guests()
                ->where(function ($q) use ($phone, $email) {
                    if ($phone) $q->where('phone', $phone);
                    if ($email) {
                        if ($phone) $q->orWhere('email', $email);
                        else $q->where('email', $email);
                    }
                })
                ->first();

            if ($existing) {
                if ($request->input('on_duplicate') === 'merge') {
                    $existing->update([
                        'name' => $name,
                        'phone' => $phone ?: $existing->phone,
                        'email' => $email ?: $existing->email,
                        'max_party_size' => $partySize,
                    ]);
                    $updatedCount++;
                } else {
                    $skippedCount++;
                }
                continue;
            }

            // Check tier limit before adding new guest
            if ($maxGuests !== null && ($currentCount + $addedCount + 1) > $maxGuests) {
                $errors[] = __("Reached plan limit of :limit guests per event. Remaining rows skipped.", ['limit' => $maxGuests]);
                break;
            }

            $channel = in_array($rawChannel, ['sms', 'email', 'both'], true)
                ? $rawChannel
                : ($phone && $email ? 'both' : ($phone ? 'sms' : 'email'));

            $event->guests()->create([
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'preferred_channel' => $this->sanitizeChannel($user, $channel),
                'max_party_size' => $partySize,
                'rsvp_status' => 'pending',
                'token' => Str::random(32),
            ]);

            $addedCount++;
        }

        $message = __("Import finished: :added added, :updated merged, :skipped skipped.", [
            'added' => $addedCount,
            'updated' => $updatedCount,
            'skipped' => $skippedCount,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'added' => $addedCount,
                'updated' => $updatedCount,
                'skipped' => $skippedCount,
                'errors' => $errors,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message)->with('import_errors', $errors);
    }

    /**
     * Inline update a guest.
     */
    public function update(Request $request, Event $event, Guest $guest): JsonResponse|RedirectResponse
    {
        Gate::authorize('manageGuests', $event);

        if ($guest->event_id !== $event->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'preferred_channel' => ['required', 'string', 'in:sms,email,both'],
            'max_party_size' => ['required', 'integer', 'min:1', 'max:50'],
            'rsvp_status' => ['required', 'string', 'in:pending,yes,no,maybe'],
        ]);

        $normalizedPhone = null;
        if (! empty($validated['phone'])) {
            $normalizedPhone = PhoneNormalizer::normalize($validated['phone']);
            if (! $normalizedPhone) {
                throw ValidationException::withMessages([
                    'phone' => __('Invalid Ethiopian mobile number.'),
                ]);
            }
        }

        $guest->update([
            'name' => $validated['name'],
            'phone' => $normalizedPhone,
            'email' => $validated['email'] ?? null,
            'preferred_channel' => $this->sanitizeChannel($request->user(), $validated['preferred_channel']),
            'max_party_size' => $validated['max_party_size'],
            'rsvp_status' => $validated['rsvp_status'],
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'guest' => $guest,
                'message' => __('Guest updated successfully.'),
            ]);
        }

        return back()->with('success', __('Guest updated successfully.'));
    }

    /**
     * Delete a single guest.
     */
    public function destroy(Request $request, Event $event, Guest $guest): JsonResponse|RedirectResponse
    {
        Gate::authorize('manageGuests', $event);

        if ($guest->event_id !== $event->id) {
            abort(404);
        }

        $guest->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('Guest deleted.'),
            ]);
        }

        return back()->with('success', __('Guest deleted.'));
    }

    /**
     * Bulk delete multiple guests.
     */
    public function bulkDelete(Request $request, Event $event): JsonResponse|RedirectResponse
    {
        Gate::authorize('manageGuests', $event);

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $count = $event->guests()->whereIn('id', $validated['ids'])->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'deleted' => $count,
                'message' => __(':count guests deleted successfully.', ['count' => $count]),
            ]);
        }

        return back()->with('success', __(':count guests deleted successfully.', ['count' => $count]));
    }

    /**
     * Bulk update preferred channel.
     */
    public function bulkChannel(Request $request, Event $event): JsonResponse|RedirectResponse
    {
        Gate::authorize('manageGuests', $event);

        $validated = $request->validate([
            'channel' => ['required', 'string', 'in:sms,email,both'],
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer'],
        ]);

        $channel = $this->sanitizeChannel($request->user(), $validated['channel']);

        $query = $event->guests();
        if (! empty($validated['ids'])) {
            $query->whereIn('id', $validated['ids']);
        }

        $count = $query->update(['preferred_channel' => $channel]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'updated' => $count,
                'message' => __(':count guests updated to :channel.', ['count' => $count, 'channel' => strtoupper($channel)]),
            ]);
        }

        return back()->with('success', __(':count guests updated to :channel.', ['count' => $count, 'channel' => strtoupper($channel)]));
    }

    /**
     * Export guest list as UTF-8 CSV with BOM for Amharic compatibility.
     */
    public function export(Request $request, Event $event): StreamedResponse
    {
        Gate::authorize('view', $event);

        $guests = $event->guests()->orderBy('name')->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s-guests.csv"', Str::slug($event->title ?: 'event')),
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($guests) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM so Excel opens Amharic characters properly
            fputs($handle, "\xEF\xBB\xBF");

            // CSV Header Row
            fputcsv($handle, [
                'Guest Name',
                'Phone',
                'Email',
                'Preferred Channel',
                'Party Size',
                'RSVP Status',
                'RSVP Party Size',
                'Checked In',
                'Checked In At',
                'Token',
            ]);

            foreach ($guests as $g) {
                fputcsv($handle, [
                    $g->name,
                    $g->phone ?: '',
                    $g->email ?: '',
                    $g->preferred_channel,
                    $g->max_party_size,
                    $g->rsvp_status,
                    $g->rsvp_party_size ?: '',
                    $g->checked_in_at ? 'Yes' : 'No',
                    $g->checked_in_at ? $g->checked_in_at->toIso8601String() : '',
                    $g->token,
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Ensure guest addition will not exceed max_guests_per_event.
     */
    protected function ensureWithinGuestLimit($user, Event $event, int $countToAdd): void
    {
        $maxGuests = $this->entitlementService->limit($user, 'max_guests_per_event');
        if ($maxGuests === null) {
            return;
        }

        $currentCount = $event->guests()->count();
        if (($currentCount + $countToAdd) > $maxGuests) {
            throw ValidationException::withMessages([
                'name' => __("Cannot add guest. Your plan limit is :limit guests per event (currently :current). Upgrade your plan to invite more.", [
                    'limit' => $maxGuests,
                    'current' => $currentCount,
                ]),
            ]);
        }
    }

    /**
     * Restrict preferred channel to what user's tier features allow.
     */
    protected function sanitizeChannel($user, string $channel): string
    {
        $canSms = $this->entitlementService->can($user, 'sms_channel');
        $canEmail = $this->entitlementService->can($user, 'email_channel');

        if ($channel === 'both') {
            if ($canSms && $canEmail) return 'both';
            return $canSms ? 'sms' : 'email';
        }

        if ($channel === 'sms' && ! $canSms && $canEmail) {
            return 'email';
        }

        if ($channel === 'email' && ! $canEmail && $canSms) {
            return 'sms';
        }

        return $channel;
    }
}
