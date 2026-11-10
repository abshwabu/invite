<?php

namespace App\Services;

use Illuminate\Support\Str;

class DesignUpgrader
{
    /**
     * Upgrade a design from v1 to v2 if necessary.
     */
    public static function upgrade(array $design): array
    {
        $version = (int) ($design['version'] ?? 1);
        if ($version >= 2) {
            // Already v2, just ensure required defaults exist
            if (! isset($design['palette']) || ! is_array($design['palette'])) {
                $design['palette'] = PaletteResolver::DEFAULT_PALETTE;
            }
            if (! isset($design['background']['texture'])) {
                $design['background']['texture'] = ['kind' => 'none', 'opacity' => 0.08, 'seed' => 1];
            }
            if (! isset($design['background']['vignette'])) {
                $design['background']['vignette'] = 0;
            }

            return $design;
        }

        // Determine background and color scheme
        $bgVal = $design['background']['value'] ?? '#FAF7F2';
        $isDark = false;
        if (is_string($bgVal) && str_starts_with(trim($bgVal), '#')) {
            $isDark = PaletteResolver::getLuminance($bgVal) < 0.35;
        }

        if ($isDark) {
            $palette = [
                'bg' => is_string($bgVal) ? $bgVal : '#18181B',
                'surface' => '#27272A',
                'primary' => '#D4AF37',
                'secondary' => '#E5D0AC',
                'accent' => '#C5A059',
                'ink' => '#F9FAFB',
                'muted' => '#9CA3AF',
            ];
        } else {
            $palette = [
                'bg' => is_string($bgVal) ? $bgVal : '#FAF7F2',
                'surface' => '#FFFFFF',
                'primary' => '#800020',
                'secondary' => '#D4AF37',
                'accent' => '#C5A059',
                'ink' => '#1F2937',
                'muted' => '#6B7280',
            ];
        }

        $v2Background = [
            'type' => $design['background']['type'] ?? 'color',
            'value' => $design['background']['value'] ?? '@bg',
            'texture' => [
                'kind' => 'none',
                'opacity' => 0.08,
                'seed' => 1,
            ],
            'vignette' => 0,
        ];

        if (isset($design['layers']) && ! is_array($design['layers'])) {
            $v2Layers = $design['layers'];
        } else {
            $v2Layers = [];
            $layers = is_array($design['layers'] ?? null) ? $design['layers'] : [];
            foreach ($layers as $index => $layer) {
                if (! is_array($layer)) {
                    continue;
                }

                $id = $layer['id'] ?? (string) Str::uuid();
                $type = $layer['type'] ?? 'text';

                $base = [
                    'id' => (string) $id,
                    'name' => $layer['name'] ?? ucfirst($type) . ' ' . ($index + 1),
                    'type' => $type,
                    'x' => (float) ($layer['x'] ?? 0),
                    'y' => (float) ($layer['y'] ?? 0),
                    'width' => (float) ($layer['width'] ?? 200),
                    'height' => (float) ($layer['height'] ?? 100),
                    'rotation' => (float) ($layer['rotation'] ?? 0),
                    'opacity' => isset($layer['opacity']) ? (float) $layer['opacity'] : 1.0,
                    'locked' => (bool) ($layer['locked'] ?? false),
                    'flipX' => (bool) ($layer['flipX'] ?? false),
                    'flipY' => (bool) ($layer['flipY'] ?? false),
                    'blendMode' => $layer['blendMode'] ?? 'normal',
                ];

                if ($type === 'text') {
                    $base['text'] = (string) ($layer['text'] ?? '');
                    if (isset($layer['i18n'])) {
                        $base['i18n'] = $layer['i18n'];
                    }
                    $base['fontFamily'] = $layer['fontFamily'] ?? 'Noto Serif Ethiopic';
                    $base['fontSize'] = (float) ($layer['fontSize'] ?? 28);
                    $base['fontWeight'] = (int) ($layer['fontWeight'] ?? 400);
                    $base['fontStyle'] = $layer['fontStyle'] ?? 'normal';
                    $base['fill'] = $layer['fill'] ?? '@ink';
                    $base['align'] = $layer['align'] ?? 'center';
                    $base['verticalAlign'] = $layer['verticalAlign'] ?? 'top';
                    $base['lineHeight'] = (float) ($layer['lineHeight'] ?? 1.3);
                    $base['letterSpacing'] = (float) ($layer['letterSpacing'] ?? 0);
                    $base['curve'] = (float) ($layer['curve'] ?? 0);
                    $base['stroke'] = $layer['stroke'] ?? null;
                    $base['strokeWidth'] = (float) ($layer['strokeWidth'] ?? 0);
                    $base['shadow'] = $layer['shadow'] ?? null;
                    $base['autoFit'] = $layer['autoFit'] ?? null;
                } elseif ($type === 'shape') {
                    $base['shape'] = $layer['shape'] ?? 'rect';
                    $base['fill'] = $layer['fill'] ?? '@primary';
                    $base['stroke'] = $layer['stroke'] ?? null;
                    $base['strokeWidth'] = (float) ($layer['strokeWidth'] ?? 0);
                    $base['cornerRadius'] = $layer['cornerRadius'] ?? 0;
                } elseif ($type === 'image') {
                    $base['src'] = $layer['src'] ?? '';
                    $base['mask'] = $layer['mask'] ?? 'none';
                    $base['objectFit'] = $layer['objectFit'] ?? 'cover';
                    $base['filter'] = $layer['filter'] ?? null;
                } elseif ($type === 'ornament') {
                    $base['assetId'] = $layer['assetId'] ?? 'meskel-cross-01';
                    $base['tint'] = $layer['tint'] ?? [
                        'c1' => '@primary',
                        'c2' => '@secondary',
                        'c3' => '@accent',
                    ];
                } elseif ($type === 'border') {
                    $base['style'] = $layer['style'] ?? 'single-rule';
                    $base['inset'] = (float) ($layer['inset'] ?? 40);
                    $base['stroke'] = $layer['stroke'] ?? '@primary';
                    $base['strokeWidth'] = (float) ($layer['strokeWidth'] ?? 2);
                } elseif ($type === 'pattern') {
                    $base['assetId'] = $layer['assetId'] ?? 'tilet-corner-01';
                    $base['tint'] = $layer['tint'] ?? [];
                } elseif ($type === 'group') {
                    $base['children'] = $layer['children'] ?? [];
                }

                $v2Layers[] = $base;
            }
        }

        return [
            'version' => 2,
            'width' => (int) ($design['width'] ?? 1080),
            'height' => (int) ($design['height'] ?? 1528),
            'palette' => $palette,
            'background' => $v2Background,
            'layers' => $v2Layers,
        ];
    }
}
