<?php

use App\Models\Category;
use App\Models\Delivery;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Subscription;
use App\Models\Tier;
use App\Models\User;
use App\Services\AudienceService;
use Carbon\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(\Database\Seeders\TierSeeder::class);

    $this->freeTier = Tier::where('slug', 'free')->first();
    $this->bronzeTier = Tier::where('slug', 'bronze')->first();

    $this->weddingCategory = Category::firstOrCreate(
        ['slug' => 'wedding'],
        ['name_en' => 'Wedding', 'name_am' => 'ሰርግ', 'sort' => 1]
    );

    $this->funeralCategory = Category::firstOrCreate(
        ['slug' => 'funeral'],
        ['name_en' => 'Funeral', 'name_am' => 'ለቅሶ', 'sort' => 10]
    );
});

function createThankYouHost(Tier $tier, Category $category, array $eventAttributes = []): array
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

    $event = Event::create(array_merge([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'title' => 'Celebration of Life',
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addHours(4),
        'timezone' => 'Africa/Addis_Ababa',
        'venue_name' => 'Skylight Hotel',
        'is_invite_only' => true,
        'rsvp_enabled' => true,
        'status' => 'published',
        'thankyou_design_json' => [
            'version' => 1,
            'width' => 1080,
            'height' => 1528,
            'background' => ['type' => 'color', 'value' => '#f8fafc'],
            'layers' => [
                [
                    'id' => '1',
                    'type' => 'text',
                    'text' => 'Thank You {{guest_name}}!',
                    'x' => 100,
                    'y' => 200,
                    'width' => 400,
                    'height' => 80,
                    'fontSize' => 36,
                    'fontFamily' => 'Inter',
                    'fill' => '#0f172a',
                ],
            ],
        ],
    ], $eventAttributes));

    return [$user, $event];
}

test('audience resolution: invite-only with QR check-in defaults to guests who checked in', function () {
    [$user, $event] = createThankYouHost($this->bronzeTier, $this->weddingCategory, [
        'is_invite_only' => true,
        'rsvp_enabled' => true,
    ]);

    // Guest 1: checked in
    $g1 = Guest::create([
        'event_id' => $event->id,
        'name' => 'Guest Checked In 1',
        'phone' => '+251911111111',
        'token' => Str::random(32),
        'checked_in_at' => now()->subHours(2),
        'checked_in_by' => 'Door 1',
    ]);

    // Guest 2: checked in
    $g2 = Guest::create([
        'event_id' => $event->id,
        'name' => 'Guest Checked In 2',
        'phone' => '+251922222222',
        'token' => Str::random(32),
        'checked_in_at' => now()->subHour(),
        'checked_in_by' => 'Door 2',
    ]);

    // Guest 3: not checked in
    $g3 = Guest::create([
        'event_id' => $event->id,
        'name' => 'Guest Not Checked In',
        'phone' => '+251933333333',
        'token' => Str::random(32),
        'checked_in_at' => null,
    ]);

    // Guest 4: checked in but opted out
    $g4 = Guest::create([
        'event_id' => $event->id,
        'name' => 'Guest Opted Out',
        'phone' => '+251944444444',
        'token' => Str::random(32),
        'checked_in_at' => now()->subHours(3),
        'opted_out' => true,
    ]);

    $audienceService = app(AudienceService::class);
    expect($audienceService->defaultThankYouAudience($event))->toBe('checked_in');

    $resolved = $audienceService->resolve($event, 'checked_in', [], 'sms');
    $eligibleIds = $resolved['eligible_guests']->pluck('id')->all();

    expect($eligibleIds)->toContain($g1->id);
    expect($eligibleIds)->toContain($g2->id);
    expect($eligibleIds)->not->toContain($g3->id);
    expect($eligibleIds)->not->toContain($g4->id);
    expect($resolved['stats']['eligible_recipients'])->toBe(2);
});

test('audience resolution: open event with RSVP defaults to guests who RSVPd yes', function () {
    [$user, $event] = createThankYouHost($this->bronzeTier, $this->weddingCategory, [
        'is_invite_only' => false,
        'rsvp_enabled' => true,
    ]);

    // Guest 1: RSVP Yes
    $g1 = Guest::create([
        'event_id' => $event->id,
        'name' => 'Guest RSVP Yes',
        'phone' => '+251911111111',
        'token' => Str::random(32),
        'rsvp_status' => 'yes',
    ]);

    // Guest 2: RSVP No
    $g2 = Guest::create([
        'event_id' => $event->id,
        'name' => 'Guest RSVP No',
        'phone' => '+251922222222',
        'token' => Str::random(32),
        'rsvp_status' => 'no',
    ]);

    // Guest 3: RSVP Pending
    $g3 = Guest::create([
        'event_id' => $event->id,
        'name' => 'Guest RSVP Pending',
        'phone' => '+251933333333',
        'token' => Str::random(32),
        'rsvp_status' => 'pending',
    ]);

    // Guest 4: RSVP Yes but opted out
    $g4 = Guest::create([
        'event_id' => $event->id,
        'name' => 'Guest RSVP Yes Opted Out',
        'phone' => '+251944444444',
        'token' => Str::random(32),
        'rsvp_status' => 'yes',
        'opted_out' => true,
    ]);

    $audienceService = app(AudienceService::class);
    expect($audienceService->defaultThankYouAudience($event))->toBe('rsvp_yes');

    $resolved = $audienceService->resolve($event, 'rsvp_yes', [], 'sms');
    $eligibleIds = $resolved['eligible_guests']->pluck('id')->all();

    expect($eligibleIds)->toBe([$g1->id]);
    expect($resolved['stats']['eligible_recipients'])->toBe(1);
});

test('audience resolution: event without RSVP and without QR check-in defaults to all', function () {
    [$user, $event] = createThankYouHost($this->bronzeTier, $this->weddingCategory, [
        'is_invite_only' => false,
        'rsvp_enabled' => false,
    ]);

    $g1 = Guest::create([
        'event_id' => $event->id,
        'name' => 'Guest 1',
        'phone' => '+251911111111',
        'token' => Str::random(32),
    ]);

    $g2 = Guest::create([
        'event_id' => $event->id,
        'name' => 'Guest 2',
        'phone' => '+251922222222',
        'token' => Str::random(32),
    ]);

    $g3 = Guest::create([
        'event_id' => $event->id,
        'name' => 'Guest 3 Opted Out',
        'phone' => '+251933333333',
        'token' => Str::random(32),
        'opted_out' => true,
    ]);

    $audienceService = app(AudienceService::class);
    expect($audienceService->defaultThankYouAudience($event))->toBe('all');

    $resolved = $audienceService->resolve($event, 'all', [], 'sms');
    $eligibleIds = $resolved['eligible_guests']->pluck('id')->all();

    expect($eligibleIds)->toEqualCanonicalizing([$g1->id, $g2->id]);
});

test('feature gating: free tier user without thank_you feature cannot create thank-you campaign', function () {
    $user = User::factory()->create();
    $event = Event::create([
        'user_id' => $user->id,
        'category_id' => $this->weddingCategory->id,
        'title' => 'Free Event',
        'starts_at' => now()->addDays(2),
        'status' => 'published',
    ]);

    Guest::create([
        'event_id' => $event->id,
        'name' => 'Abebe',
        'phone' => '+251911223344',
        'token' => Str::random(32),
    ]);

    // Attempting to visit thank-you page redirects to plan
    $this->actingAs($user)->get(route('events.thank-you', ['event' => $event->id]))
        ->assertRedirect(route('plan.index'));

    // Attempting to submit thank-you campaign fails with validation error
    $response = $this->actingAs($user)->post(route('events.campaigns.store', ['event' => $event->id]), [
        'type' => 'thank_you',
        'channel' => 'sms',
        'body' => 'Thank you {guest_name}!',
    ]);

    $response->assertSessionHasErrors('thank_you');
});

test('quota behavior: tier with thank_you_consumes_quota = false costs 0 cards', function () {
    [$user, $event] = createThankYouHost($this->bronzeTier, $this->weddingCategory);
    // Bronze tier has thank_you_consumes_quota = false, card_quota = 500

    $guests = [];
    for ($i = 1; $i <= 5; $i++) {
        $guests[] = Guest::create([
            'event_id' => $event->id,
            'name' => "Guest {$i}",
            'phone' => "+25191100000{$i}",
            'token' => Str::random(32),
            'checked_in_at' => now()->subHour(),
        ]);
    }

    $initialAvailable = app(\App\Services\QuotaService::class)->available($user);

    $response = $this->actingAs($user)->post(route('events.campaigns.store', ['event' => $event->id]), [
        'type' => 'thank_you',
        'channel' => 'sms',
        'audience_type' => 'checked_in',
        'body' => 'Thank you {guest_name} for joining us at {event_title}! {link}',
        'thankyou_note' => 'We enjoyed having you celebrating with us.',
        'thankyou_photo_url' => 'https://photos.example.com/album123',
    ]);

    $response->assertRedirect(route('events.deliveries', ['event' => $event->id]));

    // Campaign was created with estimated_cards = 0
    $campaign = $event->campaigns()->latest()->first();
    expect($campaign)->not->toBeNull();
    expect($campaign->type)->toBe('thank_you');
    expect($campaign->estimated_cards)->toBe(0);

    // Deliveries were created with quota_charged = 0
    $deliveries = Delivery::where('campaign_id', $campaign->id)->get();
    expect($deliveries->count())->toBe(5);
    foreach ($deliveries as $del) {
        expect($del->quota_charged)->toBe(0);
    }

    // Available quota must remain completely unchanged!
    $afterAvailable = app(\App\Services\QuotaService::class)->available($user);
    expect($afterAvailable)->toBe($initialAvailable);

    // Event note and photo link saved
    $event->refresh();
    expect($event->thankyou_note)->toBe('We enjoyed having you celebrating with us.');
    expect($event->thankyou_photo_url)->toBe('https://photos.example.com/album123');
});

test('quota behavior: tier with thank_you_consumes_quota = true consumes card quota', function () {
    // Create a special tier that consumes quota for thank_you cards
    $meteredTier = Tier::create([
        'slug' => 'metered_tier',
        'name_en' => 'Metered Tier',
        'name_am' => 'የሚቆጠር',
        'price_amount' => 100,
        'currency' => 'ETB',
        'card_quota' => 50,
        'max_events' => 5,
        'max_guests_per_event' => 100,
        'sms_weight' => 2,
        'email_weight' => 1,
        'thank_you_consumes_quota' => true,
        'sort' => 10,
        'features' => [
            'sms_channel' => true,
            'email_channel' => true,
            'thank_you' => true,
        ],
        'is_active' => true,
    ]);

    [$user, $event] = createThankYouHost($meteredTier, $this->weddingCategory);

    for ($i = 1; $i <= 3; $i++) {
        Guest::create([
            'event_id' => $event->id,
            'name' => "Guest {$i}",
            'phone' => "+25191100000{$i}",
            'token' => Str::random(32),
            'checked_in_at' => now()->subHour(),
        ]);
    }

    $initialAvailable = app(\App\Services\QuotaService::class)->available($user);

    // 3 guests * sms_weight (2) = 6 cards needed
    $response = $this->actingAs($user)->post(route('events.campaigns.store', ['event' => $event->id]), [
        'type' => 'thank_you',
        'channel' => 'sms',
        'audience_type' => 'checked_in',
        'body' => 'Thank you {guest_name}! {link}',
    ]);
    $response->assertRedirect();

    $afterAvailable = app(\App\Services\QuotaService::class)->available($user);
    expect($afterAvailable)->toBe($initialAvailable - 6);
});

test('scheduling: calculates dispatch time relative to event end time', function () {
    [$user, $event] = createThankYouHost($this->bronzeTier, $this->weddingCategory, [
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(3)->addHours(5),
    ]);

    Guest::create([
        'event_id' => $event->id,
        'name' => 'Abebe',
        'phone' => '+251911223344',
        'token' => Str::random(32),
        'checked_in_at' => now(),
    ]);

    // Schedule 12 hours after ends_at
    $response = $this->actingAs($user)->post(route('events.campaigns.store', ['event' => $event->id]), [
        'type' => 'thank_you',
        'channel' => 'sms',
        'audience_type' => 'all',
        'body' => 'Thank you {guest_name}! {link}',
        'schedule_hours_after_end' => 12,
    ]);
    $response->assertRedirect(route('events.campaigns.index', ['event' => $event->id]));

    $campaign = $event->campaigns()->latest()->first();
    expect($campaign->status)->toBe('scheduled');

    $expectedTime = $event->ends_at->copy()->addHours(12);
    expect($campaign->scheduled_at->format('Y-m-d H:i'))->toBe($expectedTime->format('Y-m-d H:i'));
});

test('public thank-you page GET /t/{token} renders card, note, and photo link', function () {
    [$user, $event] = createThankYouHost($this->bronzeTier, $this->weddingCategory, [
        'thankyou_note' => 'Thank you for your generous gift and presence!',
        'thankyou_photo_url' => 'https://photos.example.com/gallery',
    ]);

    $guest = Guest::create([
        'event_id' => $event->id,
        'name' => 'Almaz Ayana',
        'phone' => '+251911223344',
        'token' => Str::random(32),
    ]);

    $response = $this->get(route('public.thank-you.show', ['token' => $guest->token]));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/ThankYou')
            ->where('guest.name', 'Almaz Ayana')
            ->where('event.thankyou_note', 'Thank you for your generous gift and presence!')
            ->where('event.thankyou_photo_url', 'https://photos.example.com/gallery')
            ->has('design')
        );
});

test('public thank-you page security: 404 for unknown token and blocked for suspended events', function () {
    [$user, $event] = createThankYouHost($this->bronzeTier, $this->weddingCategory);

    // Unknown token returns 404
    $this->get('/t/non-existent-token-12345678901234567890')
        ->assertNotFound();

    $guest = Guest::create([
        'event_id' => $event->id,
        'name' => 'Abebe',
        'token' => Str::random(32),
    ]);

    // Active event returns 200
    $this->get(route('public.thank-you.show', ['token' => $guest->token]))
        ->assertOk();

    // Suspended event returns 404
    $event->update(['status' => 'suspended']);
    $this->get(route('public.thank-you.show', ['token' => $guest->token]))
        ->assertNotFound();
});

test('safeguards: funeral category provides respectful default message', function () {
    [$user, $event] = createThankYouHost($this->bronzeTier, $this->funeralCategory);

    $response = $this->actingAs($user)->get(route('events.thank-you', ['event' => $event->id]));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Events/ThankYou')
            ->where('defaults.message_en', fn ($val) => str_contains($val, 'comfort to our family'))
            ->where('defaults.message_am', fn ($val) => str_contains($val, 'ላሳዩን አጋርነት ከልብ እናመሰግናለን'))
        );
});

test('scheduling: artisan campaigns:dispatch-scheduled dispatches due thank-you campaigns', function () {
    [$user, $event] = createThankYouHost($this->bronzeTier, $this->weddingCategory);

    $guest = Guest::create([
        'event_id' => $event->id,
        'name' => 'Sara',
        'phone' => '+251911999999',
        'token' => Str::random(32),
    ]);

    // Create a scheduled thank-you campaign set in the past
    $campaign = \App\Models\Campaign::create([
        'event_id' => $event->id,
        'user_id' => $user->id,
        'type' => 'thank_you',
        'channel' => 'sms',
        'subject' => 'Thank you',
        'body' => 'Thank you {guest_name}! {link}',
        'link_mode' => 'thank_you',
        'audience' => ['type' => 'all', 'guest_ids' => []],
        'audience_count' => 1,
        'scheduled_at' => now()->subMinutes(5),
        'status' => 'scheduled',
        'estimated_cards' => 0,
    ]);

    $this->artisan('campaigns:dispatch-scheduled')
        ->assertSuccessful();

    $campaign->refresh();
    expect($campaign->status)->toBeIn(['sending', 'sent']);

    $delivery = Delivery::where('campaign_id', $campaign->id)->first();
    expect($delivery)->not->toBeNull();
    expect($delivery->quota_charged)->toBe(0);
});

test('campaign history: thank-you campaign shows in deliveries and supports resend', function () {
    [$user, $event] = createThankYouHost($this->bronzeTier, $this->weddingCategory);

    $guest = Guest::create([
        'event_id' => $event->id,
        'name' => 'Robel',
        'phone' => '+251911888888',
        'token' => Str::random(32),
    ]);

    // Send thank-you campaign
    $this->actingAs($user)->post(route('events.campaigns.store', ['event' => $event->id]), [
        'type' => 'thank_you',
        'channel' => 'sms',
        'audience_type' => 'all',
        'body' => 'Thank you {guest_name}! {link}',
    ]);

    $campaign = $event->campaigns()->latest()->first();
    $delivery = Delivery::where('campaign_id', $campaign->id)->first();

    // Verify campaign displays on deliveries log
    $res = $this->actingAs($user)->get(route('events.deliveries', ['event' => $event->id]));
    $res->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Events/Deliveries')
            ->has('deliveries.data', 1)
        );

    // Simulate failure and resend
    $delivery->update(['status' => 'failed', 'error' => 'Simulated gateway error']);

    $resendRes = $this->actingAs($user)->post(route('events.deliveries.resend', [
        'event' => $event->id,
        'delivery' => $delivery->id,
    ]));
    $resendRes->assertRedirect();

    $delivery->refresh();
    expect($delivery->status)->toBeIn(['queued', 'sent']);
});
