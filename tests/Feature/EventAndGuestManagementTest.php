<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\EventMember;
use App\Models\Guest;
use App\Models\Subscription;
use App\Models\Tier;
use App\Models\User;
use App\Services\EthiopianCalendar;
use Database\Seeders\CategorySeeder;
use Database\Seeders\TemplateSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TierSeeder::class);
    $this->seed(CategorySeeder::class);
    $this->seed(TemplateSeeder::class);
});

test('event creation wizard renders with categories and tier entitlements', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('events.create'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Events/Create')
        ->has('categories')
        ->where('canCreate', true)
        ->where('maxEvents', 1)
        ->where('activeEventsCount', 0)
        ->where('canQrCheckin', false) // Free tier
    );
});

test('user can create event with details and settings', function () {
    $user = User::factory()->create();
    $category = Category::where('slug', 'wedding')->first();

    $response = $this->actingAs($user)->post(route('events.store'), [
        'category_id' => $category->id,
        'title' => 'Ephrem & Bethlehem Wedding',
        'host_names' => 'Ephrem & Bethlehem',
        'starts_at' => '2027-10-24T16:00',
        'timezone' => 'Africa/Addis_Ababa',
        'venue_name' => 'Skylight Hotel',
        'venue_address' => 'Airport Road, Addis Ababa',
        'map_url' => 'https://maps.google.com/?q=Skylight+Hotel',
        'is_invite_only' => true,
        'rsvp_enabled' => true,
        'plus_ones_allowed' => true,
    ]);

    $event = Event::where('title', 'Ephrem & Bethlehem Wedding')->first();
    expect($event)->not->toBeNull()
        ->and($event->user_id)->toBe($user->id)
        ->and($event->status)->toBe('draft')
        ->and($event->rsvp_enabled)->toBeTrue()
        ->and($event->plus_ones_allowed)->toBeTrue();

    $response->assertRedirect(route('events.design', ['event' => $event->id]));
});

test('tier max_events limit prevents creating excess events', function () {
    $user = User::factory()->create(); // Free tier has max_events = 1
    $category = Category::first();

    // Create 1st event (allowed)
    Event::factory()->create([
        'user_id' => $user->id,
        'status' => 'draft',
    ]);

    // Attempt 2nd event (should be blocked)
    $response = $this->actingAs($user)->post(route('events.store'), [
        'category_id' => $category->id,
        'title' => 'Second Event Attempt',
        'starts_at' => '2027-11-01T14:00',
        'timezone' => 'Africa/Addis_Ababa',
    ]);

    $response->assertSessionHasErrors('title');
    expect(Event::where('user_id', $user->id)->count())->toBe(1);
});

test('funeral category defaults RSVP and plus-ones to false', function () {
    $user = User::factory()->create();
    $funeralCategory = Category::where('slug', 'funeral')->first();

    $response = $this->actingAs($user)->post(route('events.store'), [
        'category_id' => $funeralCategory->id,
        'title' => 'የአቶ ከበደ ለማ የመጨረሻ የስንብት ዝግጅት',
        'starts_at' => '2027-05-15T10:00',
        'timezone' => 'Africa/Addis_Ababa',
    ]);

    $event = Event::where('title', 'የአቶ ከበደ ለማ የመጨረሻ የስንብት ዝግጅት')->first();
    expect($event)->not->toBeNull()
        ->and($event->rsvp_enabled)->toBeFalse()
        ->and($event->plus_ones_allowed)->toBeFalse()
        ->and($event->is_invite_only)->toBeFalse();
});

test('ethiopian calendar conversion converts dates accurately', function () {
    // 2024-09-11 is Meskerem 1, 2017 in Ethiopian Calendar
    $eth = EthiopianCalendar::fromGregorian('2024-09-11 12:00:00');
    expect($eth)->not->toBeNull()
        ->and($eth['year'])->toBe(2017)
        ->and($eth['month'])->toBe(1)
        ->and($eth['day'])->toBe(1)
        ->and($eth['formatted_am'])->toBe('መስከረም 1, 2017');
});

test('event duplication clones event without copying guests', function () {
    $user = User::factory()->create();
    $goldTier = Tier::where('slug', 'gold')->first();
    Subscription::create([
        'user_id' => $user->id,
        'tier_id' => $goldTier->id,
        'cards_total' => $goldTier->card_quota,
        'status' => 'active',
        'starts_at' => now(),
    ]);

    $original = Event::factory()->create([
        'user_id' => $user->id,
        'title' => 'Original Gala',
    ]);

    Guest::factory()->count(5)->create(['event_id' => $original->id]);

    $response = $this->actingAs($user)->post(route('events.duplicate', ['event' => $original->id]));

    $response->assertRedirect(route('dashboard'));

    $cloned = Event::where('title', 'Copy of Original Gala')->first();
    expect($cloned)->not->toBeNull()
        ->and($cloned->status)->toBe('draft')
        ->and($cloned->guests()->count())->toBe(0);
});

test('user can delete their event and cascades', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);
    Guest::factory()->count(3)->create(['event_id' => $event->id]);

    $response = $this->actingAs($user)->delete(route('events.destroy', ['event' => $event->id]));

    $response->assertRedirect(route('dashboard'));
    expect(Event::find($event->id))->toBeNull()
        ->and(Guest::where('event_id', $event->id)->count())->toBe(0);
});

test('adding manual guest normalizes ethiopian phone and generates 32 char token', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson(route('events.guests.store', ['event' => $event->id]), [
        'name' => 'አበበ ደምሴ',
        'phone' => '0911223344',
        'email' => 'abebe@example.com',
        'preferred_channel' => 'sms',
        'max_party_size' => 2,
    ]);

    $response->assertOk();
    $guest = Guest::where('event_id', $event->id)->first();
    expect($guest)->not->toBeNull()
        ->and($guest->name)->toBe('አበበ ደምሴ')
        ->and($guest->phone)->toBe('+251911223344')
        ->and(strlen($guest->token))->toBe(32)
        ->and($guest->max_party_size)->toBe(2);
});

test('adding guest with invalid phone is rejected', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson(route('events.guests.store', ['event' => $event->id]), [
        'name' => 'Sara Bekele',
        'phone' => '0123456789', // Invalid Ethiopian prefix
        'preferred_channel' => 'sms',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['phone']);
});

test('duplicate guest phone or email in same event is rejected', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);

    Guest::create([
        'event_id' => $event->id,
        'name' => 'Chaltu Kebede',
        'phone' => '+251911000000',
        'preferred_channel' => 'sms',
    ]);

    $response = $this->actingAs($user)->postJson(route('events.guests.store', ['event' => $event->id]), [
        'name' => 'Chaltu Again',
        'phone' => '0911000000', // Matches existing
        'preferred_channel' => 'sms',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('bulk paste imports guests, skips duplicates, and handles blank lines and Amharic names', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);

    $bulkText = <<<TXT
Almaz Bekele, 0911223344, almaz@example.com, 2

ጫልቱ ከበደ, 0712345678, 1
Dr. Dawit, dawit@gmail.com, 3
Almaz Bekele, 0911223344, 1
TXT;

    $response = $this->actingAs($user)->postJson(route('events.guests.bulk-paste', ['event' => $event->id]), [
        'text' => $bulkText,
        'on_duplicate' => 'skip',
    ]);

    $response->assertOk();
    $json = $response->json();
    expect($json['added'])->toBe(3)
        ->and($json['skipped'])->toBe(1);

    expect(Guest::where('event_id', $event->id)->count())->toBe(3);
    expect(Guest::where('name', 'ጫልቱ ከበደ')->first()?->phone)->toBe('+251712345678');
});

test('csv_import feature blocks free tier user and allows paid tier user', function () {
    $freeUser = User::factory()->create(); // Free tier has csv_import = false
    $eventFree = Event::factory()->create(['user_id' => $freeUser->id]);

    $csvContent = "Name,Phone,Email,Party\nAlmaz,0911223344,almaz@test.com,2\n";
    $file = UploadedFile::fake()->createWithContent('guests.csv', $csvContent);

    // Free user blocked
    $responseFree = $this->actingAs($freeUser)->postJson(route('events.guests.import', ['event' => $eventFree->id]), [
        'file' => $file,
        'on_duplicate' => 'skip',
    ]);
    $responseFree->assertStatus(403);

    // Paid user (Bronze tier has csv_import = true)
    $bronzeTier = Tier::where('slug', 'bronze')->first();
    $paidUser = User::factory()->create();
    Subscription::create([
        'user_id' => $paidUser->id,
        'tier_id' => $bronzeTier->id,
        'cards_total' => $bronzeTier->card_quota,
        'status' => 'active',
        'starts_at' => now(),
    ]);

    $eventPaid = Event::factory()->create(['user_id' => $paidUser->id]);
    $filePaid = UploadedFile::fake()->createWithContent('guests.csv', "\xEF\xBB\xBFName,Phone,Email,Party\nBereket,0922334455,bereket@test.com,2\n");

    $responsePaid = $this->actingAs($paidUser)->postJson(route('events.guests.import', ['event' => $eventPaid->id]), [
        'file' => $filePaid,
        'on_duplicate' => 'skip',
    ]);

    $responsePaid->assertOk();
    expect(Guest::where('event_id', $eventPaid->id)->where('name', 'Bereket')->exists())->toBeTrue();
});

test('max_guests_per_event tier limit prevents exceeding guest quota', function () {
    $user = User::factory()->create(); // Free tier has max_guests_per_event = 50
    $event = Event::factory()->create(['user_id' => $user->id]);

    // Create 50 guests
    for ($i = 1; $i <= 50; $i++) {
        Guest::create([
            'event_id' => $event->id,
            'name' => "Guest {$i}",
            'email' => "guest{$i}@example.com",
            'preferred_channel' => 'email',
        ]);
    }

    expect($event->guests()->count())->toBe(50);

    // Attempting 51st guest should fail
    $response = $this->actingAs($user)->postJson(route('events.guests.store', ['event' => $event->id]), [
        'name' => 'Guest 51',
        'email' => 'guest51@example.com',
        'preferred_channel' => 'email',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('guest list export returns UTF-8 CSV with BOM for Amharic support', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create([
        'user_id' => $user->id,
        'title' => 'Ethiopian Wedding',
    ]);

    Guest::create([
        'event_id' => $event->id,
        'name' => 'አበበ በቀለ',
        'phone' => '+251911223344',
        'email' => 'abebe@example.com',
        'preferred_channel' => 'sms',
        'rsvp_status' => 'yes',
    ]);

    $response = $this->actingAs($user)->get(route('events.guests.export', ['event' => $event->id]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();
    // Starts with UTF-8 BOM
    expect(str_starts_with($content, "\xEF\xBB\xBF"))->toBeTrue();
    expect(str_contains($content, 'አበበ በቀለ'))->toBeTrue();
    expect(str_contains($content, '+251911223344'))->toBeTrue();
});

test('co_hosts feature flag and authorization policy enforcement', function () {
    $freeOwner = User::factory()->create(); // Free tier has co_hosts = false
    $freeEvent = Event::factory()->create(['user_id' => $freeOwner->id]);
    $collaborator = User::factory()->create();

    // Free tier cannot invite co-hosts
    $freeInviteResponse = $this->actingAs($freeOwner)->postJson(route('events.members.store', ['event' => $freeEvent->id]), [
        'email' => $collaborator->email,
        'role' => 'editor',
    ]);
    $freeInviteResponse->assertStatus(403);

    // Paid tier can invite co-hosts
    $silverTier = Tier::where('slug', 'silver')->first();
    $paidOwner = User::factory()->create();
    Subscription::create([
        'user_id' => $paidOwner->id,
        'tier_id' => $silverTier->id,
        'cards_total' => $silverTier->card_quota,
        'status' => 'active',
        'starts_at' => now(),
    ]);

    $paidEvent = Event::factory()->create(['user_id' => $paidOwner->id]);

    $inviteResponse = $this->actingAs($paidOwner)->postJson(route('events.members.store', ['event' => $paidEvent->id]), [
        'email' => $collaborator->email,
        'role' => 'editor',
    ]);
    $inviteResponse->assertOk();
    expect(EventMember::where('event_id', $paidEvent->id)->where('user_id', $collaborator->id)->exists())->toBeTrue();

    // Collaborator with role 'editor' can manage guests
    $addGuestResponse = $this->actingAs($collaborator)->postJson(route('events.guests.store', ['event' => $paidEvent->id]), [
        'name' => 'Co-host Added Guest',
        'phone' => '0911554433',
        'preferred_channel' => 'sms',
    ]);
    $addGuestResponse->assertOk();

    // Random stranger is blocked from guest list
    $stranger = User::factory()->create();
    $strangerResponse = $this->actingAs($stranger)->get(route('events.guests.index', ['event' => $paidEvent->id]));
    $strangerResponse->assertForbidden();
});
