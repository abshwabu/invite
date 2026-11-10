<?php

namespace App\Templates\Suites;

use App\Models\Template;
use App\Services\DesignValidator;
use App\Services\OrnamentRegistry;
use App\Templates\Copy\ThankYouCopyLibrary;
use App\Templates\Fonts\FontPairingLibrary;
use App\Templates\Palettes\PaletteLibrary;
use Database\Seeders\ThankYouTemplateSeeder;

class ThankYouSuiteBuilder
{
    /**
     * Build a matching thank-you template specification and design JSON for a parent invitation.
     */
    public static function buildForInvitation(Template|array $invitation, string $locale = 'en', int $variant = 0, ?int $styleIndex = null): array
    {
        $spec = is_array($invitation) ? ($invitation['spec'] ?? $invitation) : ($invitation->spec ?? []);
        $invName = is_array($invitation) ? ($invitation['name'] ?? 'Invitation') : $invitation->name;
        $suiteKey = $spec['suite_key'] ?? (is_array($invitation) ? ($invitation['suite_key'] ?? 'suite') : $invitation->suite_key);
        $categorySlug = $spec['category'] ?? 'wedding';

        $paletteId = $spec['palette'] ?? 'garden-rose';
        $fontsId = $spec['fonts'] ?? 'classic-ceremonial';
        $textureKind = $spec['texture'] ?? 'paper';
        $topOrnament = $spec['ornaments']['top'] ?? 'floral-arch-wreath';
        $dividerOrnament = $spec['ornaments']['divider'] ?? 'flourish-divider-ornate';

        // Calmer ornament assignment for thank-you
        $anchorOrnament = match ($categorySlug) {
            'funeral' => in_array($topOrnament, ['cross-orthodox', 'crescent-star-islamic', 'bismillah-calligraphy'], true)
                ? $topOrnament
                : 'olive-branch-peace',
            'corporate' => in_array($topOrnament, ['badge-seal', 'line-grid-field', 'circle-rings'], true)
                ? $topOrnament
                : 'badge-seal',
            'graduation' => 'laurel-open',
            'birthday' => 'sparkle-trio',
            'baby-shower' => 'cloud-trio',
            default => $topOrnament,
        };

        $resolvedDivider = $dividerOrnament ?: 'flourish-divider-ornate';

        // Derive styleIndex and variant if not explicitly provided
        if ($styleIndex === null) {
            $keyIndex = array_search($suiteKey, ThankYouTemplateSeeder::SUITE_KEYS, true);
            if ($keyIndex !== false) {
                $styleIndex = $keyIndex % 5;
            } else {
                $styleIndex = abs(crc32($suiteKey)) % 5;
            }
        }

        if ($variant === 0) {
            $keyIndex = array_search($suiteKey, ThankYouTemplateSeeder::SUITE_KEYS, true);
            if ($keyIndex !== false) {
                $variant = ($keyIndex % 5) % 4;
            }
        }

        // Resolve bilingual copy
        $title = ThankYouCopyLibrary::get($categorySlug, 'title', $variant, $locale);
        $salutation = ThankYouCopyLibrary::get($categorySlug, 'salutation', $variant, $locale);
        $message = ThankYouCopyLibrary::get($categorySlug, 'message', $variant, $locale);
        $closing = ThankYouCopyLibrary::get($categorySlug, 'closing', $variant, $locale);

        // Resolve typography
        $fontRoles = FontPairingLibrary::resolveRoles($fontsId, $locale);
        $displayFont = $fontRoles['display'] ?? 'Cinzel';
        $bodyFont = $fontRoles['body'] ?? 'EB Garamond';
        $accentFont = $fontRoles['script-or-accent'] ?? 'Great Vibes';

        // Resolve palette
        $pal = PaletteLibrary::get($paletteId);
        $paletteData = $pal ?: [
            'bg' => '#FAF7F2',
            'surface' => '#FFFFFF',
            'primary' => '#800020',
            'secondary' => '#D4AF37',
            'accent' => '#C5A059',
            'ink' => '#1F2937',
            'muted' => '#6B7280',
        ];

        // Corner ornament resolution for styles with corners
        $cornerOrnament = match ($categorySlug) {
            'funeral' => 'olive-branch-corner',
            'religious' => 'tilet-corner-01',
            'corporate' => 'geo-triangles-corner',
            'graduation' => 'laurel-open',
            'birthday' => 'confetti-scatter-a',
            'baby-shower' => 'leaf-garland',
            'wedding', 'anniversary' => 'floral-corner-garden',
            default => 'minimal-corner',
        };

        // Build one of 5 distinct layout styles
        [$recipeName, $layers] = match ($styleIndex) {
            0 => static::buildArchWindowLayers($anchorOrnament, $resolvedDivider, $displayFont, $bodyFont, $accentFont, $title, $salutation, $message, $closing),
            1 => static::buildSplitBandAsymmetricLayers($anchorOrnament, $resolvedDivider, $displayFont, $bodyFont, $accentFont, $title, $salutation, $message, $closing),
            2 => static::buildHeaderBandLayers($anchorOrnament, $resolvedDivider, $displayFont, $bodyFont, $accentFont, $title, $salutation, $message, $closing),
            3 => static::buildMedallionLayers($anchorOrnament, $resolvedDivider, $displayFont, $bodyFont, $accentFont, $title, $salutation, $message, $closing),
            default => static::buildHorizonAsymmetricLayers($anchorOrnament, $resolvedDivider, $displayFont, $bodyFont, $accentFont, $title, $salutation, $message, $closing),
        };

        $design = [
            'version' => 2,
            'width' => 1080,
            'height' => 1528,
            'palette' => $paletteData,
            'background' => [
                'type' => 'color',
                'value' => '@bg',
                'texture' => [
                    'kind' => in_array($textureKind, ['none', 'paper', 'linen', 'grain', 'watercolor'], true) ? $textureKind : 'paper',
                    'opacity' => 0.08,
                ],
            ],
            'layers' => $layers,
        ];

        // Strict DesignValidator check
        $validatedDesign = DesignValidator::validate($design, false, true);

        // Extract ornament sets
        $ornamentSets = [];
        foreach ($validatedDesign['layers'] as $l) {
            if (in_array($l['type'] ?? '', ['ornament', 'pattern'], true) && !empty($l['assetId'])) {
                $item = OrnamentRegistry::find($l['assetId']);
                if ($item && !empty($item['set'])) {
                    $ornamentSets[$item['set']] = true;
                }
            }
        }

        $thankYouSpec = [
            'key' => "{$suiteKey}-thankyou",
            'suite_key' => $suiteKey,
            'category' => $categorySlug,
            'name_en' => "{$invName} Thank-You",
            'name_am' => ($spec['name_am'] ?? $invName) . ' ምስጋና',
            'recipe' => $recipeName,
            'palette' => $paletteId,
            'fonts' => $fontsId,
            'texture' => $textureKind,
            'ornaments' => [
                'top' => $anchorOrnament,
                'divider' => $resolvedDivider,
                'bottom' => 'double-rule-ornament',
            ],
            'tags' => array_values(array_unique(array_merge($spec['tags'] ?? [], ['thank-you', 'suite', 'gratitude']))),
            'needs_native_review' => true,
        ];

        return [
            'spec' => $thankYouSpec,
            'design' => $validatedDesign,
            'ornament_sets' => array_keys($ornamentSets),
        ];
    }

    /**
     * Style 0: Cathedral Arch Sanctuary
     */
    protected static function buildArchWindowLayers(
        string $anchorOrnament,
        string $resolvedDivider,
        string $displayFont,
        string $bodyFont,
        string $accentFont,
        string $title,
        string $salutation,
        string $message,
        string $closing
    ): array {
        return [
            'arch-window-calm',
            [
                // Outer arch panel shape
                [
                    'id' => 'shape-arch-panel',
                    'type' => 'shape',
                    'shape' => 'arch',
                    'x' => 112,
                    'y' => 120,
                    'width' => 856,
                    'height' => 1288,
                    'fill' => 'none',
                    'stroke' => '@secondary',
                    'strokeWidth' => 2,
                    'locked' => true,
                ],
                // Inner delicate frame
                [
                    'id' => 'border-inner',
                    'type' => 'shape',
                    'shape' => 'rect',
                    'x' => 136,
                    'y' => 220,
                    'width' => 808,
                    'height' => 1160,
                    'fill' => 'none',
                    'stroke' => '@primary',
                    'strokeWidth' => 1,
                    'opacity' => 0.45,
                    'locked' => true,
                ],
                // Crown ornament atop arch
                [
                    'id' => 'ornament-crown',
                    'type' => 'ornament',
                    'assetId' => $anchorOrnament,
                    'x' => 440,
                    'y' => 96,
                    'width' => 200,
                    'height' => 128,
                    'tint' => ['c1' => '@primary', 'c2' => '@secondary', 'c3' => '@accent'],
                    'opacity' => 0.9,
                ],
                // Title
                [
                    'id' => 'text-title',
                    'type' => 'text',
                    'x' => 144,
                    'y' => 270,
                    'width' => 792,
                    'height' => 100,
                    'text' => $title,
                    'fontFamily' => $displayFont,
                    'fontSize' => 72,
                    'fontWeight' => '700',
                    'fill' => '@primary',
                    'align' => 'center',
                    'autoFit' => true,
                ],
                // Salutation
                [
                    'id' => 'text-salutation',
                    'type' => 'text',
                    'x' => 144,
                    'y' => 400,
                    'width' => 792,
                    'height' => 50,
                    'text' => $salutation,
                    'fontFamily' => $accentFont,
                    'fontSize' => 34,
                    'fontWeight' => '500',
                    'fill' => '@ink',
                    'align' => 'center',
                ],
                // Message
                [
                    'id' => 'text-message',
                    'type' => 'text',
                    'x' => 144,
                    'y' => 480,
                    'width' => 792,
                    'height' => 310,
                    'text' => $message,
                    'fontFamily' => $bodyFont,
                    'fontSize' => 32,
                    'fontWeight' => '400',
                    'fill' => '@ink',
                    'align' => 'center',
                    'lineHeight' => 1.55,
                ],
                // Divider
                [
                    'id' => 'ornament-divider',
                    'type' => 'ornament',
                    'assetId' => $resolvedDivider,
                    'x' => 390,
                    'y' => 820,
                    'width' => 300,
                    'height' => 44,
                    'tint' => ['c1' => '@secondary', 'c2' => '@primary', 'c3' => '@accent'],
                    'opacity' => 0.85,
                ],
                // Closing
                [
                    'id' => 'text-closing',
                    'type' => 'text',
                    'x' => 144,
                    'y' => 890,
                    'width' => 792,
                    'height' => 50,
                    'text' => $closing,
                    'fontFamily' => $accentFont,
                    'fontSize' => 30,
                    'fontWeight' => '400',
                    'fill' => '@muted',
                    'align' => 'center',
                ],
                // Host Names
                [
                    'id' => 'text-hosts',
                    'type' => 'text',
                    'x' => 144,
                    'y' => 960,
                    'width' => 792,
                    'height' => 80,
                    'text' => '{{host_names}}',
                    'fontFamily' => $displayFont,
                    'fontSize' => 44,
                    'fontWeight' => '600',
                    'fill' => '@primary',
                    'align' => 'center',
                    'autoFit' => true,
                ],
                // Bottom rule
                [
                    'id' => 'ornament-bottom',
                    'type' => 'ornament',
                    'assetId' => 'double-rule-ornament',
                    'x' => 440,
                    'y' => 1070,
                    'width' => 200,
                    'height' => 36,
                    'tint' => ['c1' => '@secondary', 'c2' => '@primary', 'c3' => '@accent'],
                    'opacity' => 0.75,
                ],
            ]
        ];
    }

    /**
     * Style 1: Split-Band Asymmetric Layout
     */
    protected static function buildSplitBandAsymmetricLayers(
        string $anchorOrnament,
        string $resolvedDivider,
        string $displayFont,
        string $bodyFont,
        string $accentFont,
        string $title,
        string $salutation,
        string $message,
        string $closing
    ): array {
        return [
            'split-band-asymmetric',
            [
                // Outer architectural border
                [
                    'id' => 'border-main',
                    'type' => 'shape',
                    'shape' => 'rect',
                    'x' => 72,
                    'y' => 72,
                    'width' => 936,
                    'height' => 1384,
                    'stroke' => '@secondary',
                    'strokeWidth' => 1.5,
                    'fill' => 'none',
                    'locked' => true,
                ],
                // Left architectural side panel
                [
                    'id' => 'shape-left-panel',
                    'type' => 'shape',
                    'shape' => 'rect',
                    'x' => 72,
                    'y' => 72,
                    'width' => 280,
                    'height' => 1384,
                    'fill' => '@surface',
                    'stroke' => '@secondary',
                    'strokeWidth' => 1,
                    'locked' => true,
                ],
                // Vertical dividing rule
                [
                    'id' => 'shape-vertical-divider',
                    'type' => 'shape',
                    'shape' => 'rect',
                    'x' => 352,
                    'y' => 96,
                    'width' => 3,
                    'height' => 1336,
                    'fill' => '@primary',
                    'opacity' => 0.45,
                    'locked' => true,
                ],
                // Emblem in left band
                [
                    'id' => 'ornament-left-emblem',
                    'type' => 'ornament',
                    'assetId' => $anchorOrnament,
                    'x' => 112,
                    'y' => 140,
                    'width' => 200,
                    'height' => 160,
                    'tint' => ['c1' => '@primary', 'c2' => '@secondary', 'c3' => '@accent'],
                    'opacity' => 0.9,
                ],
                // Title (right side)
                [
                    'id' => 'text-title',
                    'type' => 'text',
                    'x' => 390,
                    'y' => 160,
                    'width' => 570,
                    'height' => 120,
                    'text' => $title,
                    'fontFamily' => $displayFont,
                    'fontSize' => 68,
                    'fontWeight' => '700',
                    'fill' => '@primary',
                    'align' => 'left',
                    'autoFit' => true,
                ],
                // Salutation (right side)
                [
                    'id' => 'text-salutation',
                    'type' => 'text',
                    'x' => 390,
                    'y' => 320,
                    'width' => 570,
                    'height' => 50,
                    'text' => $salutation,
                    'fontFamily' => $accentFont,
                    'fontSize' => 32,
                    'fontWeight' => '500',
                    'fill' => '@ink',
                    'align' => 'left',
                ],
                // Message (right side)
                [
                    'id' => 'text-message',
                    'type' => 'text',
                    'x' => 390,
                    'y' => 400,
                    'width' => 570,
                    'height' => 420,
                    'text' => $message,
                    'fontFamily' => $bodyFont,
                    'fontSize' => 30,
                    'fontWeight' => '400',
                    'fill' => '@ink',
                    'align' => 'left',
                    'lineHeight' => 1.6,
                ],
                // Divider (right side)
                [
                    'id' => 'ornament-divider',
                    'type' => 'ornament',
                    'assetId' => $resolvedDivider,
                    'x' => 390,
                    'y' => 860,
                    'width' => 260,
                    'height' => 38,
                    'tint' => ['c1' => '@secondary', 'c2' => '@primary', 'c3' => '@accent'],
                    'opacity' => 0.85,
                ],
                // Closing (right side)
                [
                    'id' => 'text-closing',
                    'type' => 'text',
                    'x' => 390,
                    'y' => 930,
                    'width' => 570,
                    'height' => 50,
                    'text' => $closing,
                    'fontFamily' => $accentFont,
                    'fontSize' => 30,
                    'fontWeight' => '400',
                    'fill' => '@muted',
                    'align' => 'left',
                ],
                // Host Names (right side)
                [
                    'id' => 'text-hosts',
                    'type' => 'text',
                    'x' => 390,
                    'y' => 1000,
                    'width' => 570,
                    'height' => 80,
                    'text' => '{{host_names}}',
                    'fontFamily' => $displayFont,
                    'fontSize' => 44,
                    'fontWeight' => '600',
                    'fill' => '@primary',
                    'align' => 'left',
                    'autoFit' => true,
                ],
                // Bottom rule (right side)
                [
                    'id' => 'ornament-bottom',
                    'type' => 'ornament',
                    'assetId' => 'double-rule-ornament',
                    'x' => 390,
                    'y' => 1100,
                    'width' => 180,
                    'height' => 32,
                    'tint' => ['c1' => '@secondary', 'c2' => '@primary', 'c3' => '@accent'],
                    'opacity' => 0.75,
                ],
            ]
        ];
    }

    /**
     * Style 2: Header Band Accent
     */
    protected static function buildHeaderBandLayers(
        string $anchorOrnament,
        string $resolvedDivider,
        string $displayFont,
        string $bodyFont,
        string $accentFont,
        string $title,
        string $salutation,
        string $message,
        string $closing
    ): array {
        return [
            'header-band-calm',
            [
                // Canvas outer border
                [
                    'id' => 'border-main',
                    'type' => 'shape',
                    'shape' => 'rect',
                    'x' => 72,
                    'y' => 72,
                    'width' => 936,
                    'height' => 1384,
                    'stroke' => '@secondary',
                    'strokeWidth' => 2,
                    'fill' => 'none',
                    'opacity' => 0.75,
                    'locked' => true,
                ],
                // Header accent box
                [
                    'id' => 'shape-header-box',
                    'type' => 'shape',
                    'shape' => 'rect',
                    'x' => 96,
                    'y' => 96,
                    'width' => 888,
                    'height' => 310,
                    'fill' => '@surface',
                    'stroke' => '@primary',
                    'strokeWidth' => 1.5,
                    'opacity' => 0.9,
                    'locked' => true,
                ],
                // Dividing rule under header
                [
                    'id' => 'shape-header-rule',
                    'type' => 'shape',
                    'shape' => 'rect',
                    'x' => 96,
                    'y' => 406,
                    'width' => 888,
                    'height' => 6,
                    'fill' => '@secondary',
                    'locked' => true,
                ],
                // Top ornament inside header box
                [
                    'id' => 'ornament-top',
                    'type' => 'ornament',
                    'assetId' => $anchorOrnament,
                    'x' => 440,
                    'y' => 120,
                    'width' => 200,
                    'height' => 120,
                    'tint' => ['c1' => '@primary', 'c2' => '@secondary', 'c3' => '@accent'],
                    'opacity' => 0.9,
                ],
                // Title
                [
                    'id' => 'text-title',
                    'type' => 'text',
                    'x' => 144,
                    'y' => 260,
                    'width' => 792,
                    'height' => 90,
                    'text' => $title,
                    'fontFamily' => $displayFont,
                    'fontSize' => 68,
                    'fontWeight' => '700',
                    'fill' => '@primary',
                    'align' => 'center',
                    'autoFit' => true,
                ],
                // Salutation
                [
                    'id' => 'text-salutation',
                    'type' => 'text',
                    'x' => 144,
                    'y' => 460,
                    'width' => 792,
                    'height' => 50,
                    'text' => $salutation,
                    'fontFamily' => $accentFont,
                    'fontSize' => 34,
                    'fontWeight' => '500',
                    'fill' => '@ink',
                    'align' => 'center',
                ],
                // Message
                [
                    'id' => 'text-message',
                    'type' => 'text',
                    'x' => 144,
                    'y' => 540,
                    'width' => 792,
                    'height' => 330,
                    'text' => $message,
                    'fontFamily' => $bodyFont,
                    'fontSize' => 32,
                    'fontWeight' => '400',
                    'fill' => '@ink',
                    'align' => 'center',
                    'lineHeight' => 1.55,
                ],
                // Divider
                [
                    'id' => 'ornament-divider',
                    'type' => 'ornament',
                    'assetId' => $resolvedDivider,
                    'x' => 390,
                    'y' => 900,
                    'width' => 300,
                    'height' => 44,
                    'tint' => ['c1' => '@secondary', 'c2' => '@primary', 'c3' => '@accent'],
                    'opacity' => 0.85,
                ],
                // Closing
                [
                    'id' => 'text-closing',
                    'type' => 'text',
                    'x' => 144,
                    'y' => 970,
                    'width' => 792,
                    'height' => 50,
                    'text' => $closing,
                    'fontFamily' => $accentFont,
                    'fontSize' => 30,
                    'fontWeight' => '400',
                    'fill' => '@muted',
                    'align' => 'center',
                ],
                // Host Names
                [
                    'id' => 'text-hosts',
                    'type' => 'text',
                    'x' => 144,
                    'y' => 1040,
                    'width' => 792,
                    'height' => 80,
                    'text' => '{{host_names}}',
                    'fontFamily' => $displayFont,
                    'fontSize' => 44,
                    'fontWeight' => '600',
                    'fill' => '@primary',
                    'align' => 'center',
                    'autoFit' => true,
                ],
                // Bottom rule
                [
                    'id' => 'ornament-bottom',
                    'type' => 'ornament',
                    'assetId' => 'double-rule-ornament',
                    'x' => 440,
                    'y' => 1150,
                    'width' => 200,
                    'height' => 36,
                    'tint' => ['c1' => '@secondary', 'c2' => '@primary', 'c3' => '@accent'],
                    'opacity' => 0.75,
                ],
            ]
        ];
    }

    /**
     * Style 3: Medallion Focus Centerpiece
     */
    protected static function buildMedallionLayers(
        string $anchorOrnament,
        string $resolvedDivider,
        string $displayFont,
        string $bodyFont,
        string $accentFont,
        string $title,
        string $salutation,
        string $message,
        string $closing
    ): array {
        return [
            'medallion-focus',
            [
                // Outer border
                [
                    'id' => 'border-main',
                    'type' => 'shape',
                    'shape' => 'rect',
                    'x' => 96,
                    'y' => 96,
                    'width' => 888,
                    'height' => 1336,
                    'stroke' => '@secondary',
                    'strokeWidth' => 2,
                    'fill' => 'none',
                    'locked' => true,
                ],
                // Circular medallion shape
                [
                    'id' => 'shape-medallion',
                    'type' => 'shape',
                    'shape' => 'circle',
                    'x' => 350,
                    'y' => 120,
                    'width' => 380,
                    'height' => 380,
                    'fill' => '@surface',
                    'stroke' => '@primary',
                    'strokeWidth' => 2.5,
                    'locked' => true,
                ],
                // Medallion inner accent ring
                [
                    'id' => 'shape-medallion-inner',
                    'type' => 'shape',
                    'shape' => 'circle',
                    'x' => 370,
                    'y' => 140,
                    'width' => 340,
                    'height' => 340,
                    'fill' => 'none',
                    'stroke' => '@secondary',
                    'strokeWidth' => 1,
                    'opacity' => 0.7,
                    'locked' => true,
                ],
                // Emblem inside medallion
                [
                    'id' => 'ornament-top',
                    'type' => 'ornament',
                    'assetId' => $anchorOrnament,
                    'x' => 440,
                    'y' => 170,
                    'width' => 200,
                    'height' => 130,
                    'tint' => ['c1' => '@primary', 'c2' => '@secondary', 'c3' => '@accent'],
                    'opacity' => 0.9,
                ],
                // Title
                [
                    'id' => 'text-title',
                    'type' => 'text',
                    'x' => 144,
                    'y' => 320,
                    'width' => 792,
                    'height' => 90,
                    'text' => $title,
                    'fontFamily' => $displayFont,
                    'fontSize' => 58,
                    'fontWeight' => '700',
                    'fill' => '@primary',
                    'align' => 'center',
                    'autoFit' => true,
                ],
                // Salutation
                [
                    'id' => 'text-salutation',
                    'type' => 'text',
                    'x' => 144,
                    'y' => 550,
                    'width' => 792,
                    'height' => 50,
                    'text' => $salutation,
                    'fontFamily' => $accentFont,
                    'fontSize' => 34,
                    'fontWeight' => '500',
                    'fill' => '@ink',
                    'align' => 'center',
                ],
                // Message
                [
                    'id' => 'text-message',
                    'type' => 'text',
                    'x' => 144,
                    'y' => 630,
                    'width' => 792,
                    'height' => 310,
                    'text' => $message,
                    'fontFamily' => $bodyFont,
                    'fontSize' => 32,
                    'fontWeight' => '400',
                    'fill' => '@ink',
                    'align' => 'center',
                    'lineHeight' => 1.55,
                ],
                // Divider
                [
                    'id' => 'ornament-divider',
                    'type' => 'ornament',
                    'assetId' => $resolvedDivider,
                    'x' => 390,
                    'y' => 970,
                    'width' => 300,
                    'height' => 44,
                    'tint' => ['c1' => '@secondary', 'c2' => '@primary', 'c3' => '@accent'],
                    'opacity' => 0.85,
                ],
                // Closing
                [
                    'id' => 'text-closing',
                    'type' => 'text',
                    'x' => 144,
                    'y' => 1040,
                    'width' => 792,
                    'height' => 50,
                    'text' => $closing,
                    'fontFamily' => $accentFont,
                    'fontSize' => 30,
                    'fontWeight' => '400',
                    'fill' => '@muted',
                    'align' => 'center',
                ],
                // Host Names
                [
                    'id' => 'text-hosts',
                    'type' => 'text',
                    'x' => 144,
                    'y' => 1110,
                    'width' => 792,
                    'height' => 80,
                    'text' => '{{host_names}}',
                    'fontFamily' => $displayFont,
                    'fontSize' => 44,
                    'fontWeight' => '600',
                    'fill' => '@primary',
                    'align' => 'center',
                    'autoFit' => true,
                ],
                // Bottom rule
                [
                    'id' => 'ornament-bottom',
                    'type' => 'ornament',
                    'assetId' => 'double-rule-ornament',
                    'x' => 440,
                    'y' => 1210,
                    'width' => 200,
                    'height' => 36,
                    'tint' => ['c1' => '@secondary', 'c2' => '@primary', 'c3' => '@accent'],
                    'opacity' => 0.75,
                ],
            ]
        ];
    }

    /**
     * Style 4: Modern Horizon & Asymmetric Accents
     */
    protected static function buildHorizonAsymmetricLayers(
        string $anchorOrnament,
        string $resolvedDivider,
        string $displayFont,
        string $bodyFont,
        string $accentFont,
        string $title,
        string $salutation,
        string $message,
        string $closing
    ): array {
        return [
            'horizon-asymmetric',
            [
                // Outer architectural border
                [
                    'id' => 'border-main',
                    'type' => 'shape',
                    'shape' => 'rect',
                    'x' => 96,
                    'y' => 96,
                    'width' => 888,
                    'height' => 1336,
                    'stroke' => '@secondary',
                    'strokeWidth' => 1.5,
                    'fill' => 'none',
                    'opacity' => 0.6,
                    'locked' => true,
                ],
                // Top-left corner motif
                [
                    'id' => 'ornament-corner-tl',
                    'type' => 'ornament',
                    'assetId' => $anchorOrnament,
                    'x' => 112,
                    'y' => 112,
                    'width' => 180,
                    'height' => 180,
                    'tint' => ['c1' => '@primary', 'c2' => '@secondary', 'c3' => '@accent'],
                    'opacity' => 0.85,
                ],
                // Asymmetric Title
                [
                    'id' => 'text-title',
                    'type' => 'text',
                    'x' => 320,
                    'y' => 150,
                    'width' => 624,
                    'height' => 120,
                    'text' => $title,
                    'fontFamily' => $displayFont,
                    'fontSize' => 72,
                    'fontWeight' => '700',
                    'fill' => '@primary',
                    'align' => 'left',
                    'autoFit' => true,
                ],
                // Horizontal divider line
                [
                    'id' => 'shape-horizon-line',
                    'type' => 'shape',
                    'shape' => 'rect',
                    'x' => 120,
                    'y' => 340,
                    'width' => 840,
                    'height' => 4,
                    'fill' => '@secondary',
                    'locked' => true,
                ],
                // Left vertical accent stripe
                [
                    'id' => 'shape-left-accent',
                    'type' => 'shape',
                    'shape' => 'rect',
                    'x' => 120,
                    'y' => 380,
                    'width' => 4,
                    'height' => 740,
                    'fill' => '@primary',
                    'opacity' => 0.4,
                    'locked' => true,
                ],
                // Salutation (left-aligned)
                [
                    'id' => 'text-salutation',
                    'type' => 'text',
                    'x' => 150,
                    'y' => 410,
                    'width' => 790,
                    'height' => 50,
                    'text' => $salutation,
                    'fontFamily' => $accentFont,
                    'fontSize' => 34,
                    'fontWeight' => '500',
                    'fill' => '@ink',
                    'align' => 'left',
                ],
                // Message (left-aligned)
                [
                    'id' => 'text-message',
                    'type' => 'text',
                    'x' => 150,
                    'y' => 490,
                    'width' => 790,
                    'height' => 340,
                    'text' => $message,
                    'fontFamily' => $bodyFont,
                    'fontSize' => 32,
                    'fontWeight' => '400',
                    'fill' => '@ink',
                    'align' => 'left',
                    'lineHeight' => 1.6,
                ],
                // Divider
                [
                    'id' => 'ornament-divider',
                    'type' => 'ornament',
                    'assetId' => $resolvedDivider,
                    'x' => 150,
                    'y' => 860,
                    'width' => 280,
                    'height' => 40,
                    'tint' => ['c1' => '@secondary', 'c2' => '@primary', 'c3' => '@accent'],
                    'opacity' => 0.85,
                ],
                // Closing (left-aligned)
                [
                    'id' => 'text-closing',
                    'type' => 'text',
                    'x' => 150,
                    'y' => 930,
                    'width' => 790,
                    'height' => 50,
                    'text' => $closing,
                    'fontFamily' => $accentFont,
                    'fontSize' => 30,
                    'fontWeight' => '400',
                    'fill' => '@muted',
                    'align' => 'left',
                ],
                // Host Names (left-aligned)
                [
                    'id' => 'text-hosts',
                    'type' => 'text',
                    'x' => 150,
                    'y' => 1000,
                    'width' => 790,
                    'height' => 80,
                    'text' => '{{host_names}}',
                    'fontFamily' => $displayFont,
                    'fontSize' => 44,
                    'fontWeight' => '600',
                    'fill' => '@primary',
                    'align' => 'left',
                    'autoFit' => true,
                ],
                // Bottom-right decorative corner
                [
                    'id' => 'ornament-corner-br',
                    'type' => 'ornament',
                    'assetId' => $anchorOrnament,
                    'x' => 740,
                    'y' => 1140,
                    'width' => 200,
                    'height' => 200,
                    'flipX' => true,
                    'flipY' => true,
                    'tint' => ['c1' => '@primary', 'c2' => '@secondary', 'c3' => '@accent'],
                    'opacity' => 0.85,
                ],
            ]
        ];
    }
}
