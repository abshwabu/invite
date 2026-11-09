<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;

class DesignValidator
{
    public const SUPPORTED_PLACEHOLDERS = [
        '{{guest_name}}',
        '{{event_title}}',
        '{{date}}',
        '{{time}}',
        '{{venue}}',
        '{{host_names}}',
    ];

    public const SUPPORTED_BACKGROUND_TYPES = ['color', 'gradient', 'image', 'pattern'];
    public const SUPPORTED_TEXTURE_KINDS = ['none', 'paper', 'linen', 'grain', 'watercolor'];

    public const SUPPORTED_LAYER_TYPES = [
        'text',
        'ornament',
        'border',
        'shape',
        'image',
        'pattern',
        'group',
    ];

    public const SUPPORTED_BORDER_STYLES = [
        'double-rule',
        'single-rule',
        'ornate-corners',
        'dotted',
        'scalloped',
        'tilet-band',
    ];

    public const SUPPORTED_SHAPES = [
        'rect',
        'circle',
        'ellipse',
        'arch',
        'line',
        'polygon',
        'star',
        'ribbon',
    ];

    public const SUPPORTED_MASKS = [
        'none',
        'rect',
        'circle',
        'arch',
        'oval',
        'rounded',
    ];

    public const REQUIRED_PALETTE_ROLES = [
        'bg',
        'surface',
        'primary',
        'secondary',
        'accent',
        'ink',
        'muted',
    ];

    /**
     * Validate the design JSON structure according to the contract (v2 or v1 readable).
     *
     * @param  array<string, mixed>|string  $design
     * @param  bool  $autoUpgrade  Whether to automatically upgrade v1 to v2
     * @param  bool  $validateQualityRules  Whether to run quality checks for system templates
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function validate(array|string $design, bool $autoUpgrade = false, bool $validateQualityRules = false): array
    {
        if (is_string($design)) {
            try {
                $design = json_decode($design, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw ValidationException::withMessages([
                    'design_json' => 'Design must be a valid JSON string: ' . $e->getMessage(),
                ]);
            }
        }

        if (! is_array($design)) {
            throw ValidationException::withMessages([
                'design_json' => 'Design JSON root must be an object.',
            ]);
        }

        $errors = [];

        // 1. Version check
        $version = $design['version'] ?? 1;
        if (! is_int($version) || $version < 1) {
            $errors['version'] = 'Design version must be a positive integer (e.g. 1 or 2).';
        }

        if ($autoUpgrade && $version === 1) {
            $design = DesignUpgrader::upgrade($design);
            $version = 2;
        }

        // 2. Width & Height
        $width = $design['width'] ?? null;
        if (! is_numeric($width) || $width < 100 || $width > 6000) {
            $errors['width'] = 'Design width must be between 100 and 6000 pixels.';
        }

        $height = $design['height'] ?? null;
        if (! is_numeric($height) || $height < 100 || $height > 6000) {
            $errors['height'] = 'Design height must be between 100 and 6000 pixels.';
        }

        // 3. Background
        if (! isset($design['background']) || ! is_array($design['background'])) {
            $errors['background'] = 'Design background must be an object with type and value.';
        } else {
            $bgType = $design['background']['type'] ?? null;
            $allowedBgTypes = $version >= 2 ? self::SUPPORTED_BACKGROUND_TYPES : ['color', 'image', 'gradient'];
            if (! in_array($bgType, $allowedBgTypes, true)) {
                $errors['background.type'] = 'Background type must be one of: ' . implode(', ', $allowedBgTypes);
            }

            if (! isset($design['background']['value']) || (is_string($design['background']['value']) && trim($design['background']['value']) === '')) {
                $errors['background.value'] = 'Background value is required.';
            }

            if ($version >= 2 && isset($design['background']['texture'])) {
                $tKind = $design['background']['texture']['kind'] ?? 'none';
                if (! in_array($tKind, self::SUPPORTED_TEXTURE_KINDS, true)) {
                    $errors['background.texture.kind'] = 'Texture kind must be one of: none, paper, linen, grain, watercolor.';
                }
            }
        }

        // 4. Palette (v2 only)
        if ($version >= 2) {
            if (! isset($design['palette']) || ! is_array($design['palette'])) {
                $errors['palette'] = 'Design v2 requires a palette object containing color roles.';
            } else {
                foreach (self::REQUIRED_PALETTE_ROLES as $role) {
                    if (! isset($design['palette'][$role]) || ! is_string($design['palette'][$role])) {
                        $errors["palette.{$role}"] = "Palette requires role '{$role}' with a color string.";
                    }
                }
            }
        }

        // 5. Layers
        if (! isset($design['layers']) || ! is_array($design['layers'])) {
            $errors['layers'] = 'Design layers must be an array.';
        } else {
            $allowedLayerTypes = $version >= 2 ? self::SUPPORTED_LAYER_TYPES : ['text', 'image', 'shape'];

            foreach ($design['layers'] as $index => $layer) {
                if (! is_array($layer)) {
                    $errors["layers.{$index}"] = "Layer at index {$index} must be an object.";
                    continue;
                }

                $layerId = $layer['id'] ?? null;
                if (! $layerId || ! is_string($layerId)) {
                    $errors["layers.{$index}.id"] = "Layer at index {$index} must have a unique string id.";
                }

                $layerType = $layer['type'] ?? null;
                if (! in_array($layerType, $allowedLayerTypes, true)) {
                    $errors["layers.{$index}.type"] = "Layer at index {$index} has invalid type '{$layerType}'.";
                    continue;
                }

                // Geometric checks
                if (! isset($layer['x']) || ! is_numeric($layer['x'])) {
                    $errors["layers.{$index}.x"] = "Layer {$layerId} missing numerical x coordinate.";
                }
                if (! isset($layer['y']) || ! is_numeric($layer['y'])) {
                    $errors["layers.{$index}.y"] = "Layer {$layerId} missing numerical y coordinate.";
                }
                if (! isset($layer['width']) || ! is_numeric($layer['width']) || $layer['width'] < 0) {
                    $errors["layers.{$index}.width"] = "Layer {$layerId} must have non-negative width.";
                }
                if (! isset($layer['height']) || ! is_numeric($layer['height']) || $layer['height'] < 0) {
                    $errors["layers.{$index}.height"] = "Layer {$layerId} must have non-negative height.";
                }

                // Type-specific checks
                if ($layerType === 'text') {
                    $hasText = isset($layer['text']) && is_string($layer['text']);
                    $hasI18n = isset($layer['i18n']) && is_array($layer['i18n']);
                    if (! $hasText && ! $hasI18n) {
                        $errors["layers.{$index}.text"] = "Text layer {$layerId} requires a text property.";
                    }
                } elseif ($layerType === 'ornament') {
                    if (empty($layer['assetId']) || ! is_string($layer['assetId'])) {
                        $errors["layers.{$index}.assetId"] = "Ornament layer {$layerId} requires a non-empty assetId.";
                    }
                } elseif ($layerType === 'border') {
                    $bStyle = $layer['style'] ?? null;
                    if (! in_array($bStyle, self::SUPPORTED_BORDER_STYLES, true)) {
                        $errors["layers.{$index}.style"] = "Border layer {$layerId} style must be one of: " . implode(', ', self::SUPPORTED_BORDER_STYLES);
                    }
                } elseif ($layerType === 'shape') {
                    $shape = $layer['shape'] ?? null;
                    $allowedShapes = $version >= 2 ? self::SUPPORTED_SHAPES : ['rect', 'circle', 'line'];
                    if (! in_array($shape, $allowedShapes, true)) {
                        $errors["layers.{$index}.shape"] = "Shape layer {$layerId} shape must be one of: " . implode(', ', $allowedShapes);
                    }
                } elseif ($layerType === 'image') {
                    if (empty($layer['src']) || ! is_string($layer['src'])) {
                        $errors["layers.{$index}.src"] = "Image layer {$layerId} requires a non-empty src property.";
                    }
                } elseif ($layerType === 'pattern') {
                    if (empty($layer['assetId']) || ! is_string($layer['assetId'])) {
                        $errors["layers.{$index}.assetId"] = "Pattern layer {$layerId} requires assetId.";
                    }
                } elseif ($layerType === 'group') {
                    if (! isset($layer['children']) || ! is_array($layer['children'])) {
                        $errors["layers.{$index}.children"] = "Group layer {$layerId} requires a children array.";
                    }
                }
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        // Run quality rules if requested
        if ($validateQualityRules) {
            self::validateQualityRules($design);
        }

        return $design;
    }

    /**
     * Validator quality rules for system templates (reject on failure):
     * 1. at least 3 non-text layers
     * 2. at least 2 distinct font families and 3 distinct font sizes
     * 3. every text layer inside the canvas with 64px safe margins
     * 4. estimated text/background contrast of at least 4.5:1 for body, 3:1 for display text
     *
     * @throws ValidationException
     */
    public static function validateQualityRules(array $design): void
    {
        $qualityErrors = [];
        $layers = $design['layers'] ?? [];
        $canvasW = (int) ($design['width'] ?? 1080);
        $canvasH = (int) ($design['height'] ?? 1528);

        // Rule 1: At least 3 non-text layers
        $nonTextLayers = array_filter($layers, fn ($l) => ($l['type'] ?? '') !== 'text');
        if (count($nonTextLayers) < 3) {
            $qualityErrors['quality.non_text_layers'] = 'System template requires at least 3 non-text layers (e.g. ornaments, borders, shapes). Found: ' . count($nonTextLayers);
        }

        // Rule 2: At least 2 distinct font families and 3 distinct font sizes
        $fontFamilies = [];
        $fontSizes = [];
        $textLayers = array_filter($layers, fn ($l) => ($l['type'] ?? '') === 'text');

        foreach ($textLayers as $tl) {
            if (! empty($tl['fontFamily'])) {
                $fontFamilies[$tl['fontFamily']] = true;
            }
            if (! empty($tl['fontSize'])) {
                $fontSizes[(string) (round($tl['fontSize'], 1))] = true;
            }
        }

        if (count($fontFamilies) < 2) {
            $qualityErrors['quality.font_families'] = 'System template requires at least 2 distinct font families. Found: ' . count($fontFamilies);
        }

        if (count($fontSizes) < 3) {
            $qualityErrors['quality.font_sizes'] = 'System template requires at least 3 distinct font sizes. Found: ' . count($fontSizes);
        }

        // Rule 3: Safe margins of 64px for every text layer
        $safeMargin = 64;
        foreach ($textLayers as $idx => $tl) {
            $id = $tl['id'] ?? "layer-{$idx}";
            $x = $tl['x'] ?? 0;
            $y = $tl['y'] ?? 0;
            $w = $tl['width'] ?? 0;
            $h = $tl['height'] ?? 0;

            if ($x < $safeMargin || $y < $safeMargin || ($x + $w) > ($canvasW - $safeMargin) || ($y + $h) > ($canvasH - $safeMargin)) {
                $qualityErrors["quality.safe_margin.{$id}"] = "Text layer '{$id}' violates 64px safe margins (x: {$x}, y: {$y}, w: {$w}, h: {$h}, bounds: {$canvasW}x{$canvasH}).";
            }
        }

        // Rule 4: Estimated text/background contrast
        $palette = $design['palette'] ?? PaletteResolver::DEFAULT_PALETTE;
        $bgColor = PaletteResolver::resolve($design['background']['value'] ?? '@bg', $palette);
        if (! is_string($bgColor) || ! str_starts_with(trim($bgColor), '#')) {
            $bgColor = $palette['bg'] ?? '#FAF7F2';
        }

        foreach ($textLayers as $idx => $tl) {
            $id = $tl['id'] ?? "layer-{$idx}";
            $fill = $tl['fill'] ?? '@ink';
            $resolvedFill = PaletteResolver::resolve($fill, $palette);

            if (is_string($resolvedFill) && str_starts_with(trim($resolvedFill), '#') && str_starts_with(trim($bgColor), '#')) {
                $contrast = PaletteResolver::getContrastRatio($resolvedFill, $bgColor);
                $fontSize = (float) ($tl['fontSize'] ?? 24);
                $isDisplay = $fontSize >= 36;
                $requiredRatio = $isDisplay ? 3.0 : 4.5;

                if ($contrast < $requiredRatio) {
                    $qualityErrors["quality.contrast.{$id}"] = "Text layer '{$id}' has contrast ratio {$contrast}:1 against background ({$bgColor}), requiring at least {$requiredRatio}:1.";
                }
            }
        }

        if (! empty($qualityErrors)) {
            throw ValidationException::withMessages($qualityErrors);
        }
    }

    /**
     * Return a minimal valid empty design template (v2).
     *
     * @return array<string, mixed>
     */
    public static function blankDesign(): array
    {
        return [
            'version' => 2,
            'width' => 1080,
            'height' => 1528,
            'palette' => PaletteResolver::DEFAULT_PALETTE,
            'background' => [
                'type' => 'color',
                'value' => '@bg',
                'texture' => [
                    'kind' => 'none',
                    'opacity' => 0.08,
                    'seed' => 1,
                ],
                'vignette' => 0,
            ],
            'layers' => [],
        ];
    }

    /**
     * Check if a design is valid without throwing an exception.
     */
    public static function isValid(array|string $design, bool $validateQuality = false): bool
    {
        try {
            self::validate($design, $validateQuality);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Extract all placeholders present in text layers of a design.
     *
     * @return array<string>
     */
    public static function getPlaceholders(array|string $design): array
    {
        $normalized = is_array($design) ? $design : json_decode($design, true);
        if (! isset($normalized['layers']) || ! is_array($normalized['layers'])) {
            return [];
        }

        $placeholders = [];
        foreach ($normalized['layers'] as $layer) {
            if (($layer['type'] ?? '') === 'text') {
                $textToScan = $layer['text'] ?? '';
                if (isset($layer['i18n']) && is_array($layer['i18n'])) {
                    $textToScan .= ' ' . ($layer['i18n']['en'] ?? '') . ' ' . ($layer['i18n']['am'] ?? '');
                }

                if (preg_match_all('/\{\{[a-zA-Z0-9_]+\}\}/', $textToScan, $matches)) {
                    foreach ($matches[0] as $match) {
                        $placeholders[$match] = true;
                    }
                }
            }
        }

        return array_keys($placeholders);
    }

    /**
     * Replace placeholders with runtime values.
     */
    public static function replacePlaceholders(array|string $design, array $replacements): array
    {
        $normalized = is_array($design) ? $design : json_decode($design, true);
        if (! isset($normalized['layers']) || ! is_array($normalized['layers'])) {
            return $normalized ?? [];
        }

        foreach ($normalized['layers'] as &$layer) {
            if (($layer['type'] ?? '') === 'text') {
                if (isset($layer['text']) && is_string($layer['text'])) {
                    foreach ($replacements as $key => $val) {
                        $token = str_starts_with($key, '{{') ? $key : "{{{$key}}}";
                        $layer['text'] = str_replace($token, (string) $val, $layer['text']);
                    }
                }

                if (isset($layer['i18n']) && is_array($layer['i18n'])) {
                    foreach ($layer['i18n'] as $k => $v) {
                        foreach ($replacements as $key => $val) {
                            $token = str_starts_with($key, '{{') ? $key : "{{{$key}}}";
                            $layer['i18n'][$k] = str_replace($token, (string) $val, (string) $v);
                        }
                    }
                }
            }
        }

        return $normalized;
    }
}
