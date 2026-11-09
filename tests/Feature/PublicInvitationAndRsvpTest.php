<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use App\Notifications\GuestRsvpNotification;
use App\Services\QrService;
use Database\Seeders\CategorySeeder;
use Database\Seeders\TemplateSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TierSeeder::class);
    $this->seed(CategorySeeder::class);
    $this->seed(TemplateSeeder::class);
});

test('invalid token returns 404', function () {
    $response = $this->get('/i/non-existent-token-123456789012');

    $response->assertNotFound();
});

test('public invitation page renders without login and records first viewed_at', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create([
        'user_id' => $user->id,
        'title' => 'Kidus & Eden Wedding',
        'is_invite_only' => true,
        'rsvp_enabled' => true,
    ]);

    $guest = Guest::create([
        'event_id' => $event->id,
        'name' => 'Sara Tesfaye',
        'phone' => '+251911223344',
        'token' => 'test-token-32-chars-long-abc-123',
        'preferred_channel' => 'sms',
        'max_party_size' => 2,
    ]);

    expect($guest->viewed_at)->toBeNull();

    $response = $this->get('/i/' . $guest->token);

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Public/Invitation')
        ->where('event.id', $event->id)
        ->where('event.title', 'Kidus & Eden Wedding')
        ->where('guest.name', 'Sara Tesfaye')
        ->where('guest.token', $guest->token)
        ->has('placeholderData')
        ->has('qrSvg')
        ->has('ethiopianDate')
    );

    $guest->refresh();
    expect($guest->viewed_at)->not->toBeNull();
});

test('submitting RSVP updates guest and notifies the event host', function () {
    Notification::fake();

    $host = User::factory()->create();
    $event = Event::factory()->create([
        'user_id' => $host->id,
        'title' => 'Gala Dinner',
        'rsvp_enabled' => true,
        'starts_at' => now()->addDays(5),
    ]);

    $guest = Guest::create([
        'event_id' => $event->id,
        'name' => 'Dawit Alemu',
        'token' => 'guest-token-12345678901234567890',
        'max_party_size' => 3,
        'preferred_channel' => 'sms',
    ]);

    $response = $this->postJson(route('public.invitation.rsvp', ['token' => $guest->token]), [
        'rsvp_status' => 'yes',
        'rsvp_party_size' => 2,
        'rsvp_note' => 'Congratulations! Looking forward to it.',
    ]);

    $response->assertOk();
    $guest->refresh();
    expect($guest->rsvp_status)->toBe('yes')
        ->and($guest->rsvp_party_size)->toBe(2)
        ->and($guest->rsvp_note)->toBe('Congratulations! Looking forward to it.');

    Notification::assertSentTo($host, GuestRsvpNotification::class, function ($notification) use ($guest) {
        return $notification->guest->id === $guest->id;
    });
});

test('guest can update their RSVP answer before event starts', function () {
    $event = Event::factory()->create([
        'rsvp_enabled' => true,
        'starts_at' => now()->addDays(3),
    ]);

    $guest = Guest::create([
        'event_id' => $event->id,
        'name' => 'Helen Getachew',
        'token' => 'helen-token-12345678901234567890',
        'rsvp_status' => 'yes',
        'rsvp_party_size' => 2,
        'preferred_channel' => 'sms',
    ]);

    // Change answer to 'no'
    $response = $this->postJson(route('public.invitation.rsvp', ['token' => $guest->token]), [
        'rsvp_status' => 'no',
        'rsvp_note' => 'Apologies, unforeseen travel.',
    ]);

    $response->assertOk();
    $guest->refresh();
    expect($guest->rsvp_status)->toBe('no')
        ->and($guest->rsvp_party_size)->toBe(0);
});

test('RSVP is rejected if event has already started', function () {
    $event = Event::factory()->create([
        'rsvp_enabled' => true,
        'starts_at' => now()->subDay(), // in the past
    ]);

    $guest = Guest::create([
        'event_id' => $event->id,
        'name' => 'Late Guest',
        'token' => 'late-guest-token-12345678901234',
        'preferred_channel' => 'sms',
    ]);

    $response = $this->postJson(route('public.invitation.rsvp', ['token' => $guest->token]), [
        'rsvp_status' => 'yes',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['rsvp_status']);
});

test('qr code is generated and signed with HMAC when event is invite-only', function () {
    $event = Event::factory()->create([
        'is_invite_only' => true,
    ]);

    $guest = Guest::create([
        'event_id' => $event->id,
        'name' => 'VIP Guest',
        'token' => 'vip-guest-token-1234567890123456',
        'preferred_channel' => 'sms',
    ]);

    $response = $this->get('/i/' . $guest->token);
    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->has('qrSvg')
        ->where('event.is_invite_only', true)
    );

    // Verify HMAC QrService
    $payload = QrService::generatePayload($event->id, $guest->id, 'testnonce123');
    $verified = QrService::verifyPayload($payload);
    expect($verified['valid'])->toBeTrue()
        ->and($verified['event_id'])->toBe($event->id)
        ->and($verified['guest_id'])->toBe($guest->id);
});

test('qr code is omitted when event is not invite-only', function () {
    $event = Event::factory()->create([
        'is_invite_only' => false,
    ]);

    $guest = Guest::create([
        'event_id' => $event->id,
        'name' => 'Open Guest',
        'token' => 'open-guest-token-12345678901234',
        'preferred_channel' => 'sms',
    ]);

    $response = $this->get('/i/' . $guest->token);
    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->where('qrSvg', null)
        ->where('event.is_invite_only', false)
    );
});

test('opt-out endpoint marks guest as opted out', function () {
    $guest = Guest::create([
        'event_id' => Event::factory()->create()->id,
        'name' => 'Opt Out Guest',
        'token' => 'optout-guest-token-123456789012',
        'opted_out' => false,
        'preferred_channel' => 'sms',
    ]);

    $response = $this->postJson(route('public.invitation.opt-out', ['token' => $guest->token]));

    $response->assertOk();
    $guest->refresh();
    expect($guest->opted_out)->toBeTrue();
});

test('calendar .ics endpoint streams valid iCalendar file', function () {
    $event = Event::factory()->create(['title' => 'Grand Anniversary Celebration']);
    $guest = Guest::create([
        'event_id' => $event->id,
        'name' => 'Invited Guest',
        'token' => 'calendar-guest-token-1234567890',
        'preferred_channel' => 'sms',
    ]);

    $response = $this->get(route('public.invitation.ics', ['token' => $guest->token]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
    $content = $response->getContent();
    expect(str_contains($content, 'BEGIN:VCALENDAR'))->toBeTrue()
        ->and(str_contains($content, 'Grand Anniversary Celebration'))->toBeTrue();
});

test('public event info page displays event details without personal guest data', function () {
    $event = Event::factory()->create([
        'title' => 'Public Tech Summit',
        'public_slug' => 'tech-summit-2027',
        'venue_name' => 'Millennium Hall',
    ]);

    $response = $this->get(route('public.event.info', ['slug' => 'tech-summit-2027']));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Public/EventInfo')
        ->where('event.title', 'Public Tech Summit')
        ->where('event.venue_name', 'Millennium Hall')
        ->missing('guest')
    );
});

test('og-image endpoint generates valid PNG image', function () {
    $event = Event::factory()->create(['title' => 'Luxury Ethiopian Gala']);
    $guest = Guest::create([
        'event_id' => $event->id,
        'name' => 'Tigist Assefa',
        'token' => 'og-guest-token-123456789012345',
        'preferred_channel' => 'sms',
    ]);

    $response = $this->get(route('public.invitation.og-image', ['token' => $guest->token]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/png');
    expect(strlen($response->getContent()))->toBeGreaterThan(100);
});
