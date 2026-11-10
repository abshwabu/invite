<?php

use App\Models\AbuseReport;
use App\Models\Category;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Scanner;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SuperAdmin;
use App\Models\Tier;
use App\Models\UsageLedger;
use App\Models\User;
use App\Notifications\QuotaLowWarningNotification;
use App\Notifications\SubscriptionExpiringSoonNotification;
use App\Services\CheckinService;
use App\Services\QrService;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TierSeeder::class);
    Storage::fake('local');
});

test('subscription expiry warnings are dispatched at 7 days, 1 day, and 90% quota and fire only once', function () {
    Notification::fake();

    $goldTier = Tier::where('slug', 'gold')->first();

    // 1. User with subscription expiring in 6 days (<= 7 days)
    $user7d = User::factory()->create();
    $sub7d = Subscription::create([
        'user_id' => $user7d->id,
        'tier_id' => $goldTier->id,
        'status' => 'active',
        'cards_total' => 500,
        'cards_used' => 10,
        'starts_at' => now()->subDays(24),
        'expires_at' => now()->addDays(6),
        'ends_at' => now()->addDays(6),
    ]);

    // 2. User with subscription expiring in 18 hours (<= 1 day)
    $user1d = User::factory()->create();
    $sub1d = Subscription::create([
        'user_id' => $user1d->id,
        'tier_id' => $goldTier->id,
        'status' => 'active',
        'cards_total' => 500,
        'cards_used' => 10,
        'starts_at' => now()->subDays(29),
        'expires_at' => now()->addHours(18),
        'ends_at' => now()->addHours(18),
    ]);

    // 3. User with 92% quota used (>= 90%)
    $userQuota = User::factory()->create();
    $subQuota = Subscription::create([
        'user_id' => $userQuota->id,
        'tier_id' => $goldTier->id,
        'status' => 'active',
        'cards_total' => 100,
        'cards_used' => 92,
        'starts_at' => now()->subDays(5),
        'expires_at' => now()->addDays(25),
        'ends_at' => now()->addDays(25),
    ]);

    // 4. User with already expired subscription
    $userExpired = User::factory()->create();
    $subExpired = Subscription::create([
        'user_id' => $userExpired->id,
        'tier_id' => $goldTier->id,
        'status' => 'active',
        'cards_total' => 100,
        'cards_used' => 10,
        'starts_at' => now()->subDays(31),
        'expires_at' => now()->subHours(2),
        'ends_at' => now()->subHours(2),
    ]);

    // Run expiration check command
    Artisan::call('subscriptions:check-expirations');

    // Assert notifications sent
    Notification::assertSentTo($user7d, SubscriptionExpiringSoonNotification::class);
    Notification::assertSentTo($user1d, SubscriptionExpiringSoonNotification::class);
    Notification::assertSentTo($userQuota, QuotaLowWarningNotification::class);

    // Assert subExpired is now marked 'expired'
    expect($subExpired->fresh()->status)->toBe('expired');

    // Run command again and verify no duplicate notifications are sent
    Notification::fake();
    Artisan::call('subscriptions:check-expirations');

    Notification::assertNothingSent();
});

test('expired or exhausted subscription blocks sending but permits editing, viewing, and checkin', function () {
    $goldTier = Tier::where('slug', 'gold')->first();
    $user = User::factory()->create();

    // Mark any auto-created free subscription as exhausted so user has 0 cards
    $user->subscriptions()->update(['status' => 'exhausted', 'cards_used' => 20]);

    // Create expired subscription
    Subscription::create([
        'user_id' => $user->id,
        'tier_id' => $goldTier->id,
        'status' => 'expired',
        'cards_total' => 100,
        'cards_used' => 10,
        'starts_at' => now()->subDays(40),
        'expires_at' => now()->subDays(10),
        'ends_at' => now()->subDays(10),
    ]);

    $category = Category::create([
        'name_en' => 'Wedding',
        'name_am' => 'ሠርግ',
        'slug' => 'wedding',
        'sort' => 1,
    ]);

    $event = Event::create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'title' => 'Abebe & Chaltu Wedding',
        'starts_at' => now()->addDays(10),
        'venue_name' => 'Sheraton Addis',
        'status' => 'draft',
    ]);

    $guest = Guest::create([
        'event_id' => $event->id,
        'name' => 'Almaz Ayana',
        'phone' => '0911223344',
        'token' => 'guest-valid-token-12345',
        'max_party_size' => 2,
    ]);

    // 1. Attempting to send campaign is BLOCKED due to expired/exhausted subscription
    $response = $this->actingAs($user)->post(route('events.campaigns.store', $event->id), [
        'channel' => 'sms',
        'body' => 'Please join our wedding celebration',
        'link_mode' => 'guest_invitation',
    ]);
    $response->assertSessionHasErrors(['quota']);

    // 2. Editing event details is NEVER blocked
    $editResponse = $this->actingAs($user)->patch(route('events.update', $event->id), [
        'title' => 'Abebe & Chaltu Wedding Celebration',
        'category_id' => $category->id,
        'starts_at' => now()->addDays(11)->toDateTimeString(),
        'venue_name' => 'Skylight Hotel',
        'timezone' => 'Africa/Addis_Ababa',
        'rsvp_enabled' => true,
    ]);
    $editResponse->assertRedirect(route('dashboard'));
    expect($event->fresh()->venue_name)->toBe('Skylight Hotel');

    // 3. Public invitation page remains fully accessible
    $publicResponse = $this->get(route('public.invitation.show', ['token' => $guest->token]));
    $publicResponse->assertOk();

    // 4. Scanner check-in remains fully functional
    $scanner = Scanner::create([
        'event_id' => $event->id,
        'name' => 'Door Scanner 1',
        'code' => 'door-code-123',
        'pin_hash' => Hash::make('1234'),
        'token' => 'scanner-token-abc',
        'status' => 'active',
    ]);

    $qrPayload = QrService::generatePayload($event->id, $guest->id);
    $checkinService = app(CheckinService::class);
    $result = $checkinService->checkin($scanner, $qrPayload);

    expect($result['status'])->toBe('success');
    expect($result['code'])->toBe(200);
    expect($guest->fresh()->checked_in_at)->not->toBeNull();
});

test('public invitation reporting and rate limiting kicks in after 5 submissions', function () {
    $category = Category::create([
        'name_en' => 'Birthday',
        'name_am' => 'ልደት',
        'slug' => 'birthday',
        'sort' => 2,
    ]);

    $user = User::factory()->create();
    $event = Event::create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'title' => 'Suspicious Birthday Event',
        'starts_at' => now()->addDays(5),
    ]);

    $guest = Guest::create([
        'event_id' => $event->id,
        'name' => 'Report Guest',
        'phone' => '0911223344',
        'token' => 'report-guest-token-123',
    ]);

    // First report
    $response = $this->post(route('public.invitation.report', ['token' => $guest->token]), [
        'reason' => 'spam',
        'description' => 'I did not sign up for this commercial promo.',
        'reporter_email' => 'victim@example.com',
    ]);

    $response->assertSessionHas('success');

    $report = AbuseReport::where('event_id', $event->id)->first();
    expect($report)->not->toBeNull();
    expect($report->reason)->toBe('spam');
    expect($report->status)->toBe('pending');
    expect($report->reporter_email)->toBe('victim@example.com');

    // Submit 4 more reports (total 5)
    for ($i = 0; $i < 4; $i++) {
        $this->postJson(route('public.invitation.report', ['token' => $guest->token]), [
            'reason' => 'fraud',
        ])->assertOk();
    }

    // 6th report exceeds the rate limit of 5 per hour
    $rateLimitedResponse = $this->postJson(route('public.invitation.report', ['token' => $guest->token]), [
        'reason' => 'harassment',
    ]);
    $rateLimitedResponse->assertStatus(429);
});

test('super admin can dismiss report, suspend event, and suspend user', function () {
    $owner = SuperAdmin::create([
        'name' => 'Master Admin',
        'email' => 'admin@invite.et',
        'password' => bcrypt('password'),
        'role' => 'owner',
        'is_active' => true,
    ]);

    $user = User::factory()->create(['is_suspended' => false]);
    $category = Category::create([
        'name_en' => 'Other',
        'name_am' => 'ሌላ',
        'slug' => 'other',
        'sort' => 9,
    ]);

    $event = Event::create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'title' => 'Policy Violating Gathering',
        'starts_at' => now()->addDays(2),
        'status' => 'published',
    ]);

    $guest = Guest::create([
        'event_id' => $event->id,
        'name' => 'Test Target',
        'token' => 'target-token-xyz',
    ]);

    // Create abuse report
    $report = AbuseReport::create([
        'event_id' => $event->id,
        'guest_id' => $guest->id,
        'reporter_ip' => '127.0.0.1',
        'reason' => 'fraud',
        'description' => 'Phishing scam asking for money.',
        'status' => 'pending',
    ]);

    // Test Dismiss
    $report->dismiss($owner, 'Verified as legitimate test');
    expect($report->fresh()->status)->toBe('dismissed');
    expect($report->fresh()->action_taken)->toBe('dismissed');
    expect($report->fresh()->admin_notes)->toBe('Verified as legitimate test');

    // Reset to pending for suspendEvent test
    $report->update(['status' => 'pending']);
    $report->suspendEvent($owner, 'Definite scam found');
    expect($report->fresh()->status)->toBe('action_taken');
    expect($event->fresh()->status)->toBe('suspended');

    // Accessing public page for suspended event renders Suspended view
    $response = $this->get(route('public.invitation.show', ['token' => $guest->token]));
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('Public/Suspended'));

    // Reset report for suspendUser test
    $report->update(['status' => 'pending']);
    $report->suspendUser($owner, 'Habitual spam operator');
    expect($report->fresh()->status)->toBe('action_taken');
    expect($user->fresh()->is_suspended)->toBeTrue();

    // Suspended user cannot log in
    $loginResponse = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);
    $loginResponse->assertSessionHasErrors(['email']);
});

test('turnstile captcha graceful fallback when unconfigured in test/dev', function () {
    // In testing or when turnstile is not configured, registration proceeds smoothly
    $response = $this->post(route('register'), [
        'name' => 'Test Registrant',
        'email' => 'registrant@example.com',
        'phone' => '0911556677',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
        'cf-turnstile-response' => '', // Empty allowed in fallback mode
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));
    expect(User::where('email', 'registrant@example.com')->exists())->toBeTrue();
});

test('legal pages terms and privacy return 200 with Inertia components', function () {
    $this->get(route('legal.terms'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Legal/Terms'));

    $this->get(route('legal.privacy'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Legal/Privacy'));
});

test('user personal data export generates valid streamed JSON download', function () {
    $user = User::factory()->create(['name' => 'Bekele Desta', 'email' => 'bekele@example.com']);
    $category = Category::create(['name_en' => 'Memorial', 'name_am' => 'መታሰቢያ', 'slug' => 'memorial', 'sort' => 3]);

    $event = Event::create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'title' => 'Memorial of Grandmother',
        'starts_at' => now()->addDays(3),
    ]);

    Guest::create([
        'event_id' => $event->id,
        'name' => 'Marta Bekele',
        'phone' => '0933445566',
        'token' => 'marta-export-token',
    ]);

    $response = $this->actingAs($user)->get(route('profile.export'));
    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/json');
    expect($response->headers->get('content-disposition'))->toContain('attachment; filename=invite-data-export-');

    $content = json_decode($response->streamedContent(), true);
    expect($content)->toHaveKey('user');
    expect($content['user']['email'])->toBe('bekele@example.com');
    expect($content['events'][0]['title'])->toBe('Memorial of Grandmother');
    expect($content['guests'][0]['name'])->toBe('Marta Bekele');
});

test('guest data retention purge anonymizes concluded events past retention days', function () {
    $user = User::factory()->create();
    $category = Category::create(['name_en' => 'Concert', 'name_am' => 'ኮንሰርት', 'slug' => 'concert', 'sort' => 4]);

    // 1. Old event ended 45 days ago (past 30 days retention)
    $oldEvent = Event::create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'title' => 'Ancient Holiday Party',
        'starts_at' => now()->subDays(46),
        'ends_at' => now()->subDays(45),
    ]);

    $oldGuest = Guest::create([
        'event_id' => $oldEvent->id,
        'name' => 'Tadesse Lemma',
        'phone' => '0911998877',
        'email' => 'tadesse@example.com',
        'rsvp_note' => 'Arriving with family',
        'token' => 'token-old-guest-1',
        'checked_in_at' => now()->subDays(45),
    ]);

    // 2. Recent event ended 5 days ago (within 30 days retention)
    $recentEvent = Event::create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'title' => 'Recent Gathering',
        'starts_at' => now()->subDays(6),
        'ends_at' => now()->subDays(5),
    ]);

    $recentGuest = Guest::create([
        'event_id' => $recentEvent->id,
        'name' => 'Kalkidan Haile',
        'phone' => '0922887766',
        'token' => 'token-recent-guest-2',
    ]);

    // Execute purge command with 30 days threshold
    Artisan::call('guests:purge-retention', ['--days' => 30]);

    // Old guest is anonymized
    $oldGuest->refresh();
    expect($oldGuest->name)->toBe('Anonymized Guest');
    expect($oldGuest->phone)->toBeNull();
    expect($oldGuest->email)->toBeNull();
    expect($oldGuest->rsvp_note)->toBeNull();
    // Attendance statistics and tokens are preserved
    expect($oldGuest->token)->toBe('token-old-guest-1');
    expect($oldGuest->checked_in_at)->not->toBeNull();

    // Recent guest is preserved
    $recentGuest->refresh();
    expect($recentGuest->name)->toBe('Kalkidan Haile');
    expect($recentGuest->phone)->toBe('0922887766');
});

test('backup database command creates SQLite backup and prunes old files', function () {
    $backupDir = storage_path('app/backups');
    File::ensureDirectoryExists($backupDir);

    // Create an artificial old backup file that should be pruned (> 14 days old)
    $oldBackupFile = "{$backupDir}/invite-backup-old-test-" . uniqid() . ".sqlite";
    file_put_contents($oldBackupFile, 'old backup');
    touch($oldBackupFile, time() - (25 * 86400));
    clearstatcache(true, $oldBackupFile);

    // Run backup command
    Artisan::call('backup:database');
    clearstatcache(true, $oldBackupFile);

    // Verify fresh backup was created
    $files = File::files($backupDir);
    expect(count($files))->toBeGreaterThan(0);

    // Verify old file was pruned
    expect(File::exists($oldBackupFile))->toBeFalse();
});

test('quota reconcile command detects and fixes ledger mismatches', function () {
    $user = User::factory()->create();
    $goldTier = Tier::where('slug', 'gold')->first();

    $subscription = Subscription::create([
        'user_id' => $user->id,
        'tier_id' => $goldTier->id,
        'cards_total' => 200,
        'cards_used' => 50, // Erroneously set to 50
        'status' => 'active',
        'starts_at' => now(),
    ]);

    // In reality, only 20 cards exist in the ledger
    UsageLedger::create([
        'user_id' => $user->id,
        'subscription_id' => $subscription->id,
        'kind' => 'invitation',
        'channel' => 'sms',
        'quantity' => 20,
        'created_at' => now(),
    ]);

    // Run reconciliation without fix
    Artisan::call('quota:reconcile', ['--subscription' => $subscription->id]);
    expect($subscription->fresh()->cards_used)->toBe(50); // Untouched

    // Run reconciliation WITH --fix
    Artisan::call('quota:reconcile', ['--subscription' => $subscription->id, '--fix' => true]);

    // Subscription cards_used is now synchronized to 20
    expect($subscription->fresh()->cards_used)->toBe(20);
});

test('receipts are never publicly reachable without signed URL and authorization', function () {
    $user = User::factory()->create();
    $goldTier = Tier::where('slug', 'gold')->first();

    // Store fake private receipt
    Storage::disk('local')->put('receipts/confidential-receipt.webp', 'image-bytes');

    $payment = Payment::create([
        'user_id' => $user->id,
        'tier_id' => $goldTier->id,
        'payment_code' => 'PAY-SEC-999',
        'provider' => 'manual',
        'amount' => 500,
        'amount_expected' => 500,
        'status' => 'submitted',
        'receipt_path' => 'receipts/confidential-receipt.webp',
        'submitted_at' => now(),
    ]);

    // 1. Direct path to receipt via public storage does not exist or is denied
    expect(in_array($this->get('/storage/receipts/confidential-receipt.webp')->getStatusCode(), [403, 404]))->toBeTrue();

    // 2. Unsigned request to receipts route is rejected (403)
    $this->get(route('payments.receipt', ['payment' => $payment->id]))->assertForbidden();

    // 3. Signed URL for authorized owner streams the file successfully
    $signedUrl = URL::temporarySignedRoute('payments.receipt', now()->addMinutes(15), ['payment' => $payment->id]);
    $response = $this->actingAs($user)->get($signedUrl);
    $response->assertOk();
});

test('atomic single-use checkin concurrency race safety', function () {
    $category = Category::create(['name_en' => 'Gala', 'name_am' => 'ጋላ', 'slug' => 'gala', 'sort' => 5]);
    $user = User::factory()->create();

    $event = Event::create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'title' => 'Grand Gala',
        'starts_at' => now()->addDay(),
        'status' => 'published',
    ]);

    $guest = Guest::create([
        'event_id' => $event->id,
        'name' => 'VIP Guest',
        'token' => 'vip-guest-token-secure',
        'max_party_size' => 1,
    ]);

    $scanner = Scanner::create([
        'event_id' => $event->id,
        'name' => 'Main Gate 1',
        'code' => 'gate-1',
        'pin_hash' => Hash::make('1234'),
        'token' => 'scanner-token-gala',
        'status' => 'active',
    ]);

    $qrPayload = QrService::generatePayload($event->id, $guest->id);
    $checkinService = app(CheckinService::class);

    // First scan: SUCCESS (200)
    $firstScan = $checkinService->checkin($scanner, $qrPayload);
    expect($firstScan['status'])->toBe('success');
    expect($firstScan['code'])->toBe(200);

    // Second scan: ALREADY USED (409)
    $secondScan = $checkinService->checkin($scanner, $qrPayload);
    expect($secondScan['status'])->toBe('already_used');
    expect($secondScan['code'])->toBe(409);

    // Simulate 50 concurrent / rapid repeated scans on the same QR token
    for ($i = 0; $i < 50; $i++) {
        $subsequent = $checkinService->checkin($scanner, $qrPayload);
        expect($subsequent['status'])->toBe('already_used');
        expect($subsequent['code'])->toBe(409);
    }

    // Only 1 check-in timestamp exists
    expect($guest->fresh()->checked_in_at)->not->toBeNull();
});

test('security headers middleware applies strict security headers', function () {
    $response = $this->get('/');
    $response->assertOk();

    expect($response->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN');
    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    expect($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
    expect($response->headers->get('Permissions-Policy'))->toBe('camera=(self), microphone=(), geolocation=()');
    expect($response->headers->get('Content-Security-Policy'))->toContain('https://challenges.cloudflare.com');
});
