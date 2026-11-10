<?php

namespace App\Templates\Fonts;

class FontPairingLibrary
{
    /**
     * 18 Curated font pairings mapped to aesthetic moods, categories, and bilingual typography.
     * Each pairing defines the 5 contract roles:
     * - display
     * - script-or-accent
     * - body
     * - ethiopic-display
     * - ethiopic-body
     */
    protected static array $pairings = [
        'classic-ceremonial' => [
            'id' => 'classic-ceremonial',
            'name' => 'Classic Ceremonial',
            'description' => 'Cinzel display with graceful Great Vibes and EB Garamond body, paired with ceremonial Abyssinica SIL for Ge\'ez calligraphy.',
            'display' => 'Cinzel',
            'script-or-accent' => 'Great Vibes',
            'body' => 'EB Garamond',
            'ethiopic-display' => 'Abyssinica SIL',
            'ethiopic-body' => 'Noto Serif Ethiopic',
            'moods' => ['midnight-gold', 'burgundy', 'habesha-white-gold-green-red'],
            'categories' => ['wedding', 'anniversary', 'religious-ethiopian'],
        ],
        'romantic-editorial' => [
            'id' => 'romantic-editorial',
            'name' => 'Romantic Editorial',
            'description' => 'High-contrast Playfair Display with delicate Pinyon Script accents and refined Cormorant Garamond body.',
            'display' => 'Playfair Display',
            'script-or-accent' => 'Pinyon Script',
            'body' => 'Cormorant Garamond',
            'ethiopic-display' => 'Noto Serif Ethiopic',
            'ethiopic-body' => 'Noto Serif Ethiopic',
            'moods' => ['garden', 'dusty-blue', 'burgundy'],
            'categories' => ['wedding', 'anniversary'],
        ],
        'regal-heritage' => [
            'id' => 'regal-heritage',
            'name' => 'Regal Heritage',
            'description' => 'Stately EB Garamond title with flourishes and traditional Ethiopian Abyssinica SIL script.',
            'display' => 'EB Garamond',
            'script-or-accent' => 'Great Vibes',
            'body' => 'Cormorant Garamond',
            'ethiopic-display' => 'Abyssinica SIL',
            'ethiopic-body' => 'Noto Serif Ethiopic',
            'moods' => ['habesha-white-gold-green-red', 'eid-emerald-gold', 'midnight-gold'],
            'categories' => ['wedding', 'religious-ethiopian', 'faith-symbols'],
        ],
        'modern-luxury' => [
            'id' => 'modern-luxury',
            'name' => 'Modern Luxury',
            'description' => 'Slender high-fashion Italiana title with crisp Manrope body and calligraphic script.',
            'display' => 'Italiana',
            'script-or-accent' => 'Pinyon Script',
            'body' => 'Manrope',
            'ethiopic-display' => 'Noto Serif Ethiopic',
            'ethiopic-body' => 'Noto Sans Ethiopic',
            'moods' => ['midnight-gold', 'sober-charcoal', 'dusty-blue'],
            'categories' => ['wedding', 'corporate', 'anniversary'],
        ],
        'warm-editorial' => [
            'id' => 'warm-editorial',
            'name' => 'Warm Editorial',
            'description' => 'Rich DM Serif Display headline softened with Caveat accents and clean Manrope details.',
            'display' => 'DM Serif Display',
            'script-or-accent' => 'Caveat',
            'body' => 'Manrope',
            'ethiopic-display' => 'Noto Serif Ethiopic',
            'ethiopic-body' => 'Noto Sans Ethiopic',
            'moods' => ['warm-stone', 'forest-hush', 'garden'],
            'categories' => ['housewarming', 'anniversary', 'wedding'],
        ],
        'bold-contemporary' => [
            'id' => 'bold-contemporary',
            'name' => 'Bold Contemporary',
            'description' => 'Geometric Montserrat headline with casual handwritten touch and ultra-legible Manrope body.',
            'display' => 'Montserrat',
            'script-or-accent' => 'Caveat',
            'body' => 'Manrope',
            'ethiopic-display' => 'Noto Sans Ethiopic',
            'ethiopic-body' => 'Noto Sans Ethiopic',
            'moods' => ['corporate-slate', 'deep-navy-dove', 'sober-charcoal'],
            'categories' => ['corporate', 'graduation'],
        ],
        'architectural-modern' => [
            'id' => 'architectural-modern',
            'name' => 'Architectural Modern',
            'description' => 'Condensed vertical Oswald impact with monospace tech accents and robust Manrope body.',
            'display' => 'Oswald',
            'script-or-accent' => 'Space Grotesk',
            'body' => 'Manrope',
            'ethiopic-display' => 'Noto Sans Ethiopic',
            'ethiopic-body' => 'Noto Sans Ethiopic',
            'moods' => ['corporate-slate', 'sober-charcoal'],
            'categories' => ['corporate', 'graduation'],
        ],
        'creative-tech' => [
            'id' => 'creative-tech',
            'name' => 'Creative Tech',
            'description' => 'Tech-forward Space Grotesk headline with vibrant modern accents and clean Montserrat copy.',
            'display' => 'Space Grotesk',
            'script-or-accent' => 'Caveat',
            'body' => 'Montserrat',
            'ethiopic-display' => 'Noto Sans Ethiopic',
            'ethiopic-body' => 'Noto Sans Ethiopic',
            'moods' => ['pop', 'neon-night', 'corporate-slate'],
            'categories' => ['corporate', 'birthday'],
        ],
        'playful-celebration' => [
            'id' => 'playful-celebration',
            'name' => 'Playful Celebration',
            'description' => 'Bouncy Fredoka display with retro Pacifico accents and warm rounded Baloo 2 body.',
            'display' => 'Fredoka',
            'script-or-accent' => 'Pacifico',
            'body' => 'Baloo 2',
            'ethiopic-display' => 'Noto Sans Ethiopic',
            'ethiopic-body' => 'Noto Sans Ethiopic',
            'moods' => ['pop', 'jungle', 'pastel'],
            'categories' => ['birthday', 'baby-shower'],
        ],
        'sweet-storybook' => [
            'id' => 'sweet-storybook',
            'name' => 'Sweet Storybook',
            'description' => 'Gentle rounded Baloo 2 display with sweet script accents and comfortable Manrope body.',
            'display' => 'Baloo 2',
            'script-or-accent' => 'Pacifico',
            'body' => 'Manrope',
            'ethiopic-display' => 'Noto Sans Ethiopic',
            'ethiopic-body' => 'Noto Sans Ethiopic',
            'moods' => ['pastel', 'warm-stone'],
            'categories' => ['baby-shower', 'birthday'],
        ],
        'whimsical-script' => [
            'id' => 'whimsical-script',
            'name' => 'Whimsical Script',
            'description' => 'Warm brush Pacifico headline paired with Caveat micro-notes and balanced Montserrat body.',
            'display' => 'Pacifico',
            'script-or-accent' => 'Caveat',
            'body' => 'Montserrat',
            'ethiopic-display' => 'Noto Sans Ethiopic',
            'ethiopic-body' => 'Noto Sans Ethiopic',
            'moods' => ['pop', 'pastel'],
            'categories' => ['birthday', 'baby-shower'],
        ],
        'academic-honor' => [
            'id' => 'academic-honor',
            'name' => 'Academic Honor',
            'description' => 'Prestigious Cinzel Roman inscriptional title with scholarly EB Garamond body.',
            'display' => 'Cinzel',
            'script-or-accent' => 'EB Garamond',
            'body' => 'EB Garamond',
            'ethiopic-display' => 'Abyssinica SIL',
            'ethiopic-body' => 'Noto Serif Ethiopic',
            'moods' => ['deep-navy-dove', 'midnight-gold', 'corporate-slate'],
            'categories' => ['graduation', 'corporate'],
        ],
        'solemn-memorial' => [
            'id' => 'solemn-memorial',
            'name' => 'Solemn Memorial',
            'description' => 'Quiet, dignified Cormorant Garamond display with graceful subtle script and classical body.',
            'display' => 'Cormorant Garamond',
            'script-or-accent' => 'Pinyon Script',
            'body' => 'EB Garamond',
            'ethiopic-display' => 'Abyssinica SIL',
            'ethiopic-body' => 'Noto Serif Ethiopic',
            'moods' => ['sober-charcoal', 'warm-stone', 'deep-navy-dove'],
            'categories' => ['funeral', 'faith-symbols'],
        ],
        'sacred-tradition' => [
            'id' => 'sacred-tradition',
            'name' => 'Sacred Tradition',
            'description' => 'Reverent Cinzel title with radiant Great Vibes and ancient Abyssinica SIL Ge\'ez text.',
            'display' => 'Cinzel',
            'script-or-accent' => 'Great Vibes',
            'body' => 'Cormorant Garamond',
            'ethiopic-display' => 'Abyssinica SIL',
            'ethiopic-body' => 'Noto Serif Ethiopic',
            'moods' => ['eid-emerald-gold', 'meskel-yellow-green', 'habesha-white-gold-green-red'],
            'categories' => ['religious-ethiopian', 'religious-islamic', 'faith-symbols'],
        ],
        'cozy-hearth' => [
            'id' => 'cozy-hearth',
            'name' => 'Cozy Hearth',
            'description' => 'Inviting DM Serif Display with casual Caveat notes and timeless EB Garamond text.',
            'display' => 'DM Serif Display',
            'script-or-accent' => 'Caveat',
            'body' => 'EB Garamond',
            'ethiopic-display' => 'Noto Serif Ethiopic',
            'ethiopic-body' => 'Noto Serif Ethiopic',
            'moods' => ['warm-stone', 'forest-hush'],
            'categories' => ['housewarming', 'anniversary'],
        ],
        'cosmic-party' => [
            'id' => 'cosmic-party',
            'name' => 'Cosmic Party',
            'description' => 'Energetic tall Oswald display with Pacifico callouts and technical Space Grotesk details.',
            'display' => 'Oswald',
            'script-or-accent' => 'Pacifico',
            'body' => 'Space Grotesk',
            'ethiopic-display' => 'Noto Sans Ethiopic',
            'ethiopic-body' => 'Noto Sans Ethiopic',
            'moods' => ['space', 'neon-night'],
            'categories' => ['birthday'],
        ],
        'fine-art-botanical' => [
            'id' => 'fine-art-botanical',
            'name' => 'Fine Art Botanical',
            'description' => 'Graceful Playfair Display with flourishing Pinyon Script accents and literary EB Garamond text.',
            'display' => 'Playfair Display',
            'script-or-accent' => 'Pinyon Script',
            'body' => 'EB Garamond',
            'ethiopic-display' => 'Noto Serif Ethiopic',
            'ethiopic-body' => 'Noto Serif Ethiopic',
            'moods' => ['garden', 'forest-hush', 'dusty-blue'],
            'categories' => ['wedding', 'anniversary'],
        ],
        'clean-minimalist' => [
            'id' => 'clean-minimalist',
            'name' => 'Clean Minimalist',
            'description' => 'Contemporary geometric Montserrat heading with elegant Italiana accents and neutral Manrope body.',
            'display' => 'Montserrat',
            'script-or-accent' => 'Italiana',
            'body' => 'Manrope',
            'ethiopic-display' => 'Noto Sans Ethiopic',
            'ethiopic-body' => 'Noto Sans Ethiopic',
            'moods' => ['sober-charcoal', 'corporate-slate', 'deep-navy-dove'],
            'categories' => ['corporate', 'housewarming'],
        ],
    ];

    public static function all(): array
    {
        return static::$pairings;
    }

    public static function get(string $id): ?array
    {
        return static::$pairings[$id] ?? null;
    }

    public static function forMood(string $mood): array
    {
        return array_filter(static::$pairings, function ($pairing) use ($mood) {
            return in_array($mood, $pairing['moods'], true);
        });
    }

    public static function forCategory(string $category): array
    {
        return array_filter(static::$pairings, function ($pairing) use ($category) {
            return in_array($category, $pairing['categories'], true);
        });
    }

    /**
     * Resolve font family names mapped to language / locale.
     * When locale is 'am', displays map to ethiopic-display and body to ethiopic-body.
     */
    public static function resolveRoles(string $pairingId, string $locale = 'en'): array
    {
        $p = static::get($pairingId) ?? static::$pairings['classic-ceremonial'];

        $isAmharic = ($locale === 'am');

        return [
            'display' => $isAmharic ? $p['ethiopic-display'] : $p['display'],
            'script_or_accent' => $isAmharic ? $p['ethiopic-display'] : $p['script-or-accent'],
            'body' => $isAmharic ? $p['ethiopic-body'] : $p['body'],
            'font_family_display' => $isAmharic ? $p['ethiopic-display'] : $p['display'],
            'font_family_accent' => $isAmharic ? $p['ethiopic-display'] : $p['script-or-accent'],
            'font_family_body' => $isAmharic ? $p['ethiopic-body'] : $p['body'],
        ];
    }

    /**
     * Validate that all referenced font families exist in public/fonts/manifest.json.
     */
    public static function validateFonts(?string $manifestPath = null): array
    {
        $manifestPath = $manifestPath ?: (function_exists('public_path') && app()->has('path.public')
            ? public_path('fonts/manifest.json')
            : (defined('LARAVEL_START') ? base_path('public/fonts/manifest.json') : dirname(__DIR__, 3) . '/public/fonts/manifest.json'));
        if (!file_exists($manifestPath)) {
            return ['Font manifest not found at ' . $manifestPath];
        }

        $manifest = json_decode(file_get_contents($manifestPath), true) ?: [];
        $availableFamilies = array_unique(array_column($manifest, 'family'));

        $errors = [];
        $roles = ['display', 'script-or-accent', 'body', 'ethiopic-display', 'ethiopic-body'];

        foreach (static::$pairings as $id => $p) {
            foreach ($roles as $role) {
                if (!isset($p[$role])) {
                    $errors[] = "Pairing [{$id}] missing required role [{$role}]";
                    continue;
                }
                $family = $p[$role];
                if (!in_array($family, $availableFamilies, true)) {
                    $errors[] = "Pairing [{$id}] references font [{$family}] in role [{$role}] which is not in manifest";
                }
            }
        }

        return $errors;
    }
}
