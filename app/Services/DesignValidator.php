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

    public const SUPPORTED_BACKGROUND_TYPES = ['color', 'image', 'gradient'];
    public const SUPPORTED_LAYER_TYPES = ['text', 'image', 'shape'];
    public const SUPPORTED_SHAPES = ['rect', 'circle', 'line'];

    /**
     * Validate the design JSON structure according to the contract.
     * Returns the normalized array or throws ValidationException.
     *
     * @param  array<string, mixed>|string  $design
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function validate(array|string $design): array
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

        // 1. Version
        $version = $design['version'] ?? null;
        if (! is_int($version) || $version < 1) {
            $errors['version'] = 'Design version must be a positive integer (e.g. 1).';
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
            if (! in_array($bgType, self::SUPPORTED_BACKGROUND_TYPES, true)) {
                $errors['background.type'] = 'Background type must be one of: color, image, gradient.';
            }

            if (! isset($design['background']['value']) || (is_string($design['background']['value']) && trim($design['background']['value']) === '')) {
                $errors['background.value'] = 'Background value is required.';
            }
        }

        // 4. Layers
        if (! isset($design['layers']) || ! is_array($design['layers'])) {
            $errors['layers'] = 'Design layers must be an array.';
        } else {
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
                if (! in_array($layerType, self::SUPPORTED_LAYER_TYPES, true)) {
                    $errors["layers.{$index}.type"] = "Layer at index {$index} type must be text, image, or shape.";
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
                    if (! isset($layer['text']) || ! is_string($layer['text'])) {
                        $errors["layers.{$index}.text"] = "Text layer {$layerId} requires a text property.";
                    }
                } elseif ($layerType === 'image') {
                    if (empty($layer['src']) || ! is_string($layer['src'])) {
                        $errors["layers.{$index}.src"] = "Image layer {$layerId} requires a non-empty src property.";
                    }
                } elseif ($layerType === 'shape') {
                    $shape = $layer['shape'] ?? null;
                    if (! in_array($shape, self::SUPPORTED_SHAPES, true)) {
                        $errors["layers.{$index}.shape"] = "Shape layer {$layerId} shape must be rect, circle, or line.";
                    }
                }
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        // Return normalized design structure
        return [
            'version' => (int) $design['version'],
            'width' => (int) $design['width'],
            'height' => (int) $design['height'],
            'background' => [
                'type' => (string) $design['background']['type'],
                'value' => $design['background']['value'],
            ],
            'layers' => array_values(array_map(function ($l) {
                return [
                    'id' => (string) ($l['id'] ?? Str::uuid()->toString()),
                    'type' => (string) $l['type'],
                    'x' => (float) ($l['x'] ?? 0),
                    'y' => (float) ($l['y'] ?? 0),
                    'width' => (float) ($l['width'] ?? 0),
                    'height' => (float) ($l['height'] ?? 0),
                    'rotation' => (float) ($l['rotation'] ?? 0),
                    'opacity' => isset($l['opacity']) ? (float) $l['opacity'] : 1.0,
                    'locked' => (bool) ($l['locked'] ?? false),
                    ...array_filter([
                        'text' => isset($l['text']) ? (string) $l['text'] : null,
                        'fontFamily' => isset($l['fontFamily']) ? (string) $l['fontFamily'] : null,
                        'fontSize' => isset($l['fontSize']) ? (float) $l['fontSize'] : null,
                        'fontWeight' => isset($l['fontWeight']) ? (int) $l['fontWeight'] : null,
                        'fill' => isset($l['fill']) ? (string) $l['fill'] : null,
                        'align' => isset($l['align']) ? (string) $l['align'] : null,
                        'lineHeight' => isset($l['lineHeight']) ? (float) $l['lineHeight'] : null,
                        'letterSpacing' => isset($l['letterSpacing']) ? (float) $l['letterSpacing'] : null,
                        'src' => isset($l['src']) ? (string) $l['src'] : null,
                        'shape' => isset($l['shape']) ? (string) $l['shape'] : null,
                        'stroke' => isset($l['stroke']) ? (string) $l['stroke'] : null,
                        'strokeWidth' => isset($l['strokeWidth']) ? (float) $l['strokeWidth'] : null,
                    ], fn ($val) => $val !== null),
                ];
            }, $design['layers'])),
        ];
    }

    /**
     * Return a minimal valid empty design template.
     *
     * @return array<string, mixed>
     */
    public static function blankDesign(): array
    {
        return [
            'version' => 1,
            'width' => 1080,
            'height' => 1528,
            'background' => [
                'type' => 'color',
                'value' => '#18181B',
            ],
            'layers' => [],
        ];
    }

    /**
     * Check if a design is valid without throwing an exception.
     */
    public static function isValid(array|string $design): bool
    {
        try {
            self::validate($design);

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
            if (($layer['type'] ?? '') === 'text' && ! empty($layer['text'])) {
                if (preg_match_all('/\{\{[a-zA-Z0-9_]+\}\}/', $layer['text'], $matches)) {
                    foreach ($matches[0] as $match) {
                        $placeholders[$match] = true;
                    }
                }
            }
        }

        return array_keys($placeholders);
    }

    /**
     * Replace placeholders with runtime values (e.g. {{guest_name}} -> "Almaz Kebede").
     */
    public static function replacePlaceholders(array|string $design, array $replacements): array
    {
        $normalized = is_array($design) ? $design : json_decode($design, true);
        if (! isset($normalized['layers']) || ! is_array($normalized['layers'])) {
            return $normalized ?? [];
        }

        foreach ($normalized['layers'] as &$layer) {
            if (($layer['type'] ?? '') === 'text' && ! empty($layer['text'])) {
                foreach ($replacements as $key => $val) {
                    $token = str_starts_with($key, '{{') ? $key : "{{{$key}}}";
                    $layer['text'] = str_replace($token, (string) $val, $layer['text']);
                }
            }
        }

        return $normalized;
    }
}
