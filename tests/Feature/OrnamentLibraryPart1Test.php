<?php

use App\Services\OrnamentRegistry;
use App\Services\SvgSanitizer;
use Symfony\Component\Process\Process;

test('every manifest entry has a corresponding SVG file and vice-versa with required counts', function () {
    $manifestPath = resource_path('ornaments/manifest.json');
    expect(file_exists($manifestPath))->toBeTrue();

    $manifest = json_decode(file_get_contents($manifestPath), true);
    expect($manifest)->toBeArray()
        ->and(count($manifest))->toBeGreaterThanOrEqual(67);

    $manifestIds = [];
    $setCounts = [
        'wedding' => 0,
        'anniversary' => 0,
        'funeral' => 0,
        'faith-symbols' => 0,
        'religious-islamic' => 0,
        'religious-ethiopian' => 0,
    ];

    foreach ($manifest as $entry) {
        expect($entry)->toHaveKeys(['id', 'set', 'name_en', 'name_am', 'tags', 'anchor', 'viewBox', 'defaultAspect', 'tintSlots']);

        $id = $entry['id'];
        $set = $entry['set'];
        $manifestIds[] = "{$set}/{$id}";

        if (isset($setCounts[$set])) {
            $setCounts[$set]++;
        }

        // Verify file exists
        $svgFile = resource_path("ornaments/{$set}/{$id}.svg");
        expect(file_exists($svgFile))->toBeTrue("Missing SVG file for manifest entry: {$set}/{$id}");
    }

    // Verify minimum counts for Part 1 sets
    expect($setCounts['wedding'])->toBeGreaterThanOrEqual(14, 'Wedding set must have at least 14 ornaments');
    expect($setCounts['anniversary'])->toBeGreaterThanOrEqual(8, 'Anniversary set must have at least 8 ornaments');
    expect($setCounts['funeral'])->toBeGreaterThanOrEqual(10, 'Funeral set must have at least 10 ornaments');
    expect($setCounts['faith-symbols'])->toBeGreaterThanOrEqual(7, 'Faith symbols set must have at least 7 ornaments');
    expect($setCounts['religious-islamic'])->toBeGreaterThanOrEqual(8, 'Religious islamic set must have at least 8 ornaments');
    expect($setCounts['religious-ethiopian'])->toBeGreaterThanOrEqual(9, 'Religious ethiopian set must have at least 9 ornaments');

    // Check specific required IDs from Part 1 specification
    $requiredIds = [
        'wedding' => [
            'floral-corner-garden', 'floral-corner-peony', 'floral-arch-wreath', 'wreath-round-eucalyptus',
            'sprig-olive', 'sprig-wildflower', 'bouquet-bottom-spray', 'rings-interlocked',
            'flourish-divider-ornate', 'monogram-laurel-frame', 'dove-pair', 'botanical-side-vine',
            'gold-leaf-scatter', 'lace-edge-band',
        ],
        'anniversary' => [
            'laurel-wreath-pair', 'heart-line-art', 'numeral-medallion-frame', 'champagne-clink',
            'ribbon-banner', 'rose-single', 'sparkle-cluster', 'art-deco-fan',
        ],
        'funeral' => [
            'olive-branch-pair', 'lily-single', 'lily-spray', 'dove-ascending',
            'candle-flame', 'soft-rays', 'double-rule-frame-corner', 'willow-branch',
            'wing-pair', 'quiet-corner-flourish',
        ],
        'faith-symbols' => [
            'ethiopian-orthodox-cross-lalibela', 'ethiopian-orthodox-cross-axum', 'ethiopian-orthodox-cross-gondar',
            'latin-cross-simple', 'crescent-star-simple', 'praying-hands-line', 'dove-olive',
        ],
        'religious-islamic' => [
            'star-8-tile-pattern', 'star-12-rosette', 'arabesque-corner', 'mihrab-arch-frame',
            'crescent-lantern', 'hanging-lantern-pair', 'mosque-skyline', 'dome-minaret-silhouette',
        ],
        'religious-ethiopian' => [
            'meskel-cross-ornate', 'adey-abeba-daisy-sprig', 'tilet-band-straight', 'tilet-band-corner',
            'tilet-medallion', 'mesob-line', 'jebena-cup-set', 'lalibela-cross-border', 'genna-star',
        ],
    ];

    foreach ($requiredIds as $set => $ids) {
        foreach ($ids as $id) {
        expect($manifestIds)->toContain("{$set}/{$id}");
        }
    }

    // Vice-versa: check that every .svg file on disk is registered in manifest.json
    $svgFiles = glob(resource_path('ornaments/*/*.svg'));
    expect($svgFiles)->not->toBeEmpty();

    foreach ($svgFiles as $file) {
        $relPath = str_replace(resource_path('ornaments/'), '', $file);
        $relPath = str_replace('.svg', '', $relPath);
        expect($manifestIds)->toContain($relPath);
    }
});

test('every ornament SVG passes SvgSanitizer validation without errors', function () {
    $svgFiles = glob(resource_path('ornaments/*/*.svg'));
    expect($svgFiles)->not->toBeEmpty();

    foreach ($svgFiles as $file) {
        $raw = file_get_contents($file);
        $basename = basename($file);

        // Valid according to SvgSanitizer
        expect(SvgSanitizer::isValid($raw))->toBeTrue();

        // Sanitize must succeed
        $sanitized = SvgSanitizer::sanitize($raw);
        expect($sanitized)->not->toBeEmpty();

        // Must not contain forbidden strings
        expect($sanitized)->not->toContain('<script');
        expect($sanitized)->not->toContain('<foreignObject');
        expect($sanitized)->not->toContain('onload=');
        expect($sanitized)->not->toContain('onerror=');
    }
});

test('every ornament file meets size, viewBox, and tint token contract', function () {
    $svgFiles = glob(resource_path('ornaments/*/*.svg'));

    foreach ($svgFiles as $file) {
        $basename = basename($file);
        $size = filesize($file);

        // Under 40 KB
        expect($size)->toBeLessThan(40960);

        $raw = file_get_contents($file);

        // Has viewBox
        expect($raw)->toContain('viewBox=');

        // No fixed width/height on root svg element
        preg_match('/<svg[^>]*>/', $raw, $matches);
        $rootTag = $matches[0] ?? '';
        expect($rootTag)->not->toMatch('/\bwidth\s*=\s*["\']?\d+/i');
        expect($rootTag)->not->toMatch('/\bheight\s*=\s*["\']?\d+/i');

        // Uses color tokens
        expect($raw)->toContain('{{c1}}');
        expect($raw)->toContain('{{c2}}');
    }
});

test('ornament registry correctly loads and tints ornaments with full palettes', function () {
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
        'floral-corner-garden',
        'laurel-wreath-pair',
        'olive-branch-pair',
        'ethiopian-orthodox-cross-lalibela',
        'star-8-tile-pattern',
        'tilet-band-straight',
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

test('tileable ornaments tile without visible seams when rendering 3 adjacent tiles', function () {
    $script = base_path('scripts/verify_tiling.cjs');
    expect(file_exists($script))->toBeTrue();

    $process = new Process(['node', $script]);
    $process->run();

    expect($process->isSuccessful())->toBeTrue("Tiling verification failed:\n" . $process->getOutput() . "\n" . $process->getErrorOutput());
    expect($process->getOutput())->toContain('All tileable ornaments passed seamless tiling verification');
});

test('artisan ornaments:sheet command successfully generates contact sheet PNGs', function () {
    $this->artisan('ornaments:sheet', ['set' => 'anniversary'])
        ->assertSuccessful();

    $pngPath = storage_path('app/ornament-sheets/anniversary.png');
    expect(file_exists($pngPath))->toBeTrue();
    expect(filesize($pngPath))->toBeGreaterThan(10000);
});
