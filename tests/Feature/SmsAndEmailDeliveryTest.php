<?php

use App\Contracts\SmsGateway;
use App\Jobs\SendDeliveryJob;
use App\Mail\InvitationEmail;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\Event;
use App\Models\Guest;
use App\Models\ShortLink;
use App\Models\Subscription;
use App\Models\Tier;
use App\Models\UsageLedger;
use App\Models\User;
use App\Services\MessageTemplateService;
use App\Services\QuotaService;
use App\Services\ShortLinkService;
use App\Services\Sms\AfroMessageGateway;
use App\Services\Sms\GeezSmsGateway;
use App\Services\Sms\LogSmsGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    LogSmsGateway::clearSent();
    Cache::flush();
    if (! Tier::where('is_default', true)->exists()) {
        (new \Database\Seeders\TierSeeder())->run();
    }
});

test('sms gateway drivers behave correctly', function () {
    // 1. Log Gateway
    $logGateway = new LogSmsGateway();
    $logResult = $logGateway->send('+251911223344', 'Test log message');
    expect($logResult->isSuccessful())->toBeTrue()
        ->and($logResult->messageId)->toStartWith('log_')
        ->and(LogSmsGateway::$sentMessages)->toHaveCount(1);

    // 2. AfroMessage Gateway Success & Failure with sequence
    Http::fake([
        'https://api.afromessage.com/api/send' => Http::sequence()
            ->push([
                'acknowledge' => 'success',
                'response' => [
                    'status' => 'success',
                    'message_id' => 'afro-test-999',
                ],
            ], 200)
            ->push([
                'acknowledge' => 'error',
                'response' => [
                    'errors' => ['Invalid recipient phone number'],
                ],
            ], 400),
    ]);

    $afroGateway = new AfroMessageGateway(token: 'fake-token', senderId: 'INVITE');
    $afroResult = $afroGateway->send('+251911223344', 'Afro message content');
    expect($afroResult->isSuccessful())->toBeTrue()
        ->and($afroResult->messageId)->toBe('afro-test-999');

    $afroFailResult = $afroGateway->send('+251000000000', 'Fail message');
    expect($afroFailResult->isSuccessful())->toBeFalse()
        ->and($afroFailResult->error)->toBe('Invalid recipient phone number');

    // 3. GeezSMS Gateway Stub
    $geezGateway = new GeezSmsGateway();
    $geezResult = $geezGateway->send('+251911223344', 'Geez message content');
    expect($geezResult->isSuccessful())->toBeTrue()
        ->and($geezResult->messageId)->toStartWith('geez_');
});

test('short link service creates 8-character codes and redirects with click tracking', function () {
    $service = app(ShortLinkService::class);
    $targetUrl = 'https://example.com/invitations/abc123xyz';

    $shortLink = $service->create($targetUrl);

    expect(strlen($shortLink->code))->toBe(8)
        ->and($shortLink->clicks)->toBe(0)
        ->and($shortLink->target_url)->toBe($targetUrl);

    // Test redirection endpoint
    $response = $this->get('/s/' . $shortLink->code);
    $response->assertRedirect($targetUrl);

    $shortLink->refresh();
    expect($shortLink->clicks)->toBe(1);

    // Subsequent access increments click count
    $this->get('/s/' . $shortLink->code);
    expect($shortLink->fresh()->clicks)->toBe(2);

    // Non-existent code returns 404
    $this->get('/s/nonexistent')->assertNotFound();
});

test('message template replaces merge placeholders correctly in en and am', function () {
    $service = app(MessageTemplateService::class);

    $data = [
        'guest_name' => 'Sara Haile',
        'event_title' => 'Wedding Celebration',
        'date' => '2026-11-20',
        'time' => '4:00 PM',
        'venue' => 'Skylight Hotel',
        'host_names' => 'Dawit & Sara',
        'link' => 'https://invite.et/s/xyz89012',
    ];

    $renderedEn = $service->render(MessageTemplateService::DEFAULT_INVITATION_EN, $data);
    expect($renderedEn)->toContain('Dawit & Sara')
        ->and($renderedEn)->toContain('Wedding Celebration')
        ->and($renderedEn)->toContain('2026-11-20')
        ->and($renderedEn)->toContain('Skylight Hotel')
        ->and($renderedEn)->toContain('https://invite.et/s/xyz89012');

    $renderedAm = $service->render(MessageTemplateService::DEFAULT_INVITATION_AM, $data);
    expect($renderedAm)->toContain('Dawit & Sara')
        ->and($renderedAm)->toContain('Wedding Celebration')
        ->and($renderedAm)->toContain('ጋብዘዎታል።')
        ->and($renderedAm)->toContain('https://invite.et/s/xyz89012');
});

test('segment calculator distinguishes GSM-7 and Unicode (Amharic) segments', function () {
    $service = app(MessageTemplateService::class);

    // GSM-7 Single segment
    $gsmSingle = $service->calculateSegments('Hello! You are warmly invited to our celebration.');
    expect($gsmSingle['encoding'])->toBe('gsm7')
        ->and($gsmSingle['segment_count'])->toBe(1)
        ->and($gsmSingle['character_count'])->toBe(49)
        ->and($gsmSingle['remaining'])->toBe(160 - 49);

    // GSM-7 Multi segment (> 160 characters)
    $longGsm = str_repeat('A', 170);
    $gsmMulti = $service->calculateSegments($longGsm);
    expect($gsmMulti['encoding'])->toBe('gsm7')
        ->and($gsmMulti['segment_count'])->toBe(2)
        ->and($gsmMulti['remaining'])->toBe((2 * 153) - 170);

    // Unicode (Amharic) Single segment (<= 70 characters)
    $amharicSingle = $service->calculateSegments('እንኳን ደስ አላችሁ! ለሰርጋችን ተጋብዘዋል');
    expect($amharicSingle['encoding'])->toBe('unicode')
        ->and($amharicSingle['segment_count'])->toBe(1)
        ->and($amharicSingle['remaining'])->toBe(70 - mb_strlen('እንኳን ደስ አላችሁ! ለሰርጋችን ተጋብዘዋል', 'UTF-8'));

    // Unicode (Amharic) Multi segment (> 70 characters)
    $longAmharic = str_repeat('ሰላም ', 20); // 100 characters
    $amharicMulti = $service->calculateSegments($longAmharic);
    expect($amharicMulti['encoding'])->toBe('unicode')
        ->and($amharicMulti['segment_count'])->toBe(2);
});

test('campaign create review screen renders with recipient breakdown and quota info', function () {
    $user = User::factory()->create();
    $user->subscriptions()->update([
        'cards_total' => 100,
        'cards_used' => 10,
    ]);

    $event = Event::factory()->create(['user_id' => $user->id]);

    // Create 3 active guests with phone and email, and 1 opted-out guest
    Guest::factory()->create(['event_id' => $event->id, 'phone' => '+251911000001', 'email' => 'g1@example.com', 'opted_out' => false]);
    Guest::factory()->create(['event_id' => $event->id, 'phone' => '+251911000002', 'email' => null, 'opted_out' => false]);
    Guest::factory()->create(['event_id' => $event->id, 'phone' => null, 'email' => 'g3@example.com', 'opted_out' => false]);
    Guest::factory()->create(['event_id' => $event->id, 'phone' => '+251911000004', 'email' => 'g4@example.com', 'opted_out' => true]);

    $response = $this->actingAs($user)->get(route('events.campaigns.create', $event->id));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Campaigns/Create')
        ->has('stats', fn ($stats) => $stats
            ->where('total_guests', 4)
            ->where('opted_out_count', 1)
            ->where('sms_eligible_count', 2)
            ->where('email_eligible_count', 2)
            ->where('both_eligible_count', 3)
            ->where('both_complete_count', 1)
        )
        ->where('availableCards', 90)
    );
});

test('free test send sends message to host and is throttled to 3 per day', function () {
    $user = User::factory()->create(['phone' => '+251911223344', 'email' => 'host@example.com']);
    $event = Event::factory()->create(['user_id' => $user->id]);

    // Test SMS
    $response = $this->actingAs($user)->post(route('events.campaigns.test-send', $event->id), [
        'channel' => 'sms',
        'phone' => '+251911223344',
        'body' => 'Test message to {host_names}',
    ]);

    $response->assertSessionHas('success');
    expect(LogSmsGateway::$sentMessages)->toHaveCount(1);

    // Test Email
    Mail::fake();
    $this->actingAs($user)->post(route('events.campaigns.test-send', $event->id), [
        'channel' => 'email',
        'email' => 'host@example.com',
        'body' => 'Test email body',
        'subject' => 'Test Subject',
    ]);
    Mail::assertSent(InvitationEmail::class);

    // Send 3rd test
    $this->actingAs($user)->post(route('events.campaigns.test-send', $event->id), [
        'channel' => 'sms',
        'phone' => '+251911223344',
        'body' => '3rd Test',
    ]);

    // 4th test should be rejected by throttle
    $failedResponse = $this->actingAs($user)->post(route('events.campaigns.test-send', $event->id), [
        'channel' => 'sms',
        'phone' => '+251911223344',
        'body' => '4th Test',
    ]);
    $failedResponse->assertSessionHasErrors('test_send');
});

test('campaign creation reserves exact quota based on tier weights and skips opted out guests', function () {
    Queue::fake();

    $user = User::factory()->create();
    $tier = Tier::factory()->create([
        'sms_weight' => 2,
        'email_weight' => 1,
    ]);

    $user->subscriptions()->update([
        'tier_id' => $tier->id,
        'cards_total' => 100,
        'cards_used' => 0,
    ]);
    $subscription = $user->subscriptions()->first();

    $event = Event::factory()->create(['user_id' => $user->id]);

    // 3 active guests with phones, 1 opted out guest
    Guest::factory()->create(['event_id' => $event->id, 'phone' => '+251911000001', 'opted_out' => false]);
    Guest::factory()->create(['event_id' => $event->id, 'phone' => '+251911000002', 'opted_out' => false]);
    Guest::factory()->create(['event_id' => $event->id, 'phone' => '+251911000003', 'opted_out' => false]);
    Guest::factory()->create(['event_id' => $event->id, 'phone' => '+251911000004', 'opted_out' => true]);

    // SMS campaign for 3 active guests at weight 2 = 6 cards
    $response = $this->actingAs($user)->post(route('events.campaigns.store', $event->id), [
        'channel' => 'sms',
        'body' => 'Hello {guest_name}, see {link}',
    ]);

    $response->assertRedirect(route('events.deliveries', $event->id));

    $subscription->refresh();
    expect($subscription->cards_used)->toBe(6);

    // Ledger should record 6 cards consumed
    $ledgerEntry = UsageLedger::where('user_id', $user->id)->where('event_id', $event->id)->first();
    expect($ledgerEntry)->not->toBeNull()
        ->and($ledgerEntry->quantity)->toBe(6);

    // Deliveries created: exactly 3 deliveries (opted-out was skipped!)
    $deliveries = Delivery::all();
    expect($deliveries)->toHaveCount(3);

    Queue::assertPushed(SendDeliveryJob::class, 3);
});

test('insufficient quota blocks campaign creation with 422 error and upgrade prompt', function () {
    $user = User::factory()->create();
    $user->subscriptions()->update([
        'cards_total' => 5,
        'cards_used' => 4, // Only 1 card available
    ]);

    $event = Event::factory()->create(['user_id' => $user->id]);

    // 5 guests requiring 5 cards
    Guest::factory()->count(5)->create(['event_id' => $event->id, 'phone' => '+251911000000', 'opted_out' => false]);

    $response = $this->actingAs($user)->post(route('events.campaigns.store', $event->id), [
        'channel' => 'sms',
        'body' => 'Hello {guest_name}',
    ]);

    $response->assertSessionHasErrors(['quota']);
    expect(Campaign::count())->toBe(0)
        ->and(Delivery::count())->toBe(0);
});

test('send delivery job sends SMS and updates delivery to sent', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);
    $guest = Guest::factory()->create(['event_id' => $event->id, 'phone' => '+251911998877', 'opted_out' => false]);
    $campaign = Campaign::factory()->create(['event_id' => $event->id, 'user_id' => $user->id, 'body' => 'Welcome {guest_name}: {link}']);

    $delivery = Delivery::create([
        'campaign_id' => $campaign->id,
        'guest_id' => $guest->id,
        'channel' => 'sms',
        'status' => 'queued',
        'quota_charged' => 1,
    ]);

    $job = new SendDeliveryJob($delivery->id);
    $job->handle(
        app(SmsGateway::class),
        app(ShortLinkService::class),
        app(MessageTemplateService::class),
        app(QuotaService::class)
    );

    $delivery->refresh();
    expect($delivery->status)->toBe('sent')
        ->and($delivery->sent_at)->not->toBeNull()
        ->and($delivery->provider_message_id)->toStartWith('log_')
        ->and(LogSmsGateway::$sentMessages)->toHaveCount(1)
        ->and(ShortLink::count())->toBe(1);
});

test('send delivery job refunds quota when delivery permanently fails', function () {
    $user = User::factory()->create();
    $user->subscriptions()->update([
        'cards_total' => 20,
        'cards_used' => 5,
    ]);
    $sub = $user->subscriptions()->first();

    $event = Event::factory()->create(['user_id' => $user->id]);
    $guest = Guest::factory()->create(['event_id' => $event->id, 'phone' => '+251911000000']);
    $campaign = Campaign::factory()->create(['event_id' => $event->id, 'user_id' => $user->id]);

    $delivery = Delivery::create([
        'campaign_id' => $campaign->id,
        'guest_id' => $guest->id,
        'channel' => 'sms',
        'status' => 'queued',
        'quota_charged' => 2,
    ]);

    $job = new SendDeliveryJob($delivery->id);
    $job->failed(new Exception('Network timeout after 3 retries'));

    $delivery->refresh();
    expect($delivery->status)->toBe('failed')
        ->and($delivery->error)->toContain('Network timeout')
        ->and($delivery->quota_charged)->toBe(0);

    // Subscription cards_used decremented by 2
    $sub->refresh();
    expect($sub->cards_used)->toBe(3);

    // Negative ledger entry created
    $refundLedger = UsageLedger::where('user_id', $user->id)->where('kind', 'refund')->first();
    expect($refundLedger)->not->toBeNull()
        ->and($refundLedger->quantity)->toBe(-2);
});

test('sms webhook idempotently updates status and refunds quota on permanent failure', function () {
    $user = User::factory()->create();
    $user->subscriptions()->update([
        'cards_total' => 50,
        'cards_used' => 10,
    ]);
    $sub = $user->subscriptions()->first();

    $event = Event::factory()->create(['user_id' => $user->id]);
    $guest = Guest::factory()->create(['event_id' => $event->id]);
    $campaign = Campaign::factory()->create(['event_id' => $event->id, 'user_id' => $user->id]);

    $delivery = Delivery::create([
        'campaign_id' => $campaign->id,
        'guest_id' => $guest->id,
        'channel' => 'sms',
        'status' => 'sent',
        'provider_message_id' => 'am-webhook-test-123',
        'quota_charged' => 1,
    ]);

    // 1. Success webhook
    $response = $this->postJson(route('api.webhooks.sms', ['provider' => 'afromessage']), [
        'message_id' => 'am-webhook-test-123',
        'status' => 'delivered',
    ]);
    $response->assertOk();
    expect($delivery->fresh()->status)->toBe('delivered');

    // 2. Failure webhook on a different delivery
    $delivery2 = Delivery::create([
        'campaign_id' => $campaign->id,
        'guest_id' => $guest->id,
        'channel' => 'sms',
        'status' => 'sent',
        'provider_message_id' => 'am-webhook-fail-456',
        'quota_charged' => 2,
    ]);

    $response2 = $this->postJson(route('api.webhooks.sms', ['provider' => 'afromessage']), [
        'message_id' => 'am-webhook-fail-456',
        'status' => 'undelivered',
        'error' => 'Handset switched off',
    ]);
    $response2->assertOk();

    $delivery2->refresh();
    expect($delivery2->status)->toBe('failed')
        ->and($delivery2->error)->toContain('Handset switched off')
        ->and($delivery2->quota_charged)->toBe(0);

    // Quota refunded
    expect($sub->fresh()->cards_used)->toBe(8);

    // 3. Repeated webhook call does not double refund
    $this->postJson(route('api.webhooks.sms', ['provider' => 'afromessage']), [
        'message_id' => 'am-webhook-fail-456',
        'status' => 'failed',
    ]);
    expect($sub->fresh()->cards_used)->toBe(8);
});

test('resend actions re-queue deliveries and charge quota for previously refunded messages', function () {
    Queue::fake();

    $user = User::factory()->create();
    $user->subscriptions()->update([
        'cards_total' => 50,
        'cards_used' => 5,
    ]);
    $sub = $user->subscriptions()->first();

    $event = Event::factory()->create(['user_id' => $user->id]);
    $guest = Guest::factory()->create(['event_id' => $event->id, 'rsvp_status' => 'pending']);
    $campaign = Campaign::factory()->create(['event_id' => $event->id, 'user_id' => $user->id]);

    // Failed delivery with quota_charged = 0 (because it was refunded)
    $delivery = Delivery::create([
        'campaign_id' => $campaign->id,
        'guest_id' => $guest->id,
        'channel' => 'sms',
        'status' => 'failed',
        'error' => 'Network drop',
        'quota_charged' => 0,
    ]);

    // Resend single delivery
    $response = $this->actingAs($user)->post(route('events.deliveries.resend', [
        'event' => $event->id,
        'delivery' => $delivery->id,
    ]));

    $response->assertSessionHas('success');
    $delivery->refresh();
    expect($delivery->status)->toBe('queued')
        ->and($delivery->error)->toBeNull()
        ->and($delivery->quota_charged)->toBe(1)
        ->and($sub->fresh()->cards_used)->toBe(6);

    Queue::assertPushed(SendDeliveryJob::class, 1);
});

test('send delivery job sends email and updates delivery to sent', function () {
    Mail::fake();

    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);
    $guest = Guest::factory()->create(['event_id' => $event->id, 'email' => 'guest@example.com', 'opted_out' => false]);
    $campaign = Campaign::factory()->create([
        'event_id' => $event->id,
        'user_id' => $user->id,
        'subject' => 'Invitation to {event_title}',
        'body' => 'Dear {guest_name}, please join us: {link}',
    ]);

    $delivery = Delivery::create([
        'campaign_id' => $campaign->id,
        'guest_id' => $guest->id,
        'channel' => 'email',
        'status' => 'queued',
        'quota_charged' => 1,
    ]);

    $job = new SendDeliveryJob($delivery->id);
    $job->handle(
        app(SmsGateway::class),
        app(ShortLinkService::class),
        app(MessageTemplateService::class),
        app(QuotaService::class)
    );

    $delivery->refresh();
    expect($delivery->status)->toBe('sent')
        ->and($delivery->sent_at)->not->toBeNull();

    Mail::assertSent(InvitationEmail::class, function ($mail) use ($guest) {
        return $mail->hasTo($guest->email);
    });
});

test('concurrent sends cannot oversell available quota', function () {
    Queue::fake();

    $user = User::factory()->create();
    // Set exactly 10 cards available
    $user->subscriptions()->update([
        'cards_total' => 10,
        'cards_used' => 0,
    ]);

    $event1 = Event::factory()->create(['user_id' => $user->id]);
    $event2 = Event::factory()->create(['user_id' => $user->id]);

    // Create 8 guests in event 1 and 8 guests in event 2 (total 16, but only 10 available)
    for ($i = 0; $i < 8; $i++) {
        Guest::factory()->create(['event_id' => $event1->id, 'phone' => "+25191100100{$i}"]);
        Guest::factory()->create(['event_id' => $event2->id, 'phone' => "+25191100200{$i}"]);
    }

    // First campaign requests 8 cards -> should succeed
    $resp1 = $this->actingAs($user)->post(route('events.campaigns.store', $event1->id), [
        'channel' => 'sms',
        'body' => 'Event 1 invitation',
    ]);
    $resp1->assertRedirect();

    // Second campaign requests 8 cards -> should fail because only 2 cards remain
    $resp2 = $this->actingAs($user)->post(route('events.campaigns.store', $event2->id), [
        'channel' => 'sms',
        'body' => 'Event 2 invitation',
    ]);
    $resp2->assertSessionHasErrors('quota');

    // Subscriptions cards_used must be exactly 8, never oversold
    expect($user->subscriptions()->first()->fresh()->cards_used)->toBe(8);
});
