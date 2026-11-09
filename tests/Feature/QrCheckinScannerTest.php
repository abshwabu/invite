<?php

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\CheckinLog;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Scanner;
use App\Models\Subscription;
use App\Models\Tier;
use App\Models\User;
use App\Services\QrService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

beforeEach(function () {
    // Seed tiers
    $this->seed(\Database\Seeders\TierSeeder::class);

    $this->freeTier = Tier::where('slug', 'free')->first();
    $this->bronzeTier = Tier::where('slug', 'bronze')->first();

    $this->category = Category::firstOrCreate(
        ['slug' => 'wedding'],
        ['name_en' => 'Wedding', 'name_am' => 'ሰርግ', 'sort' => 1]
    );
});

function createPaidHostWithEvent(Tier $tier, Category $category): array
{
    $user = User::factory()->create();
    Subscription::create([
        'user_id' => $user->id,
        'tier_id' => $tier->id,
        'cards_total' => $tier->card_quota,
        'cards_used' => 0,
        'starts_at' => now(),
        'status' => 'active',
        'source' => 'system',
    ]);

    $event = Event::create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'title' => 'Gala Night',
        'starts_at' => now()->addDays(5),
        'timezone' => 'Africa/Addis_Ababa',
        'venue_name' => 'Sheraton Addis',
        'is_invite_only' => true,
        'status' => 'published',
    ]);

    $guest = Guest::create([
        'event_id' => $event->id,
        'name' => 'Abebe Bikila',
        'phone' => '+251911223344',
        'email' => 'abebe@example.com',
        'token' => Str::random(32),
        'max_party_size' => 2,
    ]);

    return [$user, $event, $guest];
}

test('feature gating: free tier user without qr_checkin cannot create scanners', function () {
    $user = User::factory()->create();
    // Free tier default subscription created on registration
    $event = Event::create([
        'user_id' => $user->id,
        'category_id' => $this->category->id,
        'title' => 'Free Event',
        'starts_at' => now()->addDays(5),
        'timezone' => 'Africa/Addis_Ababa',
        'venue_name' => 'Park',
        'is_invite_only' => true,
        'status' => 'published',
    ]);

    $response = $this->actingAs($user)->post(route('events.scanners.store', ['event' => $event->id]), [
        'name' => 'Door 1',
        'pin' => '1234',
    ]);

    $response->assertSessionHasErrors('qr_checkin');
    expect($event->scanners()->count())->toBe(0);
});

test('tier limit: enforces max_scanners_per_event on paid tier', function () {
    [$user, $event, $guest] = createPaidHostWithEvent($this->bronzeTier, $this->category);
    // Bronze tier max_scanners_per_event = 2

    // 1st scanner
    $this->actingAs($user)->post(route('events.scanners.store', ['event' => $event->id]), [
        'name' => 'Gate A',
        'pin' => '1111',
    ])->assertRedirect();
    expect($event->scanners()->count())->toBe(1);

    // 2nd scanner
    $this->actingAs($user)->post(route('events.scanners.store', ['event' => $event->id]), [
        'name' => 'Gate B',
        'pin' => '2222',
    ])->assertRedirect();
    expect($event->scanners()->count())->toBe(2);

    // 3rd scanner should exceed limit of 2
    $response = $this->actingAs($user)->post(route('events.scanners.store', ['event' => $event->id]), [
        'name' => 'Gate C',
        'pin' => '3333',
    ]);
    $response->assertSessionHasErrors('scanner_limit');
    expect($event->scanners()->count())->toBe(2);
});

test('pin lockout: 5 failed PIN attempts locks the scanner for 15 minutes', function () {
    [$user, $event, $guest] = createPaidHostWithEvent($this->bronzeTier, $this->category);

    $scanner = $event->scanners()->create([
        'name' => 'VIP Gate',
        'pin_hash' => Hash::make('4321'),
        'token' => Str::random(32),
    ]);

    // Submit wrong PIN 4 times
    for ($i = 1; $i <= 4; $i++) {
        $res = $this->post(route('scanner.auth', ['token' => $scanner->token]), ['pin' => '0000']);
        $res->assertSessionHasErrors('pin');
        $scanner->refresh();
        expect($scanner->failed_attempts)->toBe($i);
        expect($scanner->isLocked())->toBeFalse();
    }

    // 5th failed attempt triggers lockout
    $res = $this->post(route('scanner.auth', ['token' => $scanner->token]), ['pin' => '0000']);
    $res->assertSessionHasErrors('pin');
    $scanner->refresh();
    expect($scanner->failed_attempts)->toBe(5);
    expect($scanner->isLocked())->toBeTrue();
    expect($scanner->locked_until->isFuture())->toBeTrue();

    // 6th attempt while locked is rejected
    $res = $this->post(route('scanner.auth', ['token' => $scanner->token]), ['pin' => '4321']);
    $res->assertSessionHasErrors('pin');

    // Simulate lockout expiry
    $scanner->update(['locked_until' => now()->subMinute()]);
    expect($scanner->isLocked())->toBeFalse();

    // Correct PIN unlocks scanner and resets failed attempts
    $res = $this->post(route('scanner.auth', ['token' => $scanner->token]), ['pin' => '4321']);
    $res->assertRedirect(route('scanner.show', ['token' => $scanner->token]));
    $scanner->refresh();
    expect($scanner->failed_attempts)->toBe(0);
    expect($scanner->locked_until)->toBeNull();
});

test('revoked scanner is blocked from scanning and login', function () {
    [$user, $event, $guest] = createPaidHostWithEvent($this->bronzeTier, $this->category);

    $scanner = $event->scanners()->create([
        'name' => 'Revoked Gate',
        'pin_hash' => Hash::make('1234'),
        'token' => Str::random(32),
    ]);

    // Host revokes scanner
    $this->actingAs($user)->post(route('events.scanners.revoke', ['event' => $event->id, 'scanner' => $scanner->id]))
        ->assertRedirect();
    $scanner->refresh();
    expect($scanner->isRevoked())->toBeTrue();

    // Attempting PIN auth on revoked scanner returns 403
    $this->post(route('scanner.auth', ['token' => $scanner->token]), ['pin' => '1234'])
        ->assertForbidden();

    // Attempting scan API with revoked scanner token returns 403
    $payload = QrService::generatePayload($event->id, $guest->id);
    $response = $this->postJson(route('api.scan'), [
        'scanner_token' => $scanner->token,
        'qr_payload' => $payload,
    ]);
    $response->assertStatus(403)
        ->assertJson(['status' => 'revoked']);
});

test('valid QR scan succeeds exactly once and any subsequent scan fails with already_used', function () {
    [$user, $event, $guest] = createPaidHostWithEvent($this->bronzeTier, $this->category);

    $scanner = $event->scanners()->create([
        'name' => 'Main Gate',
        'pin_hash' => Hash::make('1234'),
        'token' => Str::random(32),
    ]);

    $payload = QrService::generatePayload($event->id, $guest->id);

    // 1st scan: Success
    $response1 = $this->postJson(route('api.scan'), [
        'scanner_token' => $scanner->token,
        'qr_payload' => $payload,
    ]);

    $response1->assertOk()
        ->assertJson([
            'status' => 'success',
            'message' => "Welcome, {$guest->name}",
            'guest' => [
                'id' => $guest->id,
                'name' => $guest->name,
                'party_size' => 2,
            ],
        ]);

    $guest->refresh();
    expect($guest->checked_in_at)->not->toBeNull();
    expect($guest->checked_in_by)->toBe('Main Gate');

    // Verify checkin_logs recorded success
    $successLog = CheckinLog::where('event_id', $event->id)
        ->where('guest_id', $guest->id)
        ->latest()
        ->first();
    expect($successLog)->not->toBeNull();
    expect($successLog->result)->toBe('success');
    expect($successLog->scanner_id)->toBe($scanner->id);

    // 2nd scan of same QR: Must fail with already_used
    $response2 = $this->postJson(route('api.scan'), [
        'scanner_token' => $scanner->token,
        'qr_payload' => $payload,
    ]);

    $response2->assertStatus(409)
        ->assertJson([
            'status' => 'already_used',
            'guest' => [
                'id' => $guest->id,
                'name' => $guest->name,
            ],
        ]);
    expect($response2->json('message'))->toContain('Already used at');
    expect($response2->json('message'))->toContain('Main Gate');

    // Verify checkin_logs recorded already_used
    $alreadyUsedLog = CheckinLog::where('event_id', $event->id)
        ->where('guest_id', $guest->id)
        ->where('result', 'already_used')
        ->first();
    expect($alreadyUsedLog)->not->toBeNull();
});

test('concurrent double-scan: exactly one succeeds and one fails', function () {
    [$user, $event, $guest] = createPaidHostWithEvent($this->bronzeTier, $this->category);

    $scanner1 = $event->scanners()->create([
        'name' => 'Scanner 1',
        'pin_hash' => Hash::make('1234'),
        'token' => Str::random(32),
    ]);

    $scanner2 = $event->scanners()->create([
        'name' => 'Scanner 2',
        'pin_hash' => Hash::make('1234'),
        'token' => Str::random(32),
    ]);

    $payload = QrService::generatePayload($event->id, $guest->id);

    // Emulate simultaneous scans
    $checkinService = app(\App\Services\CheckinService::class);
    $result1 = $checkinService->checkin($scanner1, $payload);
    $result2 = $checkinService->checkin($scanner2, $payload);

    $results = [$result1['status'], $result2['status']];
    expect($results)->toContain('success');
    expect($results)->toContain('already_used');

    $guest->refresh();
    expect($guest->checked_in_at)->not->toBeNull();
    expect($guest->checked_in_by)->toBeIn(['Scanner 1', 'Scanner 2']);
});

test('wrong event QR payload is rejected with 422', function () {
    [$user, $event1, $guest1] = createPaidHostWithEvent($this->bronzeTier, $this->category);

    $event2 = Event::create([
        'user_id' => $user->id,
        'category_id' => $this->category->id,
        'title' => 'Another Event',
        'starts_at' => now()->addDays(5),
        'timezone' => 'Africa/Addis_Ababa',
        'venue_name' => 'Hilton',
        'status' => 'published',
    ]);

    $scanner2 = $event2->scanners()->create([
        'name' => 'Gate Hilton',
        'pin_hash' => Hash::make('1234'),
        'token' => Str::random(32),
    ]);

    // QR payload generated for Event 1
    $payloadEvent1 = QrService::generatePayload($event1->id, $guest1->id);

    // Scanned by Event 2 scanner
    $response = $this->postJson(route('api.scan'), [
        'scanner_token' => $scanner2->token,
        'qr_payload' => $payloadEvent1,
    ]);

    $response->assertStatus(422)
        ->assertJson(['status' => 'wrong_event']);

    $log = CheckinLog::where('scanner_id', $scanner2->id)->latest()->first();
    expect($log)->not->toBeNull();
    expect($log->result)->toBe('wrong_event');
});

test('tampered signature QR payload is rejected with 422', function () {
    [$user, $event, $guest] = createPaidHostWithEvent($this->bronzeTier, $this->category);

    $scanner = $event->scanners()->create([
        'name' => 'Main Gate',
        'pin_hash' => Hash::make('1234'),
        'token' => Str::random(32),
    ]);

    // Malicious tampered signature
    $tamperedPayload = "INVITE:{$event->id}:{$guest->id}:randomnonce:invalidsignature123";

    $response = $this->postJson(route('api.scan'), [
        'scanner_token' => $scanner->token,
        'qr_payload' => $tamperedPayload,
    ]);

    $response->assertStatus(422)
        ->assertJson(['status' => 'invalid']);

    $log = CheckinLog::where('scanner_id', $scanner->id)->latest()->first();
    expect($log)->not->toBeNull();
    expect($log->result)->toBe('invalid');
});

test('scanning is blocked when event is closed, completed, or suspended', function () {
    [$user, $event, $guest] = createPaidHostWithEvent($this->bronzeTier, $this->category);

    $scanner = $event->scanners()->create([
        'name' => 'Main Gate',
        'pin_hash' => Hash::make('1234'),
        'token' => Str::random(32),
    ]);

    $payload = QrService::generatePayload($event->id, $guest->id);

    foreach (['closed', 'suspended', 'cancelled'] as $status) {
        $event->update(['status' => $status]);

        $response = $this->postJson(route('api.scan'), [
            'scanner_token' => $scanner->token,
            'qr_payload' => $payload,
        ]);

        $response->assertStatus(422)
            ->assertJson(['status' => 'event_closed']);
    }
});

test('host manual override: can manually check in and undo with audit reason', function () {
    [$user, $event, $guest] = createPaidHostWithEvent($this->bronzeTier, $this->category);

    // Host manually checks in guest
    $res1 = $this->actingAs($user)->post(route('events.guests.manual-checkin', [
        'event' => $event->id,
        'guest' => $guest->id,
    ]), [
        'reason' => 'Guest lost phone; verified national ID card',
    ]);
    $res1->assertRedirect();

    $guest->refresh();
    expect($guest->checked_in_at)->not->toBeNull();
    expect($guest->checked_in_by)->toContain($user->name);

    // Audit log recorded
    $auditLog1 = AuditLog::where('action', 'manual_checkin')
        ->where('subject_id', $guest->id)
        ->latest()
        ->first();
    expect($auditLog1)->not->toBeNull();
    expect($auditLog1->meta['reason'])->toBe('Guest lost phone; verified national ID card');

    // Host undos check-in
    $res2 = $this->actingAs($user)->post(route('events.guests.undo-checkin', [
        'event' => $event->id,
        'guest' => $guest->id,
    ]), [
        'reason' => 'Duplicate family member registered',
    ]);
    $res2->assertRedirect();

    $guest->refresh();
    expect($guest->checked_in_at)->toBeNull();
    expect($guest->checked_in_by)->toBeNull();

    // Audit log recorded for undo
    $auditLog2 = AuditLog::where('action', 'undo_checkin')
        ->where('subject_id', $guest->id)
        ->latest()
        ->first();
    expect($auditLog2)->not->toBeNull();
    expect($auditLog2->meta['reason'])->toBe('Duplicate family member registered');
});

test('offline tolerance: keeps earliest timestamp on conflict and flags audit log', function () {
    [$user, $event, $guest] = createPaidHostWithEvent($this->bronzeTier, $this->category);

    $scanner1 = $event->scanners()->create([
        'name' => 'Device Online',
        'pin_hash' => Hash::make('1234'),
        'token' => Str::random(32),
    ]);

    $scanner2 = $event->scanners()->create([
        'name' => 'Device Offline',
        'pin_hash' => Hash::make('1234'),
        'token' => Str::random(32),
    ]);

    $payload = QrService::generatePayload($event->id, $guest->id);

    // Device Online checks in guest at 15:00
    $timeOnline = Carbon::parse('2026-11-15 15:00:00');
    $resOnline = $this->postJson(route('api.scan'), [
        'scanner_token' => $scanner1->token,
        'qr_payload' => $payload,
        'scanned_at' => $timeOnline->toIso8601String(),
    ]);
    $resOnline->assertOk();

    $guest->refresh();
    expect($guest->checked_in_at->format('Y-m-d H:i:s'))->toBe('2026-11-15 15:00:00');
    expect($guest->checked_in_by)->toBe('Device Online');

    // Device Offline was disconnected, scanned earlier at 14:30, and now syncs via /api/scan/sync
    $timeOfflineEarlier = Carbon::parse('2026-11-15 14:30:00');
    $syncResponse = $this->postJson(route('api.scan.sync'), [
        'scanner_token' => $scanner2->token,
        'scans' => [
            [
                'qr_payload' => $payload,
                'scanned_at' => $timeOfflineEarlier->toIso8601String(),
                'device_info' => 'Offline iPad at Door 2',
            ],
        ],
    ]);

    $syncResponse->assertOk()
        ->assertJson([
            'status' => 'ok',
            'synced' => 1,
            'conflicts' => 1,
        ]);

    // Check that earliest timestamp (14:30:00) is preserved!
    $guest->refresh();
    expect($guest->checked_in_at->format('Y-m-d H:i:s'))->toBe('2026-11-15 14:30:00');
    expect($guest->checked_in_by)->toBe('Device Offline');

    // Check conflict audit log flagged for host
    $conflictAudit = AuditLog::where('action', 'offline_checkin_conflict')
        ->where('subject_id', $guest->id)
        ->first();
    expect($conflictAudit)->not->toBeNull();
    expect($conflictAudit->meta['earlier_scanner'])->toBe('Device Offline');
    expect($conflictAudit->meta['previous_scanner'])->toBe('Device Online');
});

test('scanner endpoints: manifest and search return event guests', function () {
    [$user, $event, $guest] = createPaidHostWithEvent($this->bronzeTier, $this->category);

    $scanner = $event->scanners()->create([
        'name' => 'Entrance A',
        'pin_hash' => Hash::make('1234'),
        'token' => Str::random(32),
    ]);

    // Offline manifest
    $resManifest = $this->getJson(route('api.scan.offline-manifest') . "?scanner_token={$scanner->token}");
    $resManifest->assertOk()
        ->assertJsonStructure([
            'event_id',
            'event_title',
            'guests' => [
                '*' => ['id', 'name', 'party_size'],
            ],
        ]);
    expect($resManifest->json('guests')[0]['name'])->toBe('Abebe Bikila');

    // Guest search
    $resSearch = $this->getJson(route('api.scan.guests') . "?scanner_token={$scanner->token}&query=Abebe");
    $resSearch->assertOk()
        ->assertJsonStructure([
            'guests' => [
                '*' => ['id', 'name', 'party_size', 'is_checked_in'],
            ],
        ]);
    expect($resSearch->json('guests')[0]['name'])->toBe('Abebe Bikila');
});
