<?php

use App\Models\Category;
use App\Models\Template;
use App\Services\DesignValidator;
use App\Templates\Fonts\FontPairingLibrary;
use Database\Seeders\InvitationTemplateSeeder;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->seed(InvitationTemplateSeeder::class);
});

test('invitation template seeder creates exactly 84 invitation templates matching category counts', function () {
    $expectedCounts = [
        'wedding' => 10,
        'funeral' => 8,
        'birthday' => 10,
        'graduation' => 8,
        'baby-shower' => 8,
        'religious' => 10,
        'corporate' => 8,
        'anniversary' => 8,
        'housewarming' => 8,
        'other' => 6,
    ];

    $totalExpected = array_sum($expectedCounts);
    expect($totalExpected)->toBe(84);

    $totalActual = Template::where('kind', 'invitation')->where('is_system', true)->count();
    expect($totalActual)->toBe(84);

    foreach ($expectedCounts as $slug => $count) {
        $category = Category::where('slug', $slug)->first();
        expect($category)->not->toBeNull("Category with slug [{$slug}] must exist");

        $actualCount = Template::where('kind', 'invitation')
            ->where('category_id', $category->id)
            ->where('is_system', true)
            ->count();

        expect($actualCount)->toBe($count, "Category [{$slug}] must have exactly {$count} invitation templates, got {$actualCount}");
    }
});

test('every category satisfies distinctness rules for recipes, palettes, font pairings, and unique tuples', function () {
    $expectedCounts = [
        'wedding' => ['recipes' => 5, 'palettes' => 3, 'fonts' => 3],
        'funeral' => ['recipes' => 5, 'palettes' => 3, 'fonts' => 3],
        'birthday' => ['recipes' => 5, 'palettes' => 3, 'fonts' => 3],
        'graduation' => ['recipes' => 5, 'palettes' => 3, 'fonts' => 3],
        'baby-shower' => ['recipes' => 5, 'palettes' => 3, 'fonts' => 3],
        'religious' => ['recipes' => 5, 'palettes' => 3, 'fonts' => 3],
        'corporate' => ['recipes' => 5, 'palettes' => 3, 'fonts' => 3],
        'anniversary' => ['recipes' => 5, 'palettes' => 3, 'fonts' => 3],
        'housewarming' => ['recipes' => 5, 'palettes' => 3, 'fonts' => 3],
        'other' => ['recipes' => 3, 'palettes' => 2, 'fonts' => 2],
    ];

    foreach ($expectedCounts as $slug => $mins) {
        $category = Category::where('slug', $slug)->first();
        $templates = Template::where('kind', 'invitation')
            ->where('category_id', $category->id)
            ->get();

        $recipes = [];
        $palettes = [];
        $fonts = [];
        $tuples = [];

        foreach ($templates as $tmpl) {
            $spec = $tmpl->spec;
            expect($spec)->toBeArray();

            $recipe = $spec['recipe'] ?? null;
            $palette = $spec['palette'] ?? null;
            $font = $spec['fonts'] ?? null;
            $topOrnament = $spec['ornaments']['top'] ?? 'none';

            $recipes[$recipe] = true;
            $palettes[$palette] = true;
            $fonts[$font] = true;

            $tuple = "{$recipe}::{$topOrnament}";
            expect(in_array($tuple, $tuples, true))
                ->toBeFalse("Category [{$slug}] has duplicate (recipe, top_ornament) tuple: '{$tuple}' in template [{$tmpl->name}]");
            $tuples[] = $tuple;
        }

        expect(count($recipes))->toBeGreaterThanOrEqual(
            $mins['recipes'],
            "Category [{$slug}] must have at least {$mins['recipes']} distinct recipes, found " . count($recipes)
        );

        expect(count($palettes))->toBeGreaterThanOrEqual(
            $mins['palettes'],
            "Category [{$slug}] must have at least {$mins['palettes']} distinct palettes, found " . count($palettes)
        );

        expect(count($fonts))->toBeGreaterThanOrEqual(
            $mins['fonts'],
            "Category [{$slug}] must have at least {$mins['fonts']} distinct font pairings, found " . count($fonts)
        );
    }
});

test('every invitation template passes DesignValidator with strict quality checks', function () {
    $templates = Template::where('kind', 'invitation')->get();
    expect($templates->count())->toBe(84);

    foreach ($templates as $tmpl) {
        $design = $tmpl->design_json;
        expect($design)->toBeArray("Template [{$tmpl->name}] must have design_json array");

        // Validate strictly with quality checks enabled
        $validated = DesignValidator::validate($design, false, true);

        expect($validated['version'])->toBe(2)
            ->and($validated['width'])->toBe(1080)
            ->and($validated['height'])->toBe(1528)
            ->and($validated['palette'])->toBeArray()
            ->and($validated['background'])->toBeArray()
            ->and($validated['layers'])->toBeArray();
    }
});

test('every invitation template has frame/band, at least 2 ornaments, non-flat background, and full information stack', function () {
    $templates = Template::where('kind', 'invitation')->get();

    foreach ($templates as $tmpl) {
        $design = $tmpl->design_json;
        $layers = $design['layers'];

        // 1. Texture or gradient background (never flat #FFF without texture)
        $bg = $design['background'];
        $hasTexture = isset($bg['texture']['kind']) && $bg['texture']['kind'] !== 'none';
        $isGradient = ($bg['type'] ?? '') === 'gradient';
        $hasColor = ($bg['type'] ?? '') === 'color';
        expect($hasTexture || $isGradient)->toBeTrue(
            "Template [{$tmpl->name}] must have either a texture or gradient background"
        );

        if ($hasColor && !$isGradient) {
            $val = strtolower((string)($bg['value'] ?? ''));
            if ($val === '#fff' || $val === '#ffffff') {
                expect($hasTexture)->toBeTrue("Template [{$tmpl->name}] cannot have flat white without texture");
            }
        }

        // 2. At least 2 ornament layers
        $ornamentLayers = array_filter($layers, fn ($l) => in_array($l['type'] ?? '', ['ornament', 'pattern'], true));
        expect(count($ornamentLayers))->toBeGreaterThanOrEqual(
            2,
            "Template [{$tmpl->name}] must have at least 2 ornament/pattern layers, got " . count($ornamentLayers)
        );

        // 3. Has frame/border layer or pattern/ornament band
        $hasFrameOrBand = false;
        foreach ($layers as $l) {
            $id = $l['id'] ?? '';
            $type = $l['type'] ?? '';
            if (
                str_contains($id, 'frame') ||
                str_contains($id, 'border') ||
                str_contains($id, 'band') ||
                str_contains($id, 'arch') ||
                str_contains($id, 'panel') ||
                str_contains($id, 'wash') ||
                str_contains($id, 'scroll') ||
                str_contains($id, 'ticket') ||
                ($type === 'shape' && (($l['strokeWidth'] ?? 0) > 0 || ($l['fill'] ?? '') !== 'none'))
            ) {
                $hasFrameOrBand = true;
                break;
            }
        }
        expect($hasFrameOrBand)->toBeTrue(
            "Template [{$tmpl->name}] must have a frame, border, arch, panel, or band structure"
        );

        // 4. Full information stack: opening line, event title, host names, date, time, venue, closing line
        $layerIds = array_column($layers, 'id');
        $allText = implode(' ', array_filter(array_column($layers, 'text')));

        // Check required IDs or placeholders in text
        $hasOpening = in_array('text-opening', $layerIds, true);
        $hasTitle = in_array('text-title', $layerIds, true) || in_array('text-display', $layerIds, true);
        $hasHosts = in_array('text-hosts', $layerIds, true) || str_contains($allText, '{{host_names}}') || str_contains($allText, 'Hosted') || str_contains($allText, 'በክብር አዘጋጆች');
        $hasClosing = in_array('text-closing', $layerIds, true);

        // Date, time, venue: either dedicated layers or structured details/meta block
        $hasDetailsBlock = in_array('text-details', $layerIds, true) || in_array('text-meta', $layerIds, true);
        $hasDate = in_array('text-date', $layerIds, true) || str_contains($allText, '{{date}}') || $hasDetailsBlock;
        $hasTime = in_array('text-time', $layerIds, true) || str_contains($allText, '{{time}}') || $hasDetailsBlock;
        $hasVenue = in_array('text-venue', $layerIds, true) || str_contains($allText, '{{venue}}') || $hasDetailsBlock;

        expect($hasOpening)->toBeTrue("Template [{$tmpl->name}] missing opening line")
            ->and($hasTitle)->toBeTrue("Template [{$tmpl->name}] missing title")
            ->and($hasHosts)->toBeTrue("Template [{$tmpl->name}] missing host names")
            ->and($hasDate)->toBeTrue("Template [{$tmpl->name}] missing date")
            ->and($hasTime)->toBeTrue("Template [{$tmpl->name}] missing time")
            ->and($hasVenue)->toBeTrue("Template [{$tmpl->name}] missing venue")
            ->and($hasClosing)->toBeTrue("Template [{$tmpl->name}] missing closing line");
    }
});

test('category art direction constraints are strictly enforced', function () {
    // 1. Corporate: no florals, no script fonts
    $corpCategory = Category::where('slug', 'corporate')->first();
    $corpTemplates = Template::where('kind', 'invitation')->where('category_id', $corpCategory->id)->get();
    expect($corpTemplates->count())->toBe(8);

    foreach ($corpTemplates as $t) {
        $spec = $t->spec;
        $fontPairing = FontPairingLibrary::get($spec['fonts'] ?? '');
        $scriptFont = $fontPairing['script-or-accent'] ?? '';
        expect(in_array($scriptFont, ['Pinyon Script', 'Alex Brush', 'Great Vibes', 'Caveat'], true))
            ->toBeFalse("Corporate template [{$t->name}] must not use script font, got '{$scriptFont}'");

        $ornamentsUsed = array_merge(
            (array)($spec['ornaments']['top'] ?? []),
            (array)($spec['ornaments']['divider'] ?? []),
            (array)($spec['ornaments']['corner'] ?? [])
        );
        foreach ($ornamentsUsed as $ornId) {
            expect($ornId)->not->toContain('floral', "Corporate template [{$t->name}] must not use floral ornament [{$ornId}]");
        }
    }

    // 2. Funeral: solemn palettes, no party ornaments, faith-aware symbols
    $funeralCategory = Category::where('slug', 'funeral')->first();
    $funeralTemplates = Template::where('kind', 'invitation')->where('category_id', $funeralCategory->id)->get();
    expect($funeralTemplates->count())->toBe(8);

    foreach ($funeralTemplates as $t) {
        $spec = $t->spec;
        $ornamentsUsed = array_merge(
            (array)($spec['ornaments']['top'] ?? []),
            (array)($spec['ornaments']['divider'] ?? []),
            (array)($spec['ornaments']['corner'] ?? [])
        );

        foreach ($ornamentsUsed as $ornId) {
            expect($ornId)->not->toContain('balloon')
                ->and($ornId)->not->toContain('confetti')
                ->and($ornId)->not->toContain('streamer')
                ->and($ornId)->not->toContain('party');
        }
    }

    // 3. Religious: covers Eid, Ramadan, Mawlid, Meskel, Timkat, Genna, Hajj suites
    $religiousCategory = Category::where('slug', 'religious')->first();
    $religiousTemplates = Template::where('kind', 'invitation')->where('category_id', $religiousCategory->id)->get();
    expect($religiousTemplates->count())->toBe(10);

    $keys = $religiousTemplates->pluck('suite_key')->all();
    $joinedKeys = implode(' ', $keys);
    expect($joinedKeys)->toContain('eid')
        ->and($joinedKeys)->toContain('ramadan')
        ->and($joinedKeys)->toContain('mawlid')
        ->and($joinedKeys)->toContain('meskel')
        ->and($joinedKeys)->toContain('timkat')
        ->and($joinedKeys)->toContain('genna')
        ->and($joinedKeys)->toContain('hajj');

    // 4. Graduation: academic ornaments
    $gradCategory = Category::where('slug', 'graduation')->first();
    $gradTemplates = Template::where('kind', 'invitation')->where('category_id', $gradCategory->id)->get();
    expect($gradTemplates->count())->toBe(8);

    $gradOrnamentsAll = [];
    foreach ($gradTemplates as $t) {
        $spec = $t->spec;
        $gradOrnamentsAll = array_merge(
            $gradOrnamentsAll,
            (array)($spec['ornaments']['top'] ?? []),
            (array)($spec['ornaments']['divider'] ?? []),
            (array)($spec['ornaments']['corner'] ?? [])
        );
    }
    $hasAcademic = false;
    foreach ($gradOrnamentsAll as $orn) {
        if (str_contains($orn, 'mortarboard') || str_contains($orn, 'diploma') || str_contains($orn, 'laurel') || str_contains($orn, 'scroll') || str_contains($orn, 'crest')) {
            $hasAcademic = true;
            break;
        }
    }
    expect($hasAcademic)->toBeTrue('Graduation templates must feature academic ornaments');
});

test('invitation template seeder is strictly idempotent', function () {
    // Initial count after beforeEach seed is 84
    expect(Template::where('kind', 'invitation')->count())->toBe(84);

    $initialIds = Template::where('kind', 'invitation')->pluck('id', 'suite_key')->all();
    expect(count($initialIds))->toBe(84);

    // Run seeder again
    $this->seed(InvitationTemplateSeeder::class);

    $afterCount = Template::where('kind', 'invitation')->count();
    expect($afterCount)->toBe(84);

    $afterIds = Template::where('kind', 'invitation')->pluck('id', 'suite_key')->all();
    expect($afterIds)->toEqual($initialIds);
});
