<?php

use App\Services\DesignValidator;
use App\Templates\Copy\CopyLibrary;
use App\Templates\Fonts\FontPairingLibrary;
use App\Templates\Palettes\PaletteLibrary;
use App\Templates\Recipes\RecipeRegistry;
use App\Templates\Recipes\RecipeSpec;

test('palette library has at least 36 contrast-verified palettes with all 7 roles', function () {
    $palettes = PaletteLibrary::all();

    expect(count($palettes))->toBeGreaterThanOrEqual(36);

    $errors = PaletteLibrary::validateContrastRules();
    expect($errors)->toBeEmpty();

    $requiredRoles = ['bg', 'surface', 'primary', 'secondary', 'accent', 'ink', 'muted'];
    foreach ($palettes as $id => $pal) {
        foreach ($requiredRoles as $role) {
            expect(array_key_exists($role, $pal))->toBeTrue("Palette [{$id}] is missing role [{$role}]");
            expect($pal[$role])->toStartWith('#', "Palette [{$id}] role [{$role}] must be a hex color");
        }
    }
});

test('font pairing library has at least 16 pairings satisfying all 5 contract roles and referencing existing fonts', function () {
    $pairings = FontPairingLibrary::all();

    expect(count($pairings))->toBeGreaterThanOrEqual(16);

    $requiredRoles = ['display', 'script-or-accent', 'body', 'ethiopic-display', 'ethiopic-body'];
    foreach ($pairings as $id => $pairing) {
        foreach ($requiredRoles as $role) {
            expect(array_key_exists($role, $pairing))->toBeTrue("Font pairing [{$id}] is missing role [{$role}]");
        }
    }

    $fontErrors = FontPairingLibrary::validateFonts();
    expect($fontErrors)->toBeEmpty();
});

test('font pairing resolution adapts correctly for English and Amharic locales', function () {
    $resolvedEn = FontPairingLibrary::resolveRoles('classic-ceremonial', 'en');
    expect($resolvedEn['display'])->toBe('Cinzel');
    expect($resolvedEn['body'])->toBe('EB Garamond');

    $resolvedAm = FontPairingLibrary::resolveRoles('classic-ceremonial', 'am');
    expect($resolvedAm['display'])->toBe('Abyssinica SIL');
    expect($resolvedAm['body'])->toBe('Noto Serif Ethiopic');
});

test('copy library covers all 11 categories with 5 roles, 3 variants each, and exports native review document', function () {
    $categories = CopyLibrary::CATEGORIES;
    expect(count($categories))->toBe(11);

    $roles = CopyLibrary::ROLES;
    expect(count($roles))->toBe(5);

    $allCopy = CopyLibrary::all();

    foreach ($categories as $cat) {
        expect(array_key_exists($cat, $allCopy))->toBeTrue("Copy library missing category [{$cat}]");

        foreach ($roles as $role) {
            expect(array_key_exists($role, $allCopy[$cat]))->toBeTrue("Category [{$cat}] missing role [{$role}]");
            $variants = $allCopy[$cat][$role];
            expect(count($variants))->toBeGreaterThanOrEqual(3, "Category [{$cat}] role [{$role}] must have at least 3 variants");

            foreach ($variants as $idx => $v) {
                expect($v)->toHaveKey('en');
                expect($v)->toHaveKey('am');
                expect($v)->toHaveKey('needs_native_review');
                expect($v['needs_native_review'])->toBeTrue();
                expect(empty($v['en']))->toBeFalse();
                expect(empty($v['am']))->toBeFalse();
            }
        }
    }

    $reviewDocPath = CopyLibrary::exportReviewDocument();
    expect(file_exists($reviewDocPath))->toBeTrue();
    $content = file_get_contents($reviewDocPath);
    expect($content)->toContain('# Amharic Copy Library Review Document');
    expect($content)->toContain('needs_native_review: true');
    expect($content)->toContain('Category: Wedding');
    expect($content)->toContain('Category: Religious Ethiopian');
});

test('recipe registry registers exactly 12 layout recipes', function () {
    $recipes = RecipeRegistry::all();

    expect(count($recipes))->toBe(12);

    $expectedIds = [
        'framed-center',
        'arch-window',
        'split-band',
        'corner-florals',
        'poster-stack',
        'medallion',
        'ticket',
        'wash',
        'horizon',
        'collage',
        'band',
        'tall-scroll',
    ];

    foreach ($expectedIds as $id) {
        expect(RecipeRegistry::has($id))->toBeTrue("Missing recipe [{$id}]");
        $inst = RecipeRegistry::get($id);
        expect($inst->getId())->toBe($id);
        expect(empty($inst->getName()))->toBeFalse();
        expect(empty($inst->getDescription()))->toBeFalse();
    }
});

test('all 12 layout recipes produce valid v2 designs passing strict quality rules for English', function () {
    $recipes = RecipeRegistry::all();

    foreach ($recipes as $id => $recipe) {
        $spec = new RecipeSpec([
            'category' => 'wedding',
            'locale' => 'en',
            'paletteId' => 'garden-rose',
            'pairingId' => 'classic-ceremonial',
        ]);

        $design = $recipe->build($spec);

        // Strict v2 schema + quality validation
        $validated = DesignValidator::validate($design, false, true);
        expect($validated)->toBeArray();
        expect($validated['version'])->toBe(2);
        expect($validated['width'])->toBe(1080);
        expect($validated['height'])->toBe(1528);

        // Quality rule checks
        $nonText = array_filter($validated['layers'], fn ($l) => ($l['type'] ?? '') !== 'text');
        expect(count($nonText))->toBeGreaterThanOrEqual(3, "Recipe [{$id}] must have >= 3 non-text layers");

        $textLayers = array_filter($validated['layers'], fn ($l) => ($l['type'] ?? '') === 'text');
        $fontFamilies = array_unique(array_column($textLayers, 'fontFamily'));
        expect(count($fontFamilies))->toBeGreaterThanOrEqual(2, "Recipe [{$id}] must use >= 2 font families");

        $fontSizes = array_unique(array_map(fn ($l) => (string) round($l['fontSize'], 1), $textLayers));
        expect(count($fontSizes))->toBeGreaterThanOrEqual(3, "Recipe [{$id}] must use >= 3 font sizes");
    }
});

test('all 12 layout recipes produce valid v2 designs passing strict quality rules for long Amharic text', function () {
    $recipes = RecipeRegistry::all();

    foreach ($recipes as $id => $recipe) {
        $spec = new RecipeSpec([
            'category' => 'wedding',
            'locale' => 'am',
            'paletteId' => 'habesha-white-gold-green-red',
            'pairingId' => 'regal-heritage',
            'title' => 'የክብርት ወይዘሮ ሳራ እና የአቶ ታደሰ ወልደማርያም የሰርግ እና የጋብቻ በዓል',
        ]);

        $design = $recipe->build($spec);

        // Strict validation with quality rules
        $validated = DesignValidator::validate($design, false, true);
        expect($validated)->toBeArray();

        // Check Ethiopic typography rules
        $textLayers = array_filter($validated['layers'], fn ($l) => ($l['type'] ?? '') === 'text');
        foreach ($textLayers as $tl) {
            expect($tl['lineHeight'])->toBeGreaterThanOrEqual(1.35, "Amharic text layer [{$tl['id']}] must have lineHeight >= 1.35");
            expect($tl['letterSpacing'])->toBe(0.0, "Amharic text layer [{$tl['id']}] must have letterSpacing 0");
        }
    }
});

test('12-card recipe contact sheet image exists and is non-empty', function () {
    $sheetPath = storage_path('app/recipe-sheets/recipes-12.png');
    expect(file_exists($sheetPath))->toBeTrue();
    expect(filesize($sheetPath))->toBeGreaterThan(50000); // At least 50KB
});
