<?php

use App\Filament\Pages\OrnamentBrowser;
use App\Models\SuperAdmin;
use App\Services\OrnamentRegistry;
use App\Services\SvgSanitizer;
use Livewire\Livewire;

test('manifest has at least 120 ornaments and all Part 2 sets meet minimum counts and specific IDs', function () {
    $manifestPath = resource_path('ornaments/manifest.json');
    expect(file_exists($manifestPath))->toBeTrue();

    $manifest = json_decode(file_get_contents($manifestPath), true);
    expect($manifest)->toBeArray()
        ->and(count($manifest))->toBeGreaterThanOrEqual(120);

    $manifestIds = [];
    $setCounts = [
        'birthday' => 0,
        'baby-shower' => 0,
        'graduation' => 0,
        'corporate' => 0,
        'housewarming' => 0,
        'shared' => 0,
    ];

    foreach ($manifest as $entry) {
        expect($entry)->toHaveKeys(['id', 'set', 'name_en', 'name_am', 'tags', 'anchor', 'viewBox', 'defaultAspect', 'tintSlots']);

        $id = $entry['id'];
        $set = $entry['set'];
        $manifestIds[] = "{$set}/{$id}";

        if (isset($setCounts[$set])) {
            $setCounts[$set]++;
        }

        // Verify file exists on disk
        $svgFile = resource_path("ornaments/{$set}/{$id}.svg");
        expect(file_exists($svgFile))->toBeTrue();
    }

    // Verify minimum counts for Part 2 sets
    expect($setCounts['birthday'])->toBeGreaterThanOrEqual(12, 'Birthday set must have at least 12 ornaments');
    expect($setCounts['baby-shower'])->toBeGreaterThanOrEqual(10, 'Baby shower set must have at least 10 ornaments');
    expect($setCounts['graduation'])->toBeGreaterThanOrEqual(8, 'Graduation set must have at least 8 ornaments');
    expect($setCounts['corporate'])->toBeGreaterThanOrEqual(8, 'Corporate set must have at least 8 ornaments');
    expect($setCounts['housewarming'])->toBeGreaterThanOrEqual(8, 'Housewarming set must have at least 8 ornaments');
    expect($setCounts['shared'])->toBeGreaterThanOrEqual(16, 'Shared set must have at least 16 ornaments');

    // Specific required IDs for Part 2
    $requiredPart2Ids = [
        'birthday' => [
            'balloon-cluster', 'balloon-arch', 'confetti-scatter-a', 'confetti-scatter-b',
            'streamer-top', 'bunting-flags', 'party-hat', 'gift-box',
            'cake-candles', 'starburst', 'sparkle-trio', 'number-frame-circle',
        ],
        'baby-shower' => [
            'cloud-trio', 'moon-star-sleepy', 'bunting-pastel', 'balloon-pair-soft',
            'rainbow-arc', 'bundle-stork-line', 'teddy-line', 'pram-line',
            'footprints', 'leaf-garland',
        ],
        'graduation' => [
            'mortarboard', 'scroll-ribbon', 'laurel-open', 'diploma-roll',
            'book-stack', 'crest-rays', 'tassel', 'ribbon-seal',
        ],
        'corporate' => [
            'geo-triangles-corner', 'line-grid-field', 'diagonal-bands', 'circle-rings',
            'ticket-notch-frame', 'dot-matrix', 'wave-lines', 'badge-seal',
        ],
        'housewarming' => [
            'house-line-roof', 'key-ring', 'door-wreath', 'plant-pot-trio',
            'jebena-coffee-set-warm', 'window-light-frame', 'welcome-band', 'ivy-vine',
        ],
        'shared' => [
            // 8 Dividers
            'dotted-diamond', 'wave', 'leaf-chain', 'double-rule-ornament',
            'star-rule', 'simple-flourish', 'dots-three', 'thin-line-gap',
            // 6 Frames
            'thin-double-frame', 'notch-frame', 'scalloped-frame', 'arch-frame',
            'circle-frame', 'ticket-frame',
            // 2 Special
            'paper-tear-edge', 'corner-ribbon',
        ],
    ];

    foreach ($requiredPart2Ids as $set => $ids) {
        foreach ($ids as $id) {
            expect($manifestIds)->toContain("{$set}/{$id}");
        }
    }
});

test('every Part 2 ornament SVG passes SvgSanitizer validation without errors', function () {
    $part2Sets = ['birthday', 'baby-shower', 'graduation', 'corporate', 'housewarming', 'shared'];

    foreach ($part2Sets as $set) {
        $svgFiles = glob(resource_path("ornaments/{$set}/*.svg"));
        expect($svgFiles)->not->toBeEmpty();

        foreach ($svgFiles as $file) {
            $raw = file_get_contents($file);

            expect(SvgSanitizer::isValid($raw))->toBeTrue();

            $sanitized = SvgSanitizer::sanitize($raw);
            expect($sanitized)->not->toBeEmpty();
            expect($sanitized)->not->toContain('<script');
            expect($sanitized)->not->toContain('<foreignObject');
            expect($sanitized)->not->toContain('onload=');
            expect($sanitized)->not->toContain('onerror=');
        }
    }
});

test('every Part 2 ornament meets file size, viewBox, and tint token contract', function () {
    $part2Sets = ['birthday', 'baby-shower', 'graduation', 'corporate', 'housewarming', 'shared'];

    foreach ($part2Sets as $set) {
        $svgFiles = glob(resource_path("ornaments/{$set}/*.svg"));

        foreach ($svgFiles as $file) {
            $size = filesize($file);
            expect($size)->toBeLessThan(40960);

            $raw = file_get_contents($file);

            // Has viewBox
            expect($raw)->toContain('viewBox=');

            // No fixed width/height on root svg element
            preg_match('/<svg[^>]*>/', $raw, $matches);
            $rootTag = $matches[0] ?? '';
            expect($rootTag)->not->toMatch('/\bwidth\s*=\s*["\']?\d+/i');
            expect($rootTag)->not->toMatch('/\bheight\s*=\s*["\']?\d+/i');

            // Uses tint color tokens
            expect($raw)->toContain('{{c1}}');
            expect($raw)->toContain('{{c2}}');
        }
    }
});

test('ornament registry successfully loads and tints Part 2 ornaments', function () {
    $palette = [
        'bg' => '#FAF7F2',
        'surface' => '#FFFFFF',
        'primary' => '#800020',
        'secondary' => '#D4AF37',
        'accent' => '#C5A059',
        'ink' => '#1F2937',
        'muted' => '#6B7280',
    ];

    $testIds = [
        'balloon-cluster',
        'cloud-trio',
        'mortarboard',
        'geo-triangles-corner',
        'house-line-roof',
        'dotted-diamond',
        'notch-frame',
        'corner-ribbon',
    ];

    foreach ($testIds as $id) {
        $tinted = OrnamentRegistry::loadTintedSvg($id, [
            'c1' => '@primary',
            'c2' => '@secondary',
            'c3' => '@accent',
        ], $palette);

        expect($tinted)->not->toBeNull()
            ->and($tinted)->toContain('#800020')
            ->and($tinted)->toContain('#D4AF37')
            ->and($tinted)->not->toContain('{{c1}}')
            ->and($tinted)->not->toContain('{{c2}}');
    }
});

test('contact sheet PNGs and review notes exist for Part 2 sets', function () {
    $part2Sets = ['birthday', 'baby-shower', 'graduation', 'corporate', 'housewarming', 'shared'];

    foreach ($part2Sets as $set) {
        $pngPath = storage_path("app/ornament-sheets/{$set}.png");
        expect(file_exists($pngPath))->toBeTrue();
        expect(filesize($pngPath))->toBeGreaterThan(10000);
    }

    $notesPath = storage_path('app/ornament-sheets/NOTES-part2.md');
    expect(file_exists($notesPath))->toBeTrue();
    expect(file_get_contents($notesPath))->toContain('birthday');
    expect(file_get_contents($notesPath))->toContain('shared');
});

test('super admin can access and interact with the ornament browser page', function () {
    $superadmin = SuperAdmin::create([
        'name' => 'Root Superadmin',
        'email' => 'superadmin@invite.test',
        'password' => bcrypt('Secret123!'),
        'role' => 'owner',
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => now(),
    ]);

    $this->actingAs($superadmin, 'superadmin');

    $secretPath = config('invite.superadmin_path', 'sa-x7k2p9q4m1');

    $response = $this->withSession(['superadmin_2fa_passed' => true])
        ->get("/{$secretPath}/ornament-browser");
    $response->assertSuccessful();
    $response->assertSee('Ornament Library Browser');

    // Livewire component test
    Livewire::actingAs($superadmin, 'superadmin')
        ->test(OrnamentBrowser::class)
        ->assertSuccessful()
        ->assertSee('Festive Balloon Cluster')
        ->set('setFilter', 'birthday')
        ->assertSee('balloon-cluster')
        ->assertDontSee('cloud-trio')
        ->set('search', 'mortarboard')
        ->set('setFilter', 'all')
        ->assertSee('mortarboard')
        ->assertDontSee('balloon-cluster')
        ->call('applyPreset', 'emerald-copper')
        ->assertSet('c1', '#1E5E41')
        ->assertSet('c2', '#B85D19')
        ->assertSet('c3', '#5C9E7A')
        ->call('downloadSvg', 'balloon-cluster', true)
        ->assertFileDownloaded('balloon-cluster-tinted.svg');
});
