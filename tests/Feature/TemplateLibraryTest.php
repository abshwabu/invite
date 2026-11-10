<?php

use App\Models\Category;
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
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TierSeeder::class);
    $this->seed(CategorySeeder::class);
    $this->seed(TemplateSeeder::class);
});

test('design validator validates valid contracts and rejects malformed designs', function () {
    $validDesign = [
        'version' => 1,
        'width' => 1080,
        'height' => 1528,
        'background' => [
            'type' => 'color',
            'value' => '#141414',
        ],
        'layers' => [
            [
                'id' => 'layer-1',
                'type' => 'text',
                'x' => 100,
                'y' => 200,
                'width' => 880,
                'height' => 60,
                'text' => 'Hello {{guest_name}}',
                'fontSize' => 32,
            ],
            [
                'id' => 'layer-2',
                'type' => 'shape',
                'shape' => 'rect',
                'x' => 50,
                'y' => 50,
                'width' => 980,
                'height' => 1428,
                'stroke' => '#D4AF37',
                'strokeWidth' => 2,
            ],
        ],
    ];

    expect(DesignValidator::isValid($validDesign))->toBeTrue();
    $normalized = DesignValidator::validate($validDesign);
    expect($normalized['version'])->toBe(1)
        ->and($normalized['width'])->toBe(1080)
        ->and($normalized['layers'])->toHaveCount(2);

    // Test invalid version
    expect(fn () => DesignValidator::validate([...$validDesign, 'version' => 0]))
        ->toThrow(ValidationException::class);

    // Test invalid width
    expect(fn () => DesignValidator::validate([...$validDesign, 'width' => 50]))
        ->toThrow(ValidationException::class);

    // Test invalid background type
    expect(fn () => DesignValidator::validate([
        ...$validDesign,
        'background' => ['type' => 'invalid_type', 'value' => '#000'],
    ]))->toThrow(ValidationException::class);

    // Test invalid layer type
    expect(fn () => DesignValidator::validate([
        ...$validDesign,
        'layers' => [['id' => 'bad-1', 'type' => 'video', 'x' => 0, 'y' => 0, 'width' => 100, 'height' => 100]],
    ]))->toThrow(ValidationException::class);
});

test('design validator extracts placeholders and performs runtime replacement', function () {
    $design = [
        'version' => 1,
        'width' => 1080,
        'height' => 1528,
        'background' => ['type' => 'color', 'value' => '#FFF'],
        'layers' => [
            [
                'id' => 'l1',
                'type' => 'text',
                'x' => 0, 'y' => 0, 'width' => 100, 'height' => 50,
                'text' => 'Dear {{guest_name}}, you are invited to {{event_title}} by {{host_names}}.',
            ],
            [
                'id' => 'l2',
                'type' => 'text',
                'x' => 0, 'y' => 60, 'width' => 100, 'height' => 50,
                'text' => 'Venue: {{venue}} on {{date}} at {{time}}.',
            ],
        ],
    ];

    $placeholders = DesignValidator::getPlaceholders($design);
    expect($placeholders)->toContain('{{guest_name}}', '{{event_title}}', '{{host_names}}', '{{venue}}', '{{date}}', '{{time}}');

    $replaced = DesignValidator::replacePlaceholders($design, [
        '{{guest_name}}' => 'Almaz',
        '{{event_title}}' => 'Solomon & Sara Wedding',
        'venue' => 'Skyline Hotel',
    ]);

    expect($replaced['layers'][0]['text'])->toContain('Dear Almaz')
        ->and($replaced['layers'][0]['text'])->toContain('Solomon & Sara Wedding')
        ->and($replaced['layers'][1]['text'])->toContain('Venue: Skyline Hotel');
});

test('seeder creates at least 8 invitations and at least 5 thank-you templates per category', function () {
    $coreCategories = [
        'wedding',
        'funeral',
        'birthday',
        'graduation',
        'baby-shower',
        'religious',
        'corporate',
        'anniversary',
        'housewarming',
    ];

    foreach ($coreCategories as $slug) {
        $category = Category::where('slug', $slug)->first();
        expect($category)->not->toBeNull();

        $invitationCount = Template::where('category_id', $category->id)
            ->where('kind', 'invitation')
            ->count();

        $thankYouCount = Template::where('category_id', $category->id)
            ->where('kind', 'thank_you')
            ->count();

        expect($invitationCount)->toBeGreaterThanOrEqual(8, "Category {$slug} should have at least 8 invitation templates")
            ->and($thankYouCount)->toBeGreaterThanOrEqual(5, "Category {$slug} should have at least 5 thank-you templates");
    }

    // Check funeral sobriety
    $funeralTemplates = Template::whereHas('category', fn ($q) => $q->where('slug', 'funeral'))->get();
    foreach ($funeralTemplates as $ft) {
        $design = $ft->design_json;
        // Background must not be saturated party colors
        $bgVal = is_string($design['background']['value'] ?? null)
            ? strtolower($design['background']['value'])
            : json_encode($design['background']['value'] ?? '');
        expect($bgVal)->not->toBe('#ff0000')
            ->and($bgVal)->not->toBe('#ffff00');
    }
});

test('templates:render-previews command generates preview PNGs', function () {
    $this->artisan('templates:render-previews')
        ->assertSuccessful();

    $sampleTemplate = Template::where('is_system', true)->first();
    expect($sampleTemplate)->not->toBeNull();

    $relPath = str_replace('/storage/', '', $sampleTemplate->preview_path);
    $fullPath = storage_path('app/public/' . $relPath);

    expect(file_exists($fullPath))->toBeTrue();
    expect(filesize($fullPath))->toBeGreaterThan(500);
});

test('template gallery renders templates and supports kind and category filtering', function () {
    $response = $this->get('/templates?kind=invitation&category=wedding');
    $response->assertOk();

    $responseThankYou = $this->get('/templates?kind=thank_you');
    $responseThankYou->assertOk();
});

test('custom template upload requires authentication and upload_templates tier entitlement', function () {
    Storage::fake('public');

    // 1. Unauthenticated guest
    $guestResponse = $this->post('/templates/upload', []);
    $guestResponse->assertRedirect('/login');

    // 2. User with Free tier (does NOT have upload_templates)
    $freeUser = User::factory()->create();
    $freeUserTier = Tier::where('slug', 'free')->first();
    $freeUser->subscriptions()->create([
        'tier_id' => $freeUserTier->id,
        'cards_total' => 20,
        'cards_used' => 0,
        'starts_at' => now(),
        'status' => 'active',
        'source' => 'free',
    ]);

    $file = UploadedFile::fake()->image('my_card.png', 800, 1200);

    $unentitledResponse = $this->actingAs($freeUser)->post('/templates/upload', [
        'image' => $file,
        'name' => 'My Free Card',
    ]);

    // Should redirect to /plan with warning
    $unentitledResponse->assertRedirect('/plan');
    $unentitledResponse->assertSessionHas('warning');

    // 3. User with Gold tier (HAS upload_templates)
    $goldUser = User::factory()->create();
    $goldTier = Tier::where('slug', 'gold')->first();
    $goldUser->subscriptions()->create([
        'tier_id' => $goldTier->id,
        'cards_total' => 40000,
        'cards_used' => 0,
        'starts_at' => now(),
        'status' => 'active',
        'source' => 'purchase',
    ]);

    $entitledResponse = $this->actingAs($goldUser)->post('/templates/upload', [
        'image' => $file,
        'name' => 'Gold Custom Wedding',
    ]);

    $entitledResponse->assertRedirect();
    $createdTemplate = Template::where('user_id', $goldUser->id)->first();
    expect($createdTemplate)->not->toBeNull()
        ->and($createdTemplate->is_system)->toBeFalse()
        ->and($createdTemplate->name)->toBe('Gold Custom Wedding')
        ->and($createdTemplate->design_json['background']['type'])->toBe('image')
        ->and($createdTemplate->design_json['layers'])->toBeArray()
        ->and($createdTemplate->design_json['layers'])->toBeEmpty();
});

test('custom template upload security rejects non-images, SVG with scripts, and oversized files', function () {
    $goldUser = User::factory()->create();
    $goldTier = Tier::where('slug', 'gold')->first();
    $goldUser->subscriptions()->create([
        'tier_id' => $goldTier->id,
        'cards_total' => 40000,
        'cards_used' => 0,
        'starts_at' => now(),
        'status' => 'active',
        'source' => 'purchase',
    ]);

    // 1. Reject non-image file (e.g. text or pdf)
    $textFile = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');
    $res1 = $this->actingAs($goldUser)->post('/templates/upload', [
        'image' => $textFile,
    ]);
    $res1->assertSessionHasErrors('image');

    // 2. Reject SVG or file containing script tags
    $maliciousFile = UploadedFile::fake()->createWithContent('badge.png', '<script>alert("XSS")</script>');
    $res2 = $this->actingAs($goldUser)->post('/templates/upload', [
        'image' => $maliciousFile,
    ]);
    $res2->assertSessionHasErrors('image');

    // 3. Reject oversized file (> 8 MB)
    $oversizedFile = UploadedFile::fake()->image('huge.png')->size(9000);
    $res3 = $this->actingAs($goldUser)->post('/templates/upload', [
        'image' => $oversizedFile,
    ]);
    $res3->assertSessionHasErrors('image');
});
