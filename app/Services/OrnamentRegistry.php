<?php

namespace App\Services;

class OrnamentRegistry
{
    private static ?array $manifest = null;

    /**
     * Get all ornaments from manifest.
     */
    public static function all(): array
    {
        if (self::$manifest === null) {
            $path = resource_path('ornaments/manifest.json');
            if (file_exists($path)) {
                self::$manifest = json_decode(file_get_contents($path), true) ?? [];
            } else {
                self::$manifest = [];
            }
        }

        return self::$manifest;
    }

    /**
     * Filter ornaments by set.
     */
    public static function getBySet(string $set): array
    {
        return array_values(array_filter(self::all(), fn ($o) => ($o['set'] ?? '') === $set));
    }

    /**
     * Filter ornaments by anchor.
     */
    public static function getByAnchor(string $anchor): array
    {
        return array_values(array_filter(self::all(), fn ($o) => ($o['anchor'] ?? '') === $anchor));
    }

    /**
     * Filter ornaments by tag.
     */
    public static function getByTag(string $tag): array
    {
        return array_values(array_filter(self::all(), fn ($o) => in_array($tag, $o['tags'] ?? [], true)));
    }

    /**
     * Find ornament by id.
     */
    public static function find(string $id): ?array
    {
        foreach (self::all() as $item) {
            if (($item['id'] ?? '') === $id) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Load raw SVG file for an ornament.
     */
    public static function loadRawSvg(string $id): ?string
    {
        $ornament = self::find($id);
        if (! $ornament) {
            return null;
        }

        $set = $ornament['set'];
        $filePath = resource_path("ornaments/{$set}/{$id}.svg");

        if (! file_exists($filePath)) {
            return null;
        }

        return file_get_contents($filePath);
    }

    /**
     * Load, sanitize, and tint an ornament SVG.
     * Replaces {{c1}}, {{c2}}, {{c3}} with resolved colors.
     */
    public static function loadTintedSvg(string $id, array $tint = [], ?array $palette = null): ?string
    {
        $raw = self::loadRawSvg($id);
        if (! $raw) {
            return null;
        }

        // Sanitize raw first
        $clean = SvgSanitizer::sanitize($raw);

        // Resolve colors
        $c1 = PaletteResolver::resolve($tint['c1'] ?? '@primary', $palette);
        $c2 = PaletteResolver::resolve($tint['c2'] ?? '@secondary', $palette);
        $c3 = PaletteResolver::resolve($tint['c3'] ?? '@accent', $palette);

        $tinted = str_replace(
            ['{{c1}}', '{{c2}}', '{{c3}}'],
            [(string) $c1, (string) $c2, (string) $c3],
            $clean
        );

        return $tinted;
    }
}
