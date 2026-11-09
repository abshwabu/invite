<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\EventMember;
use App\Models\Subscription;
use App\Models\Template;
use App\Models\Tier;
use App\Models\User;
use App\Services\DesignValidator;
use Database\Seeders\CategorySeeder;
use Database\Seeders\TemplateSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TierSeeder::class);
    $this->seed(CategorySeeder::class);
    $this->seed(TemplateSeeder::class);
});

test('guest can access editor with blank design or default template', function () {
    $response = $this->get(route('editor.show'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Editor/Show')
        ->where('isGuest', true)
        ->where('target', 'invitation')
        ->where('hasRemoveBranding', false)
        ->has('design')
        ->has('categories')
    );
});

test('guest can access editor for a specific template', function () {
    $template = Template::first();

    $response = $this->get(route('editor.show', ['template' => $template->id]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Editor/Show')
        ->where('template.id', $template->id)
        ->where('isGuest', true)
        ->where('design.version', 1)
        ->where('design.width', 1080)
        ->where('design.height', 1528)
    );
});

test('event owner can access invitation card editor', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create([
        'user_id' => $user->id,
        'title' => 'Sam & Sara Wedding',
    ]);

    $response = $this->actingAs($user)->get(route('events.design', ['event' => $event->id]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Editor/Show')
        ->where('event.id', $event->id)
        ->where('target', 'invitation')
        ->where('isGuest', false)
        ->where('eventData.guest_name', 'Almaz Bekele')
        ->where('eventData.event_title', 'Sam & Sara Wedding')
    );
});

test('event collaborator with editor role can access invitation card editor', function () {
    $owner = User::factory()->create();
    $collaborator = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $owner->id]);

    EventMember::create([
        'event_id' => $event->id,
        'user_id' => $collaborator->id,
        'role' => 'editor',
    ]);

    $response = $this->actingAs($collaborator)->get(route('events.design', ['event' => $event->id]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Editor/Show')
        ->where('event.id', $event->id)
    );
});

test('unauthorized user cannot access another user event editor', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($stranger)->get(route('events.design', ['event' => $event->id]));

    $response->assertForbidden();
});

test('unauthenticated guest is redirected to login when accessing event design', function () {
    $event = Event::factory()->create();

    $response = $this->get(route('events.design', ['event' => $event->id]));

    $response->assertRedirect(route('login'));
});

test('event owner can update invitation card design with valid JSON', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);

    $validDesign = [
        'version' => 1,
        'width' => 1080,
        'height' => 1528,
        'background' => [
            'type' => 'color',
            'value' => '#0A0A0D',
        ],
        'layers' => [
            [
                'id' => 'text-header',
                'type' => 'text',
                'x' => 100,
                'y' => 200,
                'width' => 880,
                'height' => 80,
                'rotation' => 0,
                'opacity' => 1,
                'locked' => false,
                'text' => 'Wedding of {{host_names}}',
                'fontFamily' => 'Noto Sans Ethiopic',
                'fontSize' => 48,
                'fill' => '#D4AF37',
            ],
        ],
    ];

    $response = $this->actingAs($user)->patchJson(route('events.design.update', ['event' => $event->id]), [
        'design' => $validDesign,
    ]);

    $response->assertOk();
    $response->assertJson([
        'status' => 'success',
    ]);

    $event->refresh();
    expect($event->design_json['background']['value'])->toBe('#0A0A0D')
        ->and($event->design_json['layers'][0]['text'])->toBe('Wedding of {{host_names}}');
});

test('updating design rejects invalid structure with 422', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);

    $invalidDesign = [
        'version' => 1,
        'width' => -100, // Invalid width
        'height' => 1528,
        'background' => ['type' => 'invalid_type', 'value' => 'test'],
        'layers' => 'not an array',
    ];

    $response = $this->actingAs($user)->patchJson(route('events.design.update', ['event' => $event->id]), [
        'design' => $invalidDesign,
    ]);

    $response->assertStatus(422);
});

test('free tier user without thank_you entitlement cannot access thank you editor', function () {
    $user = User::factory()->create(); // default tier is Free with thank_you = false
    $event = Event::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get(route('events.thank-you-design', ['event' => $event->id]));

    $response->assertForbidden();
});

test('paid tier user with thank_you entitlement can access and update thank you editor', function () {
    $user = User::factory()->create();
    $bronzeTier = Tier::where('slug', 'bronze')->first();

    Subscription::create([
        'user_id' => $user->id,
        'tier_id' => $bronzeTier->id,
        'cards_total' => $bronzeTier->card_quota,
        'status' => 'active',
        'starts_at' => now(),
    ]);

    $event = Event::factory()->create(['user_id' => $user->id]);

    // View
    $response = $this->actingAs($user)->get(route('events.thank-you-design', ['event' => $event->id]));
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Editor/Show')
        ->where('target', 'thank_you')
        ->where('hasRemoveBranding', true)
    );

    // Update
    $validDesign = [
        'version' => 1,
        'width' => 1080,
        'height' => 1528,
        'background' => ['type' => 'color', 'value' => '#18181B'],
        'layers' => [
            [
                'id' => 'thankyou-txt',
                'type' => 'text',
                'x' => 100,
                'y' => 300,
                'width' => 880,
                'height' => 100,
                'text' => 'Thank you {{guest_name}} for joining us!',
            ],
        ],
    ];

    $updateResponse = $this->actingAs($user)->patchJson(route('events.thank-you-design.update', ['event' => $event->id]), [
        'design' => $validDesign,
    ]);

    $updateResponse->assertOk();
    $event->refresh();
    expect($event->thankyou_design_json['layers'][0]['text'])->toBe('Thank you {{guest_name}} for joining us!');
});

test('authenticated user can upload an image for card design', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('couple.jpg', 800, 600);

    $response = $this->actingAs($user)->postJson(route('editor.upload-image'), [
        'image' => $file,
    ]);

    $response->assertOk();
    $response->assertJsonStructure([
        'status',
        'url',
        'filename',
    ]);

    $json = $response->json();
    expect($json['filename'])->toBe('couple.jpg');
});

test('guest cannot upload image to editor', function () {
    $file = UploadedFile::fake()->image('test.jpg');

    $response = $this->postJson(route('editor.upload-image'), [
        'image' => $file,
    ]);

    $response->assertUnauthorized();
});

test('branding removal is properly reflected based on user subscription', function () {
    $freeUser = User::factory()->create();
    $eventFree = Event::factory()->create(['user_id' => $freeUser->id]);

    $responseFree = $this->actingAs($freeUser)->get(route('events.design', ['event' => $eventFree->id]));
    $responseFree->assertOk();
    $responseFree->assertInertia(fn (Assert $page) => $page
        ->where('hasRemoveBranding', false)
    );

    $goldTier = Tier::where('slug', 'gold')->first();
    $paidUser = User::factory()->create();
    Subscription::create([
        'user_id' => $paidUser->id,
        'tier_id' => $goldTier->id,
        'cards_total' => $goldTier->card_quota,
        'status' => 'active',
        'starts_at' => now(),
    ]);

    $eventPaid = Event::factory()->create(['user_id' => $paidUser->id]);

    $responsePaid = $this->actingAs($paidUser)->get(route('events.design', ['event' => $eventPaid->id]));
    $responsePaid->assertOk();
    $responsePaid->assertInertia(fn (Assert $page) => $page
        ->where('hasRemoveBranding', true)
    );
});

test('font picker and amharic placeholders verify correctly', function () {
    $validAmharicDesign = [
        'version' => 1,
        'width' => 1080,
        'height' => 1528,
        'background' => ['type' => 'color', 'value' => '#18181B'],
        'layers' => [
            [
                'id' => 'amharic-heading',
                'type' => 'text',
                'x' => 140,
                'y' => 300,
                'width' => 800,
                'height' => 100,
                'text' => 'እንኳን ደስ አላችሁ {{guest_name}}',
                'fontFamily' => 'Noto Sans Ethiopic',
            ],
        ],
    ];

    expect(DesignValidator::isValid($validAmharicDesign))->toBeTrue();
    $placeholders = DesignValidator::getPlaceholders($validAmharicDesign);
    expect($placeholders)->toContain('{{guest_name}}');

    $replaced = DesignValidator::replacePlaceholders($validAmharicDesign, [
        '{{guest_name}}' => 'Almaz Bekele',
    ]);

    expect($replaced['layers'][0]['text'])->toBe('እንኳን ደስ አላችሁ Almaz Bekele');
});
