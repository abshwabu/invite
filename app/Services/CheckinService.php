<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CheckinLog;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Scanner;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CheckinService
{
    /**
     * Process an admission scan for a scanner.
     *
     * @return array{
     *     status: string,
     *     code: int,
     *     message: string,
     *     guest?: array<string, mixed>
     * }
     */
    public function checkin(
        Scanner $scanner,
        string $qrPayload,
        ?string $scannedAt = null,
        ?string $deviceInfo = null,
        ?string $ipAddress = null
    ): array {
        // 1. Verify scanner status
        if ($scanner->isRevoked()) {
            return [
                'status' => 'revoked',
                'code' => 403,
                'message' => 'Scanner has been revoked',
            ];
        }

        if ($scanner->isLocked()) {
            return [
                'status' => 'locked',
                'code' => 429,
                'message' => "Scanner is locked until {$scanner->locked_until?->format('H:i')}",
            ];
        }

        // 2. Verify event status
        $event = $scanner->event;
        if (! $event || in_array($event->status, ['closed', 'suspended', 'cancelled'], true)) {
            return [
                'status' => 'event_closed',
                'code' => 422,
                'message' => 'Event is closed or suspended',
            ];
        }

        // 3. Verify HMAC signature & event matching
        $verification = QrService::verifyPayload($qrPayload);
        if (! $verification['valid']) {
            CheckinLog::create([
                'event_id' => $event->id,
                'scanner_id' => $scanner->id,
                'guest_id' => null,
                'result' => 'invalid',
                'raw_payload' => $qrPayload,
                'ip_address' => $ipAddress,
                'device_info' => $deviceInfo,
                'scanned_at' => now(),
            ]);

            return [
                'status' => 'invalid',
                'code' => 422,
                'message' => 'Invalid / wrong event',
            ];
        }

        if ((int) $verification['event_id'] !== (int) $event->id) {
            CheckinLog::create([
                'event_id' => $event->id,
                'scanner_id' => $scanner->id,
                'guest_id' => $verification['guest_id'] ?? null,
                'result' => 'wrong_event',
                'raw_payload' => $qrPayload,
                'ip_address' => $ipAddress,
                'device_info' => $deviceInfo,
                'scanned_at' => now(),
            ]);

            return [
                'status' => 'wrong_event',
                'code' => 422,
                'message' => 'Invalid / wrong event',
            ];
        }

        // 4. Locate guest
        $guest = Guest::where('event_id', $event->id)->find($verification['guest_id']);
        if (! $guest) {
            CheckinLog::create([
                'event_id' => $event->id,
                'scanner_id' => $scanner->id,
                'guest_id' => null,
                'result' => 'invalid',
                'raw_payload' => $qrPayload,
                'ip_address' => $ipAddress,
                'device_info' => $deviceInfo,
                'scanned_at' => now(),
            ]);

            return [
                'status' => 'invalid',
                'code' => 422,
                'message' => 'Invalid / wrong event',
            ];
        }

        $checkinTime = $scannedAt ? Carbon::parse($scannedAt) : now();

        // 5. Atomic single-use check-in with SQLITE_BUSY retry
        $affected = 0;
        $maxRetries = 5;
        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            try {
                $affected = DB::update(
                    'UPDATE guests SET checked_in_at = :checked_in_at, checked_in_by = :checked_in_by, updated_at = :updated_at WHERE id = :id AND event_id = :event_id AND checked_in_at IS NULL',
                    [
                        'checked_in_at' => $checkinTime->toDateTimeString(),
                        'checked_in_by' => $scanner->name,
                        'updated_at' => now()->toDateTimeString(),
                        'id' => $guest->id,
                        'event_id' => $event->id,
                    ]
                );
                break;
            } catch (\Illuminate\Database\QueryException $e) {
                if (str_contains($e->getMessage(), 'database is locked') || str_contains($e->getMessage(), 'busy')) {
                    usleep(10000 * ($attempt + 1));
                    continue;
                }
                throw $e;
            }
        }

        // 6. Handle check-in success
        if ($affected === 1) {
            $guest->refresh();

            CheckinLog::create([
                'event_id' => $event->id,
                'scanner_id' => $scanner->id,
                'guest_id' => $guest->id,
                'result' => 'success',
                'raw_payload' => $qrPayload,
                'ip_address' => $ipAddress,
                'device_info' => $deviceInfo,
                'scanned_at' => $checkinTime,
            ]);

            return [
                'status' => 'success',
                'code' => 200,
                'message' => "Welcome, {$guest->name}",
                'guest' => [
                    'id' => $guest->id,
                    'name' => $guest->name,
                    'party_size' => $guest->rsvp_party_size ?: $guest->max_party_size,
                    'checked_in_at' => $guest->checked_in_at?->toIso8601String(),
                    'checked_in_by' => $guest->checked_in_by,
                ],
            ];
        }

        // 7. Handle already used or offline conflict
        $guest->refresh();

        // Check if incoming offline scan happened EARLIER than current checked_in_at
        if ($scannedAt && $guest->checked_in_at && $checkinTime->lt($guest->checked_in_at)) {
            $previousTime = $guest->checked_in_at;
            $previousBy = $guest->checked_in_by;

            $guest->update([
                'checked_in_at' => $checkinTime,
                'checked_in_by' => $scanner->name,
            ]);

            CheckinLog::create([
                'event_id' => $event->id,
                'scanner_id' => $scanner->id,
                'guest_id' => $guest->id,
                'result' => 'conflict_earliest_kept',
                'raw_payload' => $qrPayload,
                'ip_address' => $ipAddress,
                'device_info' => $deviceInfo,
                'scanned_at' => $checkinTime,
            ]);

            AuditLogService::log(
                'offline_checkin_conflict',
                null,
                $guest,
                [
                    'event_id' => $event->id,
                    'earlier_time' => $checkinTime->toIso8601String(),
                    'previous_time' => $previousTime->toIso8601String(),
                    'earlier_scanner' => $scanner->name,
                    'previous_scanner' => $previousBy,
                ]
            );

            return [
                'status' => 'conflict_resolved',
                'code' => 200,
                'message' => "Welcome, {$guest->name} (offline earlier scan synced)",
                'guest' => [
                    'id' => $guest->id,
                    'name' => $guest->name,
                    'party_size' => $guest->rsvp_party_size ?: $guest->max_party_size,
                    'checked_in_at' => $guest->checked_in_at?->toIso8601String(),
                    'checked_in_by' => $guest->checked_in_by,
                ],
            ];
        }

        // Normal already used
        CheckinLog::create([
            'event_id' => $event->id,
            'scanner_id' => $scanner->id,
            'guest_id' => $guest->id,
            'result' => 'already_used',
            'raw_payload' => $qrPayload,
            'ip_address' => $ipAddress,
            'device_info' => $deviceInfo,
            'scanned_at' => now(),
        ]);

        $timeStr = $guest->checked_in_at ? $guest->checked_in_at->format('Y-m-d H:i') : 'earlier';
        $byStr = $guest->checked_in_by ?: 'another scanner';

        return [
            'status' => 'already_used',
            'code' => 409,
            'message' => "Already used at {$timeStr} by {$byStr}",
            'guest' => [
                'id' => $guest->id,
                'name' => $guest->name,
                'party_size' => $guest->rsvp_party_size ?: $guest->max_party_size,
                'checked_in_at' => $guest->checked_in_at?->toIso8601String(),
                'checked_in_by' => $guest->checked_in_by,
            ],
        ];
    }

    /**
     * Host manual override to check in a guest.
     */
    public function manualCheckin(User $user, Event $event, Guest $guest, string $reason): array
    {
        $guest->update([
            'checked_in_at' => now(),
            'checked_in_by' => "Host ({$user->name})",
        ]);

        CheckinLog::create([
            'event_id' => $event->id,
            'scanner_id' => null,
            'guest_id' => $guest->id,
            'result' => 'manual_override',
            'raw_payload' => "Manual check-in: {$reason}",
            'ip_address' => request()->ip(),
            'device_info' => request()->userAgent(),
            'scanned_at' => now(),
        ]);

        AuditLogService::log('manual_checkin', $user, $guest, [
            'event_id' => $event->id,
            'reason' => $reason,
        ]);

        return [
            'status' => 'success',
            'message' => "Guest {$guest->name} checked in manually.",
            'guest' => $guest->fresh(),
        ];
    }

    /**
     * Host manual override to undo check-in for a guest.
     */
    public function undoCheckin(User $user, Event $event, Guest $guest, string $reason): array
    {
        $previousTime = $guest->checked_in_at;
        $previousBy = $guest->checked_in_by;

        $guest->update([
            'checked_in_at' => null,
            'checked_in_by' => null,
        ]);

        CheckinLog::create([
            'event_id' => $event->id,
            'scanner_id' => null,
            'guest_id' => $guest->id,
            'result' => 'undo_override',
            'raw_payload' => "Undo check-in: {$reason}",
            'ip_address' => request()->ip(),
            'device_info' => request()->userAgent(),
            'scanned_at' => now(),
        ]);

        AuditLogService::log('undo_checkin', $user, $guest, [
            'event_id' => $event->id,
            'reason' => $reason,
            'previous_checked_in_at' => $previousTime?->toIso8601String(),
            'previous_checked_in_by' => $previousBy,
        ]);

        return [
            'status' => 'success',
            'message' => "Check-in undone for {$guest->name}.",
            'guest' => $guest->fresh(),
        ];
    }
}
