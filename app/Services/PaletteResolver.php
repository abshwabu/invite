<?php

namespace App\Services;

class PaletteResolver
{
    public const DEFAULT_PALETTE = [
        'bg' => '#FAF7F2',
        'surface' => '#FFFFFF',
        'primary' => '#800020', // Burgundy
        'secondary' => '#D4AF37', // Gold
        'accent' => '#C5A059',
        'ink' => '#1F2937',
        'muted' => '#6B7280',
    ];

    /**
     * Resolve a color value or "@role" reference against a palette.
     */
    public static function resolve(mixed $color, ?array $palette = null): mixed
    {
        $palette = array_merge(self::DEFAULT_PALETTE, $palette ?? []);

        if (is_string($color)) {
            $trimmed = trim($color);
            if (str_starts_with($trimmed, '@')) {
                $role = substr($trimmed, 1);

                return $palette[$role] ?? self::DEFAULT_PALETTE[$role] ?? '#000000';
            }

            return $trimmed;
        }

        if (is_array($color)) {
            // Handle gradient with stops
            if (isset($color['stops']) && is_array($color['stops'])) {
                $color['stops'] = array_map(function ($stop) use ($palette) {
                    if (is_array($stop)) {
                        if (isset($stop['color'])) {
                            $stop['color'] = self::resolve($stop['color'], $palette);
                        } elseif (isset($stop[1])) {
                            $stop[1] = self::resolve($stop[1], $palette);
                        }
                    }

                    return $stop;
                }, $color['stops']);
            }

            return $color;
        }

        return $color;
    }

    /**
     * Apply a new palette to a design structure.
     * Swapping the palette recolors the whole card.
     */
    public static function applyPalette(array $design, array $newPalette): array
    {
        $currentPalette = $design['palette'] ?? self::DEFAULT_PALETTE;
        $mergedPalette = array_merge($currentPalette, $newPalette);

        $design['palette'] = $mergedPalette;

        return $design;
    }

    /**
     * Derive a harmonious palette from a single base color hex.
     * Implements color harmony theory (analogous, split-complementary, luminance-aware).
     */
    public static function deriveHarmoniousPalette(string $baseHex): array
    {
        $baseHex = self::normalizeHex($baseHex);
        [$r, $g, $b] = self::hexToRgb($baseHex);
        [$h, $s, $l] = self::rgbToHsl($r, $g, $b);

        // Calculate perceived luminance: 0.2126*R + 0.7152*G + 0.0722*B
        $luminance = (0.2126 * ($r / 255) + 0.7152 * ($g / 255) + 0.0722 * ($b / 255));

        // Primary is the chosen base color
        $primary = $baseHex;

        // Secondary: Split-complementary / analogous gold or contrasting hue
        $secHue = ($h + 35) % 360;
        $secondary = self::hslToHex($secHue, max(0.4, min(0.85, $s * 0.9)), max(0.35, min(0.65, $l)));

        // Accent: Warm gold/metallic highlight
        $accent = self::hslToHex(($h + 45) % 360, 0.65, 0.60);

        if ($luminance < 0.4) {
            // Dark primary -> Light background (Ivory / warm cream)
            $bg = self::hslToHex($h, 0.15, 0.97); // #FAF8F5 tinted
            $surface = '#FFFFFF';
            $ink = self::hslToHex($h, 0.25, 0.12); // Deep rich charcoal tint
            $muted = self::hslToHex($h, 0.15, 0.45);
        } else {
            // Light primary -> Dark background or crisp contrast
            $bg = self::hslToHex($h, 0.20, 0.96);
            $surface = '#FFFFFF';
            $ink = self::hslToHex($h, 0.40, 0.10);
            $muted = self::hslToHex($h, 0.20, 0.40);
        }

        return [
            'bg' => $bg,
            'surface' => $surface,
            'primary' => $primary,
            'secondary' => $secondary,
            'accent' => $accent,
            'ink' => $ink,
            'muted' => $muted,
        ];
    }

    /**
     * Compute relative luminance of a color.
     */
    public static function getLuminance(string $hex): float
    {
        $hex = self::normalizeHex($hex);
        [$r, $g, $b] = self::hexToRgb($hex);

        $toLinear = function ($c) {
            $v = $c / 255.0;

            return ($v <= 0.03928) ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
        };

        return 0.2126 * $toLinear($r) + 0.7152 * $toLinear($g) + 0.0722 * $toLinear($b);
    }

    /**
     * Compute contrast ratio between two colors according to WCAG.
     */
    public static function getContrastRatio(string $color1, string $color2): float
    {
        $l1 = self::getLuminance($color1);
        $l2 = self::getLuminance($color2);

        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);

        return round(($lighter + 0.05) / ($darker + 0.05), 2);
    }

    public static function normalizeHex(string $hex): string
    {
        $clean = ltrim(trim($hex), '#');
        if (strlen($clean) === 3) {
            $clean = $clean[0] . $clean[0] . $clean[1] . $clean[1] . $clean[2] . $clean[2];
        }

        return '#' . strtolower(substr($clean, 0, 6));
    }

    public static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    public static function rgbToHsl(int $r, int $g, int $b): array
    {
        $r /= 255;
        $g /= 255;
        $b /= 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $diff = $max - $min;

        $l = ($max + $min) / 2;

        if ($diff == 0) {
            $h = 0;
            $s = 0;
        } else {
            $s = $l > 0.5 ? $diff / (2 - $max - $min) : $diff / ($max + $min);

            switch ($max) {
                case $r:
                    $h = ($g - $b) / $diff + ($g < $b ? 6 : 0);
                    break;
                case $g:
                    $h = ($b - $r) / $diff + 2;
                    break;
                case $b:
                    $h = ($r - $g) / $diff + 4;
                    break;
                default:
                    $h = 0;
            }
            $h /= 6;
        }

        return [round($h * 360), $s, $l];
    }

    public static function hslToHex(float $h, float $s, float $l): string
    {
        $h = fmod($h, 360);
        if ($h < 0) {
            $h += 360;
        }
        $h /= 360;

        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;

        $r = self::hueToRgb($p, $q, $h + 1 / 3);
        $g = self::hueToRgb($p, $q, $h);
        $b = self::hueToRgb($p, $q, $h - 1 / 3);

        return sprintf('#%02x%02x%02x', round($r * 255), round($g * 255), round($b * 255));
    }

    private static function hueToRgb(float $p, float $q, float $t): float
    {
        if ($t < 0) {
            $t += 1;
        }
        if ($t > 1) {
            $t -= 1;
        }
        if ($t < 1 / 6) {
            return $p + ($q - $p) * 6 * $t;
        }
        if ($t < 1 / 2) {
            return $q;
        }
        if ($t < 2 / 3) {
            return $p + ($q - $p) * (2 / 3 - $t) * 6;
        }

        return $p;
    }
}
