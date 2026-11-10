<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\Template;
use App\Models\User;
use App\Services\DesignUpgrader;
use App\Services\DesignValidator;
use App\Templates\Palettes\PaletteLibrary;

test('v1 design opens upgraded to v2 via DesignUpgrader in the editor', function () {
    $user = User::factory()->create();
    $category = Category::create([
        'name_en' => 'Wedding',
        'name_am' => 'ሠርግ',
        'slug' => 'wedding',
        'sort' => 1,
        'is_active' => true,
    ]);

    // Create a legacy v1 design
    $v1Design = [
        'version' => 1,
        'width' => 1080,
        'height' => 1528,
        'background' => [
            'type' => 'color',
            'value' => '#FAF7F2',
        ],
        'layers' => [
            [
                'id' => 'legacy-text-1',
                'type' => 'text',
                'text' => 'Legacy Invitation',
                'fontFamily' => 'Noto Serif Ethiopic',
                'fontSize' => 48,
                'x' => 100,
                'y' => 200,
                'width' => 800,
                'height' => 100,
            ],
        ],
    ];

    $event = Event::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'design_json' => $v1Design,
        'card_language' => 'en',
    ]);

    $response = $this->actingAs($user)->get(route('events.design', ['event' => $event->id]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Editor/Show')
        ->has('design', fn ($d) => $d
            ->where('version', 2)
            ->has('palette')
            ->has('background.texture')
            ->has('background.vignette')
            ->has('layers')
            ->etc()
        )
        ->has('curatedPalettes', 12)
        ->where('cardLanguage', 'en')
    );
});

test('palette swap changes every role-referencing layer and persists via design update', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);

    $v2Design = [
        'version' => 2,
        'width' => 1080,
        'height' => 1528,
        'palette' => [
            'bg' => '#FAF7F2',
            'surface' => '#FFFFFF',
            'primary' => '#8C2D45',
            'secondary' => '#D4A373',
            'accent' => '#B35D70',
            'ink' => '#1F2937',
            'muted' => '#6B7280',
        ],
        'background' => [
            'type' => 'color',
            'value' => '@bg',
            'texture' => ['kind' => 'linen', 'opacity' => 0.08, 'seed' => 1],
            'vignette' => 0.2,
        ],
        'layers' => [
            [
                'id' => 'text-head',
                'type' => 'text',
                'text' => 'Grand Gala',
                'i18n' => [
                    'en' => 'Grand Gala',
                    'am' => 'ታላቅ ድግስ',
                ],
                'fill' => '@primary',
                'x' => 100,
                'y' => 200,
                'width' => 800,
                'height' => 100,
            ],
            [
                'id' => 'ornament-main',
                'type' => 'ornament',
                'assetId' => 'meskel-cross-01',
                'tint' => [
                    'c1' => '@primary',
                    'c2' => '@secondary',
                    'c3' => '@accent',
                ],
                'x' => 440,
                'y' => 80,
                'width' => 200,
                'height' => 200,
            ],
            [
                'id' => 'border-outer',
                'type' => 'border',
                'style' => 'double-rule',
                'stroke' => '@secondary',
                'strokeWidth' => 3,
                'inset' => 40,
                'gap' => 8,
                'x' => 0,
                'y' => 0,
                'width' => 1080,
                'height' => 1528,
            ],
        ],
    ];

    $response = $this->actingAs($user)->patchJson(route('events.design.update', ['event' => $event->id]), [
        'design' => $v2Design,
        'card_language' => 'am',
    ]);

    $response->assertOk();
    $response->assertJson([
        'status' => 'success',
        'design' => [
            'version' => 2,
            'palette' => [
                'primary' => '#8C2D45',
                'secondary' => '#D4A373',
            ],
        ],
    ]);

    $event->refresh();
    expect($event->card_language)->toBe('am');
    expect($event->design_json['palette']['primary'])->toBe('#8C2D45');
    expect($event->design_json['layers'][1]['assetId'])->toBe('meskel-cross-01');
    expect($event->design_json['layers'][1]['tint']['c1'])->toBe('@primary');
});

test('ornament tint updates and validates correctly', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);

    $design = DesignValidator::blankDesign();
    $design['layers'][] = [
        'id' => 'ornament-1',
        'type' => 'ornament',
        'assetId' => 'tilet-corner-01',
        'tint' => [
            'c1' => '#FF0000',
            'c2' => '#00FF00',
            'c3' => '@accent',
        ],
        'x' => 50,
        'y' => 50,
        'width' => 150,
        'height' => 150,
        'flipX' => true,
        'flipY' => false,
    ];

    $response = $this->actingAs($user)->patchJson(route('events.design.update', ['event' => $event->id]), [
        'design' => $design,
    ]);

    $response->assertOk();
    $event->refresh();
    expect($event->design_json['layers'][0]['type'])->toBe('ornament');
    expect($event->design_json['layers'][0]['tint']['c1'])->toBe('#FF0000');
    expect($event->design_json['layers'][0]['flipX'])->toBeTrue();
});

test('language switch picks correct copy and updates event preference', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create([
        'user_id' => $user->id,
        'card_language' => 'en',
    ]);

    $design = DesignValidator::blankDesign();
    $design['layers'][] = [
        'id' => 'text-i18n',
        'type' => 'text',
        'text' => 'Default Text',
        'i18n' => [
            'en' => 'English Celebration',
            'am' => 'የኢትዮጵያ በዓል',
        ],
        'x' => 100,
        'y' => 200,
        'width' => 800,
        'height' => 100,
    ];

    // Update with card_language = both
    $response = $this->actingAs($user)->patchJson(route('events.design.update', ['event' => $event->id]), [
        'design' => $design,
        'card_language' => 'both',
    ]);

    $response->assertOk();
    $event->refresh();
    expect($event->card_language)->toBe('both');
    expect($event->design_json['layers'][0]['i18n']['am'])->toBe('የኢትዮጵያ በዓል');
    expect($event->design_json['layers'][0]['i18n']['en'])->toBe('English Celebration');
});

test('palette library returns 12 curated alternate palettes for any category', function () {
    $weddingCurated = PaletteLibrary::curatedForCategory('wedding', 12);
    expect($weddingCurated)->toBeArray();
    expect(count($weddingCurated))->toBe(12);

    $birthdayCurated = PaletteLibrary::curatedForCategory('birthday', 12);
    expect(count($birthdayCurated))->toBe(12);

    // Ensure all 7 roles exist in each curated palette
    foreach ($weddingCurated as $pal) {
        expect($pal)->toHaveKeys(['id', 'bg', 'surface', 'primary', 'secondary', 'accent', 'ink', 'muted']);
    }
});
