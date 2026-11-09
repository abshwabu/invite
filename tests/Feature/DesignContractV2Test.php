<?php

use App\Services\DesignRendererService;
use App\Services\DesignUpgrader;
use App\Services\DesignValidator;
use App\Services\OrnamentRegistry;
use App\Services\PaletteResolver;
use App\Services\SvgSanitizer;
use Illuminate\Validation\ValidationException;

test('design contract v2 validates valid designs and rejects malformed designs', function () {
    $validV2 = [
        'version' => 2,
        'width' => 1080,
        'height' => 1528,
        'palette' => [
            'bg' => '#FAF7F2',
            'surface' => '#FFFFFF',
            'primary' => '#800020',
            'secondary' => '#D4AF37',
            'accent' => '#C5A059',
            'ink' => '#1F2937',
            'muted' => '#6B7280',
        ],
        'background' => [
            'type' => 'color',
            'value' => '@bg',
            'texture' => [
                'kind' => 'paper',
                'opacity' => 0.08,
                'seed' => 1,
            ],
            'vignette' => 0.1,
        ],
        'layers' => [
            [
                'id' => 'border-1',
                'name' => 'Main Border',
                'type' => 'border',
                'style' => 'double-rule',
                'inset' => 40,
                'stroke' => '@primary',
                'strokeWidth' => 2,
                'x' => 0,
                'y' => 0,
                'width' => 1080,
                'height' => 1528,
            ],
            [
                'id' => 'ornament-1',
                'name' => 'Header Cross',
                'type' => 'ornament',
                'assetId' => 'meskel-cross-01',
                'tint' => [
                    'c1' => '@primary',
                    'c2' => '@secondary',
                    'c3' => '@accent',
                ],
                'x' => 490,
                'y' => 100,
                'width' => 100,
                'height' => 100,
            ],
            [
                'id' => 'text-1',
                'name' => 'Couple Names',
                'type' => 'text',
                'text' => 'Dawit & Almaz',
                'fontFamily' => 'Playfair Display',
                'fontSize' => 48,
                'fontWeight' => 600,
                'fill' => '@primary',
                'curve' => 250,
                'x' => 240,
                'y' => 240,
                'width' => 600,
                'height' => 120,
            ],
            [
                'id' => 'pattern-1',
                'name' => 'Accent Pattern Band',
                'type' => 'pattern',
                'assetId' => 'tilet-divider-01',
                'x' => 140,
                'y' => 400,
                'width' => 800,
                'height' => 50,
            ],
        ],
    ];

    expect(DesignValidator::isValid($validV2))->toBeTrue();
    $validated = DesignValidator::validate($validV2);
    expect($validated['version'])->toBe(2)
        ->and($validated['palette']['primary'])->toBe('#800020')
        ->and($validated['layers'])->toHaveCount(4);

    // Rejects missing palette
    $badPalette = $validV2;
    unset($badPalette['palette']);
    expect(fn () => DesignValidator::validate($badPalette))->toThrow(ValidationException::class);

    // Rejects invalid background type
    $badBg = $validV2;
    $badBg['background']['type'] = 'video';
    expect(fn () => DesignValidator::validate($badBg))->toThrow(ValidationException::class);

    // Rejects invalid layer type
    $badLayer = $validV2;
    $badLayer['layers'][] = ['id' => 'bad-1', 'type' => 'unsupported', 'x' => 0, 'y' => 0, 'width' => 10, 'height' => 10];
    expect(fn () => DesignValidator::validate($badLayer))->toThrow(ValidationException::class);
});

test('v1 designs are automatically upgraded to complete valid v2 designs', function () {
    $v1Design = [
        'version' => 1,
        'width' => 1080,
        'height' => 1528,
        'background' => [
            'type' => 'color',
            'value' => '#141414',
        ],
        'layers' => [
            [
                'id' => 'l1',
                'type' => 'text',
                'x' => 100,
                'y' => 200,
                'width' => 880,
                'height' => 60,
                'text' => 'Hello {{guest_name}}',
                'fontSize' => 32,
            ],
            [
                'id' => 'l2',
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

    $upgraded = DesignUpgrader::upgrade($v1Design);

    expect($upgraded['version'])->toBe(2)
        ->and($upgraded)->toHaveKey('palette')
        ->and($upgraded['palette'])->toHaveKeys(['bg', 'surface', 'primary', 'secondary', 'accent', 'ink', 'muted'])
        ->and($upgraded['background']['texture']['kind'])->toBe('none')
        ->and($upgraded['layers'][0]['type'])->toBe('text')
        ->and($upgraded['layers'][0])->toHaveKey('blendMode')
        ->and(DesignValidator::isValid($upgraded))->toBeTrue();

    // DesignValidator::validate with autoUpgrade upgrades v1 to v2
    $validated = DesignValidator::validate($v1Design, autoUpgrade: true);
    expect($validated['version'])->toBe(2);
});

test('palette resolver resolves color roles, swaps palettes, and derives harmonious palettes', function () {
    $palette = [
        'bg' => '#FAF7F2',
        'surface' => '#FFFFFF',
        'primary' => '#800020',
        'secondary' => '#D4AF37',
        'accent' => '#C5A059',
        'ink' => '#1F2937',
        'muted' => '#6B7280',
    ];

    // 1. Resolve role references
    expect(PaletteResolver::resolve('@primary', $palette))->toBe('#800020');
    expect(PaletteResolver::resolve('@secondary', $palette))->toBe('#D4AF37');
    expect(PaletteResolver::resolve('@bg', $palette))->toBe('#FAF7F2');
    expect(PaletteResolver::resolve('#123456', $palette))->toBe('#123456');

    // 2. Palette swapping
    $design = [
        'version' => 2,
        'palette' => $palette,
    ];
    $recolored = PaletteResolver::applyPalette($design, [
        'primary' => '#1E3A8A', // Navy
        'secondary' => '#93C5FD',
    ]);
    expect($recolored['palette']['primary'])->toBe('#1E3A8A')
        ->and($recolored['palette']['secondary'])->toBe('#93C5FD')
        ->and($recolored['palette']['bg'])->toBe('#FAF7F2');

    // 3. Derive harmonious palette
    $derived = PaletteResolver::deriveHarmoniousPalette('#800020'); // Burgundy base
    expect($derived)->toHaveKeys(['bg', 'surface', 'primary', 'secondary', 'accent', 'ink', 'muted'])
        ->and($derived['primary'])->toBe('#800020')
        ->and(PaletteResolver::getContrastRatio($derived['ink'], $derived['bg']))->toBeGreaterThanOrEqual(4.5);
});

test('svg sanitizer whitelists safe SVG elements and strictly rejects hostile markup', function () {
    // 1. Safe SVG passes
    $safeSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><g fill="{{c1}}"><circle cx="50" cy="50" r="40"/><path d="M10,10 L90,90"/></g></svg>';
    expect(SvgSanitizer::isValid($safeSvg))->toBeTrue();
    $sanitized = SvgSanitizer::sanitize($safeSvg);
    expect($sanitized)->toContain('<circle');

    // 2. Reject script tag
    $maliciousScript = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><circle cx="10" cy="10" r="5"/></svg>';
    expect(SvgSanitizer::isValid($maliciousScript))->toBeFalse();
    expect(fn () => SvgSanitizer::sanitize($maliciousScript))->toThrow(InvalidArgumentException::class);

    // 3. Reject foreignObject
    $maliciousForeign = '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject width="100" height="100"><iframe src="http://evil.com"></iframe></foreignObject></svg>';
    expect(SvgSanitizer::isValid($maliciousForeign))->toBeFalse();
    expect(fn () => SvgSanitizer::sanitize($maliciousForeign))->toThrow(InvalidArgumentException::class);

    // 4. Reject inline event handlers (onmouseover, onload)
    $maliciousOnload = '<svg xmlns="http://www.w3.org/2000/svg"><rect x="0" y="0" width="10" height="10" onload="alert(1)"/></svg>';
    expect(SvgSanitizer::isValid($maliciousOnload))->toBeFalse();

    // 5. Reject style with external url()
    $maliciousUrl = '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0,0" style="fill:url(http://evil.com/x.svg#foo)"/></svg>';
    expect(SvgSanitizer::isValid($maliciousUrl))->toBeFalse();

    // 6. Reject use with external href
    $maliciousUse = '<svg xmlns="http://www.w3.org/2000/svg"><use href="https://attacker.com/exploit.svg#id"/></svg>';
    expect(SvgSanitizer::isValid($maliciousUse))->toBeFalse();
});

test('ornament registry loads and filters manifests and generates sanitized tinted SVGs', function () {
    $all = OrnamentRegistry::all();
    expect(count($all))->toBeGreaterThanOrEqual(10);

    $traditional = OrnamentRegistry::getBySet('traditional');
    expect(count($traditional))->toBeGreaterThanOrEqual(4);

    $corners = OrnamentRegistry::getByAnchor('corner');
    expect(count($corners))->toBeGreaterThanOrEqual(3);

    // Load and tint
    $tinted = OrnamentRegistry::loadTintedSvg('meskel-cross-01', [
        'c1' => '#800020',
        'c2' => '#D4AF37',
        'c3' => '#C5A059',
    ]);

    expect($tinted)->not->toBeNull()
        ->and($tinted)->toContain('#800020')
        ->and($tinted)->toContain('#D4AF37')
        ->and($tinted)->not->toContain('{{c1}}');
});

test('validator quality rules for system templates enforce margins, contrast, and element thresholds', function () {
    $goodSystemTemplate = [
        'version' => 2,
        'width' => 1080,
        'height' => 1528,
        'palette' => [
            'bg' => '#FAF7F2',
            'surface' => '#FFFFFF',
            'primary' => '#800020',
            'secondary' => '#D4AF37',
            'accent' => '#C5A059',
            'ink' => '#1F2937',
            'muted' => '#6B7280',
        ],
        'background' => [
            'type' => 'color',
            'value' => '@bg',
            'texture' => ['kind' => 'paper', 'opacity' => 0.08, 'seed' => 1],
            'vignette' => 0.1,
        ],
        'layers' => [
            // Non-text layer 1
            ['id' => 'b1', 'type' => 'border', 'style' => 'double-rule', 'stroke' => '@primary', 'x' => 0, 'y' => 0, 'width' => 1080, 'height' => 1528],
            // Non-text layer 2
            ['id' => 'o1', 'type' => 'ornament', 'assetId' => 'meskel-cross-01', 'x' => 490, 'y' => 120, 'width' => 100, 'height' => 100],
            // Non-text layer 3
            ['id' => 'p1', 'type' => 'pattern', 'assetId' => 'tilet-divider-01', 'x' => 140, 'y' => 400, 'width' => 800, 'height' => 50],
            // Text layer 1 (Display title, font 1, size 48)
            ['id' => 't1', 'type' => 'text', 'text' => 'Wedding Invitation', 'fontFamily' => 'Playfair Display', 'fontSize' => 48, 'fill' => '@primary', 'x' => 100, 'y' => 250, 'width' => 880, 'height' => 70],
            // Text layer 2 (Display names, font 2, size 36)
            ['id' => 't2', 'type' => 'text', 'text' => 'Dawit & Almaz', 'fontFamily' => 'Noto Serif Ethiopic', 'fontSize' => 36, 'fill' => '@primary', 'x' => 100, 'y' => 330, 'width' => 880, 'height' => 60],
            // Text layer 3 (Body details, font 2, size 24)
            ['id' => 't3', 'type' => 'text', 'text' => 'Join us at the Sheraton Addis on Sunday', 'fontFamily' => 'Noto Serif Ethiopic', 'fontSize' => 24, 'fill' => '@ink', 'x' => 100, 'y' => 500, 'width' => 880, 'height' => 50],
        ],
    ];

    // Passes all quality rules
    expect(fn () => DesignValidator::validateQualityRules($goodSystemTemplate))->not->toThrow(ValidationException::class);

    // Fail 1: Less than 3 non-text layers
    $fewNonText = $goodSystemTemplate;
    $fewNonText['layers'] = array_filter($fewNonText['layers'], fn ($l) => $l['id'] !== 'p1'); // Only 2 non-text
    expect(fn () => DesignValidator::validateQualityRules($fewNonText))->toThrow(ValidationException::class);

    // Fail 2: Safe margin violation (< 64px from edge)
    $unsafeMargin = $goodSystemTemplate;
    $unsafeMargin['layers'][3]['x'] = 10; // violating 64px left margin
    expect(fn () => DesignValidator::validateQualityRules($unsafeMargin))->toThrow(ValidationException::class);

    // Fail 3: Poor contrast ratio
    $badContrast = $goodSystemTemplate;
    // Set text fill to almost identical color to background
    $badContrast['layers'][5]['fill'] = '#F9F5EF';
    expect(fn () => DesignValidator::validateQualityRules($badContrast))->toThrow(ValidationException::class);
});

test('renderer determinism: identical input gives identical PNG hash, and palette swap recolors', function () {
    $design = [
        'version' => 2,
        'width' => 1080,
        'height' => 1528,
        'palette' => [
            'bg' => '#FAF7F2',
            'surface' => '#FFFFFF',
            'primary' => '#800020',
            'secondary' => '#D4AF37',
            'accent' => '#C5A059',
            'ink' => '#1F2937',
            'muted' => '#6B7280',
        ],
        'background' => [
            'type' => 'color',
            'value' => '@bg',
            'texture' => ['kind' => 'paper', 'opacity' => 0.08, 'seed' => 1],
            'vignette' => 0.1,
        ],
        'layers' => [
            [
                'id' => 'border-1',
                'type' => 'border',
                'style' => 'double-rule',
                'inset' => 40,
                'stroke' => '@primary',
                'strokeWidth' => 2,
                'x' => 0,
                'y' => 0,
                'width' => 1080,
                'height' => 1528,
            ],
            [
                'id' => 'ornament-1',
                'type' => 'ornament',
                'assetId' => 'meskel-cross-01',
                'tint' => ['c1' => '@primary', 'c2' => '@secondary', 'c3' => '@accent'],
                'x' => 490,
                'y' => 120,
                'width' => 100,
                'height' => 100,
            ],
            [
                'id' => 'text-1',
                'type' => 'text',
                'text' => 'Abebe & Chaltu',
                'curve' => 300,
                'x' => 240,
                'y' => 250,
                'width' => 600,
                'height' => 100,
                'fontSize' => 36,
                'fill' => '@primary',
            ],
            [
                'id' => 'pattern-1',
                'type' => 'pattern',
                'assetId' => 'tilet-divider-01',
                'x' => 140,
                'y' => 400,
                'width' => 800,
                'height' => 50,
            ],
        ],
    ];

    // Render twice with same input
    $hash1 = DesignRendererService::getHash($design, ['scale' => 0.5]);
    $hash2 = DesignRendererService::getHash($design, ['scale' => 0.5]);

    expect($hash1)->toBeString()
        ->and(strlen($hash1))->toBe(64)
        ->and($hash1)->toBe($hash2); // Determinism confirmed!

    // Swap palette
    $recoloredHash = DesignRendererService::getHash($design, [
        'scale' => 0.5,
        'palette' => [
            'bg' => '#18181B', // Dark mode
            'primary' => '#E5D0AC',
        ],
    ]);

    expect($recoloredHash)->toBeString()
        ->and(strlen($recoloredHash))->toBe(64)
        ->and($recoloredHash)->not->toBe($hash1); // Palette swap recolored!
});
