<?php

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Delivery;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Reminder;
use App\Models\Scanner;
use App\Models\ShortLink;
use App\Models\Subscription;
use App\Models\Tier;
use App\Models\UsageLedger;
use App\Models\User;
use App\Notifications\HostAlertNotification;
use App\Services\DesignValidator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'CategorySeeder']);
    $this->artisan('db:seed', ['--class' => 'TierSeeder']);
});

test('event overview dashboard renders with totals, charts data, and recent arrivals', function () {
    $tier = Tier::where('slug', 'silver')->first();
    $user = User::factory()->create();
    Subscription::create([
        'user_id' => $user->id,
        'tier_id' => $tier->id,
        'status' => 'active',
        'starts_at' => now(),
        'ends_at' => now()->addYear(),
        'cards_total' => 5000,
        'cards_remaining' => 4500,
    ]);

    $category = Category::first();
    $event = Event::create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'title' => 'Gal Gala Dinner',
        'starts_at' => now()->addDays(5),
        'ends_at' => now()->addDays(5)->addHours(4),
        'timezone' => 'Africa/Addis_Ababa',
        'venue_name' => 'Sheraton Addis',
        'is_invite_only' => true,
        'rsvp_enabled' => true,
        'status' => 'published',
        'design_json' => DesignValidator::blankDesign(),
    ]);

    $scanner = Scanner::create([
        'event_id' => $event->id,
        'name' => 'Gate Alpha',
        'pin_hash' => Hash::make('1234'),
        'token' => Str::random(32),
    ]);

    // Create guests with diverse RSVP and check-in states
    $g1 = Guest::create([
        'event_id' => $event->id,
        'name' => 'Abebe Bikila',
        'phone' => '+251911223344',
        'email' => 'abebe@example.com',
        'preferred_channel' => 'sms',
        'max_party_size' => 2,
        'rsvp_status' => 'yes',
        'rsvp_party_size' => 2,
        'checked_in_at' => now()->subMinutes(10),
        'checked_in_by' => $scanner->id,
        'viewed_at' => now()->subHours(2),
        'token' => Str::random(32),
    ]);

    $g2 = Guest::create([
        'event_id' => $event->id,
        'name' => 'Derartu Tulu',
        'phone' => '+251922334455',
        'preferred_channel' => 'sms',
        'max_party_size' => 3,
        'rsvp_status' => 'no',
        'rsvp_party_size' => 0,
        'token' => Str::random(32),
    ]);

    $g3 = Guest::create([
        'event_id' => $event->id,
        'name' => 'Haile Gebrselassie',
        'email' => 'haile@example.com',
        'preferred_channel' => 'email',
        'max_party_size' => 4,
        'rsvp_status' => 'pending',
        'token' => Str::random(32),
    ]);

    // Create campaign and deliveries
    $campaign = Campaign::create([
        'event_id' => $event->id,
        'user_id' => $user->id,
        'type' => 'invitation',
        'channel' => 'sms',
        'subject' => 'Invitation',
        'body' => 'Welcome',
        'status' => 'sent',
        'audience' => ['type' => 'all'],
        'audience_count' => 3,
    ]);

    Delivery::create([
        'campaign_id' => $campaign->id,
        'guest_id' => $g1->id,
        'channel' => 'sms',
        'status' => 'delivered',
        'quota_charged' => 1,
    ]);

    Delivery::create([
        'campaign_id' => $campaign->id,
        'guest_id' => $g2->id,
        'channel' => 'sms',
        'status' => 'failed',
        'error' => 'Network error',
        'quota_charged' => 0,
    ]);

    $response = $this->actingAs($user)->get(route('events.show', $event->id));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Events/Dashboard')
        ->where('event.id', $event->id)
        ->where('totals.guests', 3)
        ->where('totals.sent', 1)
        ->where('totals.delivered', 1)
        ->where('totals.failed', 1)
        ->where('totals.opened', 1)
        ->where('totals.rsvp_yes', 1)
        ->where('totals.rsvp_no', 1)
        ->where('totals.rsvp_pending', 1)
        ->where('totals.expected_headcount', 2)
        ->where('totals.checked_in', 1)
        ->has('recentArrivals', 1)
        ->where('recentArrivals.0.name', 'Abebe Bikila')
        ->has('charts.sendsTimeline')
        ->has('charts.rsvpBreakdown')
    );
});

test('live checkins endpoint returns json for 5-second polling', function () {
    $user = User::factory()->create();
    $category = Category::first();
    $event = Event::create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'title' => 'Tech Summit Check-in',
        'starts_at' => now(),
        'timezone' => 'Africa/Addis_Ababa',
        'is_invite_only' => true,
        'status' => 'published',
        'design_json' => DesignValidator::blankDesign(),
    ]);

    $scanner = Scanner::create([
        'event_id' => $event->id,
        'name' => 'Scanner Front',
        'pin_hash' => Hash::make('1234'),
        'token' => Str::random(32),
    ]);

    Guest::create([
        'event_id' => $event->id,
        'name' => 'Sara Daniel',
        'preferred_channel' => 'sms',
        'max_party_size' => 2,
        'rsvp_status' => 'yes',
        'rsvp_party_size' => 2,
        'checked_in_at' => now(),
        'checked_in_by' => $scanner->id,
        'token' => Str::random(32),
    ]);

    Guest::create([
        'event_id' => $event->id,
        'name' => 'Yonas Girma',
        'preferred_channel' => 'sms',
        'max_party_size' => 1,
        'rsvp_status' => 'pending',
        'token' => Str::random(32),
    ]);

    $response = $this->actingAs($user)->getJson(route('events.live-checkins', $event->id));

    $response->assertOk();
    $response->assertJson([
        'checked_in_count' => 1,
        'total_guests' => 2,
        'percentage' => 50,
        'expected_headcount' => 2,
    ]);
    expect($response->json('recent_arrivals'))->toHaveCount(1);
    expect($response->json('recent_arrivals.0.name'))->toBe('Sara Daniel');
});

test('reminders feature gating: user on free tier cannot configure reminders without entitlement', function () {
    $user = User::factory()->create();
    // Default subscription is Free tier which has features.reminders = false
    $category = Category::first();
    $event = Event::create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'title' => 'Free Tier Event',
        'starts_at' => now()->addDays(2),
        'timezone' => 'Africa/Addis_Ababa',
        'status' => 'published',
        'design_json' => DesignValidator::blankDesign(),
    ]);

    $response = $this->actingAs($user)->post(route('events.reminders.store', $event->id), [
        'preset' => '1_day_before',
        'channel' => 'sms',
        'target_audience' => 'rsvp_pending',
    ]);

    $response->assertSessionHasErrors('preset');
});

test('reminder creation calculates scheduled time for presets', function () {
    $tier = Tier::where('slug', 'silver')->first();
    $user = User::factory()->create();
    Subscription::create([
        'user_id' => $user->id,
        'tier_id' => $tier->id,
        'status' => 'active',
        'starts_at' => now(),
        'ends_at' => now()->addYear(),
        'cards_total' => 5000,
        'cards_remaining' => 4500,
    ]);

    $category = Category::first();
    $startsAt = Carbon::parse('2026-12-25 18:00:00', 'Africa/Addis_Ababa');

    $event = Event::create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'title' => 'Holiday Gala',
        'starts_at' => $startsAt,
        'timezone' => 'Africa/Addis_Ababa',
        'status' => 'published',
        'design_json' => DesignValidator::blankDesign(),
    ]);

    Guest::create([
        'event_id' => $event->id,
        'name' => 'Almaz Ayana',
        'phone' => '+251911000000',
        'preferred_channel' => 'sms',
        'rsvp_status' => 'pending',
        'token' => Str::random(32),
    ]);

    // Create 3_days_before reminder
    $response = $this->actingAs($user)->post(route('events.reminders.store', $event->id), [
        'preset' => '3_days_before',
        'channel' => 'sms',
        'target_audience' => 'rsvp_pending',
        'subject' => 'Gala in 3 days!',
        'message_body' => 'Hi {guest_name}, gala is in 3 days: {link}',
    ]);

    $response->assertRedirect(route('events.reminders.index', $event->id));

    $reminder = Reminder::where('event_id', $event->id)->first();
    expect($reminder)->not->toBeNull();
    expect($reminder->preset)->toBe('3_days_before');
    expect($reminder->status)->toBe('pending');
    expect($reminder->scheduled_at->format('Y-m-d H:i'))->toBe('2026-12-22 09:00');
});

test('artisan reminders:dispatch command dispatches due reminders when quota is sufficient', function () {
    $tier = Tier::where('slug', 'silver')->first();
    $user = User::factory()->create();
    Subscription::create([
        'user_id' => $user->id,
        'tier_id' => $tier->id,
        'status' => 'active',
        'starts_at' => now(),
        'ends_at' => now()->addYear(),
        'cards_total' => 1000,
        'cards_remaining' => 1000,
    ]);

    $category = Category::first();
    $event = Event::create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'title' => 'Concert',
        'starts_at' => now()->addDays(2),
        'timezone' => 'Africa/Addis_Ababa',
        'status' => 'published',
        'design_json' => DesignValidator::blankDesign(),
    ]);

    $g1 = Guest::create([
        'event_id' => $event->id,
        'name' => 'Guest One',
        'phone' => '+251911111111',
        'rsvp_status' => 'pending',
        'opted_out' => false,
        'token' => Str::random(32),
    ]);

    $g2 = Guest::create([
        'event_id' => $event->id,
        'name' => 'Guest Opted Out',
        'phone' => '+251922222222',
        'rsvp_status' => 'pending',
        'opted_out' => true,
        'token' => Str::random(32),
    ]);

    $reminder = Reminder::create([
        'event_id' => $event->id,
        'preset' => '1_day_before',
        'channel' => 'sms',
        'target_audience' => 'rsvp_pending',
        'scheduled_at' => now()->subMinute(),
        'status' => 'pending',
        'subject' => 'Tomorrow',
        'message_body' => 'Event tomorrow {link}',
    ]);

    Artisan::call('reminders:dispatch');

    $reminder->refresh();
    expect($reminder->status)->toBe('sent');
    expect($reminder->campaign_id)->not->toBeNull();

    $campaign = Campaign::find($reminder->campaign_id);
    expect($campaign->type)->toBe('reminder');
    expect($campaign->audience_count)->toBe(1); // skipped opted-out guest

    $deliveries = Delivery::where('campaign_id', $campaign->id)->get();
    expect($deliveries)->toHaveCount(1);
    expect($deliveries->first()->guest_id)->toBe($g1->id);

    // Quota ledger record
    $ledger = UsageLedger::where('campaign_id', $campaign->id)->first();
    expect($ledger)->not->toBeNull();
    expect($ledger->kind)->toBe('reminder');
});

test('artisan reminders:dispatch blocks reminder when quota is insufficient and notifies host', function () {
    $tier = Tier::where('slug', 'silver')->first();
    $user = User::factory()->create();
    $sub = $user->subscriptions()->first();
    $sub->update([
        'tier_id' => $tier->id,
        'cards_total' => 10,
        'cards_used' => 10,
        'cards_remaining' => 0, // Exhausted quota
    ]);

    $category = Category::first();
    $event = Event::create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'title' => 'Exhausted Quota Event',
        'starts_at' => now()->addDays(1),
        'timezone' => 'Africa/Addis_Ababa',
        'status' => 'published',
        'design_json' => DesignValidator::blankDesign(),
    ]);

    Guest::create([
        'event_id' => $event->id,
        'name' => 'Teddy Afro',
        'phone' => '+251911334455',
        'rsvp_status' => 'pending',
        'token' => Str::random(32),
    ]);

    $reminder = Reminder::create([
        'event_id' => $event->id,
        'preset' => 'morning_of',
        'channel' => 'sms',
        'target_audience' => 'rsvp_pending',
        'scheduled_at' => now()->subMinute(),
        'status' => 'pending',
        'subject' => 'Morning reminder',
        'message_body' => 'See you today!',
    ]);

    Artisan::call('reminders:dispatch');

    $reminder->refresh();
    expect($reminder->status)->toBe('failed');
    expect($reminder->error)->toContain('Insufficient card quota');

    // Host must have received a HostAlertNotification
    $notification = $user->notifications()->first();
    expect($notification)->not->toBeNull();
    $data = is_array($notification->data) ? $notification->data : json_decode($notification->data, true);
    expect($data['type'])->toBe('reminder_failed');
    expect($data['title'])->toContain('Insufficient Quota');
});

test('event closing: host can manually close and reopen event', function () {
    $user = User::factory()->create();
    $category = Category::first();
    $event = Event::create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'title' => 'Anniversary Event',
        'starts_at' => now()->subHours(2),
        'timezone' => 'Africa/Addis_Ababa',
        'status' => 'published',
        'design_json' => DesignValidator::blankDesign(),
    ]);

    // Close event
    $res = $this->actingAs($user)->post(route('events.close', $event->id));
    $res->assertRedirect();
    expect($event->refresh()->status)->toBe('closed');

    // Reopen event
    $res2 = $this->actingAs($user)->post(route('events.reopen', $event->id));
    $res2->assertRedirect();
    expect($event->refresh()->status)->toBe('published');
});

test('event closing: artisan events:close-ended command marks ended events as closed', function () {
    $user = User::factory()->create();
    $category = Category::first();

    // Event 1: ended 30 minutes ago
    $pastEvent = Event::create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'title' => 'Concluded Event',
        'starts_at' => now()->subHours(4),
        'ends_at' => now()->subMinutes(30),
        'timezone' => 'Africa/Addis_Ababa',
        'status' => 'published',
        'design_json' => DesignValidator::blankDesign(),
    ]);

    // Event 2: ends tomorrow
    $futureEvent = Event::create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'title' => 'Upcoming Event',
        'starts_at' => now()->addDays(1),
        'ends_at' => now()->addDays(1)->addHours(4),
        'timezone' => 'Africa/Addis_Ababa',
        'status' => 'published',
        'design_json' => DesignValidator::blankDesign(),
    ]);

    Artisan::call('events:close-ended');

    expect($pastEvent->refresh()->status)->toBe('closed');
    expect($futureEvent->refresh()->status)->toBe('published');
});

test('event closing behavior: closed event disables scanner check-in and rejects RSVP updates', function () {
    $user = User::factory()->create();
    $category = Category::first();
    $event = Event::create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'title' => 'Closed Event',
        'starts_at' => now()->addDays(1), // start is future, but manually closed
        'timezone' => 'Africa/Addis_Ababa',
        'status' => 'closed',
        'is_invite_only' => true,
        'rsvp_enabled' => true,
        'design_json' => DesignValidator::blankDesign(),
    ]);

    $guest = Guest::create([
        'event_id' => $event->id,
        'name' => 'Kenenisa Bekele',
        'phone' => '+251911445566',
        'rsvp_status' => 'pending',
        'token' => Str::random(32),
    ]);

    $scanner = Scanner::create([
        'event_id' => $event->id,
        'name' => 'Door 1',
        'pin_hash' => Hash::make('1234'),
        'token' => Str::random(32),
    ]);

    // 1. RSVP attempt must fail because event is closed
    $rsvpResponse = $this->postJson(route('public.invitation.rsvp', ['token' => $guest->token]), [
        'rsvp_status' => 'yes',
        'rsvp_party_size' => 1,
    ]);

    $rsvpResponse->assertStatus(422);
    $rsvpResponse->assertJsonValidationErrors(['rsvp_status']);

    // 2. Scanner page shows Closed component
    $scanPageResponse = $this->get(route('scanner.show', ['token' => $scanner->token]));
    $scanPageResponse->assertOk();
    $scanPageResponse->assertInertia(fn (Assert $page) => $page
        ->component('Scanners/Closed')
        ->where('eventTitle', 'Closed Event')
    );

    // 3. API checkin rejects closed event
    $qrPayload = \App\Services\QrService::generatePayload($event->id, $guest->id);
    $apiResponse = $this->postJson(route('api.scan'), [
        'scanner_token' => $scanner->token,
        'qr_payload' => $qrPayload,
    ]);

    $apiResponse->assertStatus(422);
    expect($apiResponse->json('status'))->toBe('event_closed');
});

test('export csv contains rsvp, delivery status, and checkin status', function () {
    $user = User::factory()->create();
    $category = Category::first();
    $event = Event::create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'title' => 'Graduation Party',
        'starts_at' => now()->addDays(3),
        'timezone' => 'Africa/Addis_Ababa',
        'status' => 'published',
        'design_json' => DesignValidator::blankDesign(),
    ]);

    $scanner = Scanner::create([
        'event_id' => $event->id,
        'name' => 'Main Gate',
        'pin_hash' => Hash::make('1234'),
        'token' => Str::random(32),
    ]);

    $guest = Guest::create([
        'event_id' => $event->id,
        'name' => 'Tirunesh Dibaba',
        'phone' => '+251911556677',
        'email' => 'tirunesh@example.com',
        'preferred_channel' => 'sms',
        'max_party_size' => 3,
        'rsvp_status' => 'yes',
        'rsvp_party_size' => 3,
        'checked_in_at' => now(),
        'checked_in_by' => $scanner->id,
        'token' => Str::random(32),
    ]);

    $campaign = Campaign::create([
        'event_id' => $event->id,
        'user_id' => $user->id,
        'type' => 'invitation',
        'channel' => 'sms',
        'subject' => 'Invite',
        'body' => 'Hello',
        'status' => 'sent',
        'audience' => ['type' => 'all'],
        'audience_count' => 1,
    ]);

    Delivery::create([
        'campaign_id' => $campaign->id,
        'guest_id' => $guest->id,
        'channel' => 'sms',
        'status' => 'delivered',
        'quota_charged' => 1,
    ]);

    $response = $this->actingAs($user)->get(route('events.export.csv', $event->id));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    ob_start();
    $response->sendContent();
    $csvContent = ob_get_clean();

    expect($csvContent)->toContain('Guest Name');
    expect($csvContent)->toContain('Tirunesh Dibaba');
    expect($csvContent)->toContain('+251911556677');
    expect($csvContent)->toContain('YES'); // RSVP yes
    expect($csvContent)->toContain('Main Gate'); // Checked in by
    expect($csvContent)->toContain('DELIVERED'); // Delivery status
});

test('printable attendance sheet and door list render correctly', function () {
    $user = User::factory()->create();
    $category = Category::first();
    $event = Event::create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'title' => 'Cultural Festival',
        'starts_at' => now()->addDays(7),
        'timezone' => 'Africa/Addis_Ababa',
        'status' => 'published',
        'design_json' => DesignValidator::blankDesign(),
    ]);

    Guest::create([
        'event_id' => $event->id,
        'name' => 'Aster Aweke',
        'phone' => '+251911889900',
        'max_party_size' => 2,
        'rsvp_status' => 'yes',
        'token' => Str::random(32),
    ]);

    // Attendance sheet
    $sheetResponse = $this->actingAs($user)->get(route('events.export.attendance', $event->id));
    $sheetResponse->assertOk();
    $sheetResponse->assertInertia(fn (Assert $page) => $page
        ->component('Events/Exports/AttendanceSheet')
        ->where('event.title', 'Cultural Festival')
        ->has('guests', 1)
        ->where('guests.0.name', 'Aster Aweke')
    );

    // Door list
    $doorResponse = $this->actingAs($user)->get(route('events.export.door-list', $event->id));
    $doorResponse->assertOk();
    $doorResponse->assertInertia(fn (Assert $page) => $page
        ->component('Events/Exports/DoorList')
        ->where('event.title', 'Cultural Festival')
        ->has('guests', 1)
        ->where('guests.0.letter', 'A')
    );
});

test('host notification center: lists notifications, unread count, mark as read, mark all read', function () {
    $user = User::factory()->create();

    $user->notify(new HostAlertNotification(
        type: 'low_quota',
        title: 'Low Card Balance',
        message: 'You have only 5 cards left.',
        meta: ['available' => 5],
        actionUrl: route('plan.index')
    ));

    $user->notify(new HostAlertNotification(
        type: 'subscription_expiring',
        title: 'Plan Expiring Soon',
        message: 'Your plan expires in 3 days.',
        meta: ['days' => 3],
        actionUrl: route('plan.index')
    ));

    expect($user->unreadNotifications()->count())->toBe(2);

    $indexRes = $this->actingAs($user)->get(route('notifications.index'));
    $indexRes->assertOk();
    $indexRes->assertInertia(fn (Assert $page) => $page
        ->component('Notifications/Index')
        ->where('unreadCount', 2)
        ->has('notifications.data', 2)
    );

    $firstNotification = $user->unreadNotifications()->first();

    // Mark single as read
    $readRes = $this->actingAs($user)->post(route('notifications.read', $firstNotification->id));
    $readRes->assertRedirect();
    expect($firstNotification->refresh()->read_at)->not->toBeNull();
    expect($user->unreadNotifications()->count())->toBe(1);

    // Mark all as read
    $allReadRes = $this->actingAs($user)->post(route('notifications.mark-all-read'));
    $allReadRes->assertRedirect();
    expect($user->unreadNotifications()->count())->toBe(0);
});

test('subscriptions:check-expiring command alerts users whose subscription ends within 7 days', function () {
    $tier = Tier::where('slug', 'silver')->first();
    $user = User::factory()->create();
    $sub = $user->subscriptions()->first();
    $sub->update([
        'tier_id' => $tier->id,
        'status' => 'active',
        'expires_at' => now()->addDays(5),
    ]);

    Artisan::call('subscriptions:check-expiring');

    expect($user->notifications()->count())->toBe(1);
    $notification = $user->notifications()->first();
    $data = is_array($notification->data) ? $notification->data : json_decode($notification->data, true);
    expect($data['type'])->toBe('subscription_expiring');
    expect($data['title'])->toContain('Subscription Expiring');
});

test('my usage page aggregates cards by event, campaign kind, channel, and monthly timeline', function () {
    $tier = Tier::where('slug', 'silver')->first();
    $user = User::factory()->create();
    $sub = $user->subscriptions()->first();
    $sub->update([
        'tier_id' => $tier->id,
        'cards_total' => 5000,
        'cards_used' => 150,
        'cards_remaining' => 4850,
    ]);

    $category = Category::first();
    $event = Event::create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'title' => 'Charity Event',
        'starts_at' => now(),
        'status' => 'published',
        'design_json' => DesignValidator::blankDesign(),
    ]);

    // Record ledger entries
    UsageLedger::create([
        'user_id' => $user->id,
        'subscription_id' => $sub->id,
        'event_id' => $event->id,
        'kind' => 'invitation',
        'channel' => 'sms',
        'quantity' => 100,
        'created_at' => now(),
    ]);

    UsageLedger::create([
        'user_id' => $user->id,
        'subscription_id' => $sub->id,
        'event_id' => $event->id,
        'kind' => 'reminder',
        'channel' => 'email',
        'quantity' => 50,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('usage.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Usage/Index')
        ->where('overview.total', 5000)
        ->where('overview.available', 4850)
        ->where('overview.used', 150)
        ->has('byEvent', 1)
        ->where('byEvent.0.title', 'Charity Event')
        ->where('byEvent.0.cards', 150)
        ->has('byKind')
        ->has('byChannel')
        ->has('monthlyTimeline')
        ->has('recentTransactions', 2)
    );
});
