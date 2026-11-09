<?php

use App\Contracts\SmsGateway;
use App\Jobs\SendDeliveryJob;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Setting;
use App\Models\ShortLink;
use App\Models\Subscription;
use App\Models\Tier;
use App\Models\UsageLedger;
use App\Models\User;
use App\Services\AudienceService;
use App\Services\ContentSafeguardService;
use App\Services\MessageTemplateService;
use App\Services\QuotaService;
use App\Services\ShortLinkService;
use App\Services\Sms\LogSmsGateway;
use App\Services\UrlSafetyService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    LogSmsGateway::clearSent();
    Cache::flush();
    if (! Tier::where('is_default', true)->exists()) {
        (new \Database\Seeders\TierSeeder())->run();
    }
});

test('feature gating: free tier user without custom_messages cannot access custom message composer or submit custom campaign', function () {
    $user = User::factory()->create();
    // Free default tier has custom_messages = false
    $event = Event::factory()->create(['user_id' => $user->id]);

    // Attempt to access custom message screen
    $response = $this->actingAs($user)->get(route('events.messages.create', $event->id));
    $response->assertRedirect(route('plan.index'));
    $response->assertSessionHasErrors('feature');

    // Attempt to post a custom campaign
    $postResponse = $this->actingAs($user)->post(route('events.campaigns.store', $event->id), [
        'type' => 'custom',
        'channel' => 'sms',
        'body' => 'Custom announcement to guests',
        'link_mode' => 'none',
        'audience_type' => 'all',
    ]);
    $postResponse->assertSessionHasErrors('custom_messages');
});

test('feature gating: user without custom_links cannot submit custom_url link_mode', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);

    // Create tier with custom_messages = true but custom_links = false
    $tier = Tier::factory()->create([
        'features' => [
            'sms_channel' => true,
            'email_channel' => true,
            'custom_messages' => true,
            'custom_links' => false,
        ],
    ]);
    $user->subscriptions()->update(['tier_id' => $tier->id, 'cards_total' => 100, 'cards_used' => 0]);

    Guest::factory()->create(['event_id' => $event->id, 'phone' => '+251911000001', 'opted_out' => false]);

    $response = $this->actingAs($user)->post(route('events.campaigns.store', $event->id), [
        'type' => 'custom',
        'channel' => 'sms',
        'body' => 'Message with custom link: {link}',
        'link_mode' => 'custom_url',
        'custom_url' => 'https://mywebsite.com',
        'audience_type' => 'all',
    ]);

    $response->assertSessionHasErrors('custom_links');
});

test('custom url safety: validates https, blocks IPs, shorteners, blocklisted domains, and lookalikes', function () {
    $safetyService = app(UrlSafetyService::class);

    // 1. HTTP rejected (HTTPS only)
    $httpResult = $safetyService->validate('http://example.com/invitation');
    expect($httpResult['valid'])->toBeFalse()
        ->and($httpResult['error'])->toContain('HTTPS');

    // 2. IP address host rejected
    $ipResult = $safetyService->validate('https://192.168.1.1/event');
    expect($ipResult['valid'])->toBeFalse()
        ->and($ipResult['error'])->toContain('IP-address');

    // 3. Third-party URL shorteners rejected
    $bitlyResult = $safetyService->validate('https://bit.ly/custom-invite');
    expect($bitlyResult['valid'])->toBeFalse()
        ->and($bitlyResult['error'])->toContain('shorteners are prohibited');

    $tinyResult = $safetyService->validate('https://tinyurl.com/event123');
    expect($tinyResult['valid'])->toBeFalse()
        ->and($tinyResult['error'])->toContain('shorteners are prohibited');

    // 4. Blocklisted domain rejected
    Setting::updateOrCreate(
        ['key' => 'blocked_domains'],
        ['value' => ['evil-scam.com', 'badphish.org']]
    );

    $blockedResult = $safetyService->validate('https://evil-scam.com/celebration');
    expect($blockedResult['valid'])->toBeFalse()
        ->and($blockedResult['error'])->toContain('safety blocklist');

    // 5. Lookalike / phishing pattern rejected
    $phishResult = $safetyService->validate('https://telebirr-login.net/reward');
    expect($phishResult['valid'])->toBeFalse()
        ->and($phishResult['error'])->toContain('suspicious lookalike');

    // 6. Valid safe HTTPS destination approved
    $validResult = $safetyService->validate('https://sheger-events.com/program');
    expect($validResult['valid'])->toBeTrue()
        ->and($validResult['error'])->toBeNull();
});

test('custom url safety: flagged domain triggers interstitial warning page before redirecting', function () {
    Setting::updateOrCreate(
        ['key' => 'flagged_domains'],
        ['value' => ['unverified-party.org']]
    );

    $service = app(ShortLinkService::class);
    $shortLink = $service->create(
        targetUrl: 'https://unverified-party.org/details',
        campaignId: null,
        isFlagged: true
    );

    // Initial visit without ?proceed=1 returns interstitial warning view
    $response = $this->get('/s/' . $shortLink->code);
    $response->assertOk();
    $response->assertSee('External Website Warning');
    $response->assertSee('https://unverified-party.org/details');
    expect($shortLink->fresh()->clicks)->toBe(0);

    // Proceeding with ?proceed=1 redirects and increments clicks
    $proceedResponse = $this->get('/s/' . $shortLink->code . '?proceed=1');
    $proceedResponse->assertRedirect('https://unverified-party.org/details');
    expect($shortLink->fresh()->clicks)->toBe(1);
});

test('new account sending custom external URL is held in pending_review status without immediate dispatch', function () {
    Queue::fake();

    // User created today (< 3 days old)
    $user = User::factory()->create([
        'created_at' => now(),
    ]);

    $tier = Tier::factory()->create([
        'features' => [
            'sms_channel' => true,
            'email_channel' => true,
            'custom_messages' => true,
            'custom_links' => true,
        ],
    ]);
    $user->subscriptions()->update(['tier_id' => $tier->id, 'cards_total' => 100, 'cards_used' => 0]);

    $event = Event::factory()->create(['user_id' => $user->id]);
    Guest::factory()->create(['event_id' => $event->id, 'phone' => '+251911000001', 'opted_out' => false]);

    $response = $this->actingAs($user)->post(route('events.campaigns.store', $event->id), [
        'type' => 'custom',
        'channel' => 'sms',
        'body' => 'Event update: {link}',
        'link_mode' => 'custom_url',
        'custom_url' => 'https://sheger-events.com/schedule',
        'audience_type' => 'all',
    ]);

    $response->assertRedirect(route('events.campaigns.index', $event->id));
    $response->assertSessionHas('info');

    $campaign = Campaign::first();
    expect($campaign->status)->toBe('pending_review');

    // No delivery jobs dispatched while pending review
    Queue::assertNothingPushed();
});

test('audience filtering resolves statuses, checkin, view counts, and exclusions accurately', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);
    $audienceService = app(AudienceService::class);

    // Seed diverse guests
    $g1 = Guest::factory()->create(['event_id' => $event->id, 'rsvp_status' => 'yes', 'checked_in_at' => now(), 'viewed_at' => now(), 'phone' => '+251911111111', 'opted_out' => false]);
    $g2 = Guest::factory()->create(['event_id' => $event->id, 'rsvp_status' => 'no', 'checked_in_at' => null, 'viewed_at' => now(), 'phone' => '+251911222222', 'opted_out' => false]);
    $g3 = Guest::factory()->create(['event_id' => $event->id, 'rsvp_status' => 'pending', 'checked_in_at' => null, 'viewed_at' => null, 'phone' => '+251911333333', 'opted_out' => false]);
    $g4 = Guest::factory()->create(['event_id' => $event->id, 'rsvp_status' => 'yes', 'checked_in_at' => null, 'viewed_at' => now(), 'phone' => null, 'email' => 'g4@example.com', 'opted_out' => false]);
    $g5 = Guest::factory()->create(['event_id' => $event->id, 'rsvp_status' => 'yes', 'checked_in_at' => now(), 'viewed_at' => now(), 'phone' => '+251911555555', 'opted_out' => true]); // Opted out

    // 1. Audience: RSVP Yes for SMS
    // Guests matching rsvp_yes: g1, g4, g5. g5 is opted out. g4 has no phone. g1 is eligible.
    $yesSms = $audienceService->resolve($event, 'rsvp_yes', [], 'sms');
    expect($yesSms['stats']['total_audience'])->toBe(3)
        ->and($yesSms['stats']['opted_out_excluded'])->toBe(1)
        ->and($yesSms['stats']['missing_contact_excluded'])->toBe(1)
        ->and($yesSms['stats']['eligible_recipients'])->toBe(1)
        ->and($yesSms['eligible_guests']->first()->id)->toBe($g1->id);

    // 2. Audience: Checked In (Attended) for SMS
    // Matching: g1, g5. g5 is opted out. g1 eligible.
    $checkedIn = $audienceService->resolve($event, 'checked_in', [], 'sms');
    expect($checkedIn['stats']['eligible_recipients'])->toBe(1)
        ->and($checkedIn['eligible_guests']->first()->id)->toBe($g1->id);

    // 3. Audience: Not Yet Viewed
    $notViewed = $audienceService->resolve($event, 'not_viewed', [], 'sms');
    expect($notViewed['stats']['eligible_recipients'])->toBe(1)
        ->and($notViewed['eligible_guests']->first()->id)->toBe($g3->id);

    // 4. Audience: Manual Selection
    $manual = $audienceService->resolve($event, 'manual', [$g1->id, $g2->id], 'sms');
    expect($manual['stats']['eligible_recipients'])->toBe(2);
});

test('content safeguards: prohibited keywords are rejected with 422 error', function () {
    $user = User::factory()->create();
    $tier = Tier::factory()->create([
        'features' => ['sms_channel' => true, 'email_channel' => true, 'custom_messages' => true, 'custom_links' => true],
    ]);
    $user->subscriptions()->update(['tier_id' => $tier->id, 'cards_total' => 100, 'cards_used' => 0]);
    $event = Event::factory()->create(['user_id' => $user->id]);
    Guest::factory()->create(['event_id' => $event->id, 'phone' => '+251911000001', 'opted_out' => false]);

    $response = $this->actingAs($user)->post(route('events.campaigns.store', $event->id), [
        'type' => 'custom',
        'channel' => 'sms',
        'body' => 'Congratulations! You won cash wire transfer lottery winner prize!',
        'link_mode' => 'none',
        'audience_type' => 'all',
    ]);

    $response->assertSessionHasErrors('body');
    expect(Campaign::count())->toBe(0);
});

test('content safeguards: new account daily send cap prevents excessive sending', function () {
    // User created today (< 3 days)
    $user = User::factory()->create(['created_at' => now()]);
    $tier = Tier::factory()->create([
        'features' => ['sms_channel' => true, 'email_channel' => true, 'custom_messages' => true, 'custom_links' => true],
    ]);
    $user->subscriptions()->update(['tier_id' => $tier->id, 'cards_total' => 200, 'cards_used' => 0]);

    Setting::updateOrCreate(['key' => 'new_account_daily_cap'], ['value' => 30]);

    $event = Event::factory()->create(['user_id' => $user->id]);
    // Create 35 guests
    for ($i = 0; $i < 35; $i++) {
        Guest::factory()->create(['event_id' => $event->id, 'phone' => "+2519111000{$i}"]);
    }

    $response = $this->actingAs($user)->post(route('events.campaigns.store', $event->id), [
        'type' => 'custom',
        'channel' => 'sms',
        'body' => 'Important update regarding our celebration',
        'link_mode' => 'none',
        'audience_type' => 'all',
    ]);

    $response->assertSessionHasErrors('quota');
    expect(Campaign::count())->toBe(0);
});

test('scheduling: campaign is stored as scheduled and quota is only reserved when dispatched', function () {
    Queue::fake();

    $user = User::factory()->create();
    $tier = Tier::factory()->create([
        'features' => ['sms_channel' => true, 'email_channel' => true, 'custom_messages' => true, 'custom_links' => true],
    ]);
    $user->subscriptions()->update(['tier_id' => $tier->id, 'cards_total' => 50, 'cards_used' => 5]);
    $sub = $user->subscriptions()->first();

    $event = Event::factory()->create(['user_id' => $user->id]);
    Guest::factory()->count(4)->create(['event_id' => $event->id, 'phone' => '+251911000000', 'opted_out' => false]);

    $futureTime = now()->addHours(2);

    // Schedule campaign
    $response = $this->actingAs($user)->post(route('events.campaigns.store', $event->id), [
        'type' => 'custom',
        'channel' => 'sms',
        'body' => 'Scheduled announcement to all guests',
        'link_mode' => 'none',
        'audience_type' => 'all',
        'scheduled_at' => $futureTime->toDateTimeString(),
    ]);

    $response->assertRedirect(route('events.campaigns.index', $event->id));

    $campaign = Campaign::first();
    expect($campaign->status)->toBe('scheduled')
        ->and($campaign->scheduled_at->toDateTimeString())->toBe($futureTime->toDateTimeString());

    // Quota must NOT be reserved at schedule time!
    expect($sub->fresh()->cards_used)->toBe(5);
    Queue::assertNothingPushed();

    // User cancels the scheduled campaign
    $cancelResponse = $this->actingAs($user)->post(route('events.campaigns.cancel', [
        'event' => $event->id,
        'campaign' => $campaign->id,
    ]));
    $cancelResponse->assertSessionHas('success');
    expect($campaign->fresh()->status)->toBe('cancelled');
});

test('scheduling: artisan campaigns:dispatch-scheduled command dispatches due campaigns and reserves quota', function () {
    Queue::fake();

    $user = User::factory()->create();
    $tier = Tier::factory()->create([
        'features' => ['sms_channel' => true, 'email_channel' => true, 'custom_messages' => true, 'custom_links' => true],
    ]);
    $user->subscriptions()->update(['tier_id' => $tier->id, 'cards_total' => 50, 'cards_used' => 0]);
    $sub = $user->subscriptions()->first();

    $event = Event::factory()->create(['user_id' => $user->id]);
    Guest::factory()->count(3)->create(['event_id' => $event->id, 'phone' => '+251911000000', 'opted_out' => false]);

    // Create due scheduled campaign (scheduled 5 minutes ago)
    $campaign = Campaign::create([
        'event_id' => $event->id,
        'user_id' => $user->id,
        'type' => 'custom',
        'channel' => 'sms',
        'body' => 'Announcement now running {link}',
        'link_mode' => 'none',
        'audience' => ['type' => 'all'],
        'scheduled_at' => now()->subMinutes(5),
        'status' => 'scheduled',
    ]);

    // Execute scheduler command
    Artisan::call('campaigns:dispatch-scheduled');

    $campaign->refresh();
    expect($campaign->status)->toBe('sending');

    // Quota now reserved: 3 cards
    expect($sub->fresh()->cards_used)->toBe(3);

    // Deliveries created and jobs pushed
    expect(Delivery::where('campaign_id', $campaign->id)->count())->toBe(3);
    Queue::assertPushed(SendDeliveryJob::class, 3);
});

test('link mode substitution: substitutes short link for guest invitation, event page, and custom url', function () {
    $user = User::factory()->create();
    $tier = Tier::factory()->create([
        'features' => ['sms_channel' => true, 'email_channel' => true, 'custom_messages' => true, 'custom_links' => true],
    ]);
    $user->subscriptions()->update(['tier_id' => $tier->id, 'cards_total' => 100, 'cards_used' => 0]);

    $event = Event::factory()->create(['user_id' => $user->id, 'public_slug' => 'test-wedding-slug-123']);
    $guest = Guest::factory()->create(['event_id' => $event->id, 'token' => 'guest-secret-token-456']);

    $jobService = app(ShortLinkService::class);
    $smsGateway = app(SmsGateway::class);
    $templateService = app(MessageTemplateService::class);
    $quotaService = app(QuotaService::class);

    // 1. link_mode = event_page
    $cEventPage = Campaign::create([
        'event_id' => $event->id,
        'user_id' => $user->id,
        'type' => 'custom',
        'channel' => 'sms',
        'body' => 'Event page details: {link}',
        'link_mode' => 'event_page',
        'status' => 'sending',
    ]);
    $d1 = Delivery::create(['campaign_id' => $cEventPage->id, 'guest_id' => $guest->id, 'channel' => 'sms', 'status' => 'queued', 'quota_charged' => 1]);
    (new SendDeliveryJob($d1->id))->handle($smsGateway, $jobService, $templateService, $quotaService);

    $s1 = ShortLink::where('campaign_id', $cEventPage->id)->first();
    expect($s1)->not->toBeNull()
        ->and($s1->target_url)->toContain('test-wedding-slug-123');

    // 2. link_mode = custom_url
    $cCustomUrl = Campaign::create([
        'event_id' => $event->id,
        'user_id' => $user->id,
        'type' => 'custom',
        'channel' => 'sms',
        'body' => 'External gallery: {link}',
        'link_mode' => 'custom_url',
        'custom_url' => 'https://sheger-photos.com/album',
        'status' => 'sending',
    ]);
    $d2 = Delivery::create(['campaign_id' => $cCustomUrl->id, 'guest_id' => $guest->id, 'channel' => 'sms', 'status' => 'queued', 'quota_charged' => 1]);
    (new SendDeliveryJob($d2->id))->handle($smsGateway, $jobService, $templateService, $quotaService);

    $s2 = ShortLink::where('campaign_id', $cCustomUrl->id)->first();
    expect($s2)->not->toBeNull()
        ->and($s2->target_url)->toBe('https://sheger-photos.com/album');
});

test('campaign history: index page displays campaigns with audience, link clicks, and delivery counts', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);

    $campaign = Campaign::create([
        'event_id' => $event->id,
        'user_id' => $user->id,
        'type' => 'custom',
        'channel' => 'sms',
        'subject' => 'Important Venue Update',
        'body' => 'Please note venue has moved.',
        'link_mode' => 'none',
        'audience' => ['type' => 'rsvp_yes'],
        'audience_count' => 15,
        'status' => 'sent',
        'estimated_cards' => 15,
    ]);

    // Create short link with clicks
    ShortLink::create([
        'campaign_id' => $campaign->id,
        'code' => 'camp1234',
        'target_url' => 'https://sheger.com/update',
        'clicks' => 42,
    ]);

    $response = $this->actingAs($user)->get(route('events.campaigns.index', $event->id));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Campaigns/Index')
        ->has('campaigns.data', 1, fn ($c) => $c
            ->where('id', $campaign->id)
            ->where('type', 'custom')
            ->where('link_clicks', 42)
            ->etc()
        )
    );
});
