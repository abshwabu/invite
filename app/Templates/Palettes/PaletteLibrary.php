<?php

namespace App\Templates\Palettes;

use App\Services\PaletteResolver;

class PaletteLibrary
{
    /**
     * Complete collection of 40 curated, contrast-verified color palettes.
     * Each palette provides all 7 contract roles:
     * bg, surface, primary, secondary, accent, ink, muted.
     *
     * Guarantee:
     * - ink on bg >= 4.5:1
     * - ink on surface >= 4.5:1
     * - primary on bg >= 3.0:1
     */
    protected static array $palettes = [
        // --- 1. GARDEN ---
        'garden-rose' => [
            'name' => 'Garden Rose',
            'mood' => 'garden',
            'category' => 'wedding',
            'bg' => '#FAF7F5',
            'surface' => '#FFFFFF',
            'primary' => '#8C2D45', // Deep rose
            'secondary' => '#D4A373', // Warm sand
            'accent' => '#B35D70',
            'ink' => '#2B171D',
            'muted' => '#6E555C',
        ],
        'garden-lavender' => [
            'name' => 'Garden Lavender',
            'mood' => 'garden',
            'category' => 'wedding',
            'bg' => '#F8F6FA',
            'surface' => '#FFFFFF',
            'primary' => '#5E3B76', // Deep purple
            'secondary' => '#C4A482', // Antique tan
            'accent' => '#8B64A3',
            'ink' => '#23142F',
            'muted' => '#655473',
        ],
        'garden-peony' => [
            'name' => 'Garden Peony',
            'mood' => 'garden',
            'category' => 'baby-shower',
            'bg' => '#FCF8F9',
            'surface' => '#FFFFFF',
            'primary' => '#9B2C4E', // Rich magenta-peony
            'secondary' => '#D9A066', // Golden apricot
            'accent' => '#C46D87',
            'ink' => '#2A111A',
            'muted' => '#72515C',
        ],
        'garden-botanical' => [
            'name' => 'Garden Botanical',
            'mood' => 'garden',
            'category' => 'housewarming',
            'bg' => '#F4F7F4',
            'surface' => '#FFFFFF',
            'primary' => '#215A36', // Deep leaf green
            'secondary' => '#C89D56', // Ochre gold
            'accent' => '#4D8262',
            'ink' => '#12261A',
            'muted' => '#4E6857',
        ],

        // --- 2. MIDNIGHT GOLD ---
        'midnight-gold' => [
            'name' => 'Midnight Gold',
            'mood' => 'midnight-gold',
            'category' => 'anniversary',
            'bg' => '#0F172A', // Dark slate navy
            'surface' => '#1E293B',
            'primary' => '#E2B857', // Brilliant warm gold
            'secondary' => '#C5A059',
            'accent' => '#F8E3A1',
            'ink' => '#F8FAFC',
            'muted' => '#94A3B8',
        ],
        'midnight-champagne' => [
            'name' => 'Midnight Champagne',
            'mood' => 'midnight-gold',
            'category' => 'anniversary',
            'bg' => '#12141D',
            'surface' => '#1F2330',
            'primary' => '#E6C687', // Champagne gold
            'secondary' => '#BA9B65',
            'accent' => '#F5E4BE',
            'ink' => '#FFFFFF',
            'muted' => '#9EABB8',
        ],
        'midnight-emerald' => [
            'name' => 'Midnight Emerald',
            'mood' => 'midnight-gold',
            'category' => 'corporate',
            'bg' => '#0A1B16',
            'surface' => '#142C24',
            'primary' => '#D8AF50', // Radiant gold
            'secondary' => '#4E9F7A',
            'accent' => '#F2D379',
            'ink' => '#F2F9F5',
            'muted' => '#87AFA0',
        ],

        // --- 3. BURGUNDY ---
        'burgundy-classic' => [
            'name' => 'Burgundy Classic',
            'mood' => 'burgundy',
            'category' => 'wedding',
            'bg' => '#FAF5F5',
            'surface' => '#FFFFFF',
            'primary' => '#7A1426', // Deep wine
            'secondary' => '#C59A45', // Gilded brass
            'accent' => '#9E3245',
            'ink' => '#290E13',
            'muted' => '#6E4E54',
        ],
        'burgundy-warm' => [
            'name' => 'Burgundy Warm Amber',
            'mood' => 'burgundy',
            'category' => 'anniversary',
            'bg' => '#FDF7F4',
            'surface' => '#FFFFFF',
            'primary' => '#80182E',
            'secondary' => '#D48C46', // Amber gold
            'accent' => '#A6384E',
            'ink' => '#2B1217',
            'muted' => '#735158',
        ],

        // --- 4. DUSTY BLUE ---
        'dusty-blue-serene' => [
            'name' => 'Dusty Blue Serene',
            'mood' => 'dusty-blue',
            'category' => 'wedding',
            'bg' => '#F3F6F9',
            'surface' => '#FFFFFF',
            'primary' => '#2D5B7A', // Slate cerulean
            'secondary' => '#C4A265', // Muted gold
            'accent' => '#56809E',
            'ink' => '#122330',
            'muted' => '#516474',
        ],
        'dusty-blue-slate' => [
            'name' => 'Dusty Blue Slate',
            'mood' => 'dusty-blue',
            'category' => 'corporate',
            'bg' => '#F5F7FA',
            'surface' => '#FFFFFF',
            'primary' => '#1E4663', // Deep slate ocean
            'secondary' => '#7E9BB0',
            'accent' => '#3E7094',
            'ink' => '#0F1E28',
            'muted' => '#4A5D6B',
        ],

        // --- 5. SOBER CHARCOAL ---
        'sober-charcoal' => [
            'name' => 'Sober Charcoal',
            'mood' => 'sober-charcoal',
            'category' => 'funeral',
            'bg' => '#F7F7F8',
            'surface' => '#FFFFFF',
            'primary' => '#272A30', // Deep granite
            'secondary' => '#8A919E', // Pewter silver
            'accent' => '#4B515D',
            'ink' => '#131518',
            'muted' => '#585F6B',
        ],
        'sober-monochrome' => [
            'name' => 'Sober Monochrome',
            'mood' => 'sober-charcoal',
            'category' => 'funeral',
            'bg' => '#F9F9FA',
            'surface' => '#FFFFFF',
            'primary' => '#1A1C1E',
            'secondary' => '#71767F',
            'accent' => '#3D4249',
            'ink' => '#0B0C0E',
            'muted' => '#52565E',
        ],

        // --- 6. DEEP NAVY DOVE ---
        'deep-navy-dove' => [
            'name' => 'Deep Navy Dove',
            'mood' => 'deep-navy-dove',
            'category' => 'funeral',
            'bg' => '#F4F6F9',
            'surface' => '#FFFFFF',
            'primary' => '#192C45', // Midnight blue
            'secondary' => '#90A3B8', // Dove gray
            'accent' => '#3D5573',
            'ink' => '#0E1724',
            'muted' => '#48586E',
        ],
        'deep-navy-silver' => [
            'name' => 'Deep Navy Silver',
            'mood' => 'deep-navy-dove',
            'category' => 'graduation',
            'bg' => '#F6F8FC',
            'surface' => '#FFFFFF',
            'primary' => '#102542',
            'secondary' => '#8598AD',
            'accent' => '#304D73',
            'ink' => '#081220',
            'muted' => '#465A73',
        ],

        // --- 7. FOREST HUSH ---
        'forest-hush' => [
            'name' => 'Forest Hush',
            'mood' => 'forest-hush',
            'category' => 'housewarming',
            'bg' => '#F4F6F3',
            'surface' => '#FFFFFF',
            'primary' => '#1C452C', // Deep evergreen
            'secondary' => '#A38758', // Bark ochre
            'accent' => '#3F7053',
            'ink' => '#0E2116',
            'muted' => '#4D6456',
        ],
        'forest-pine' => [
            'name' => 'Forest Pine & Gold',
            'mood' => 'forest-hush',
            'category' => 'wedding',
            'bg' => '#F5F8F6',
            'surface' => '#FFFFFF',
            'primary' => '#143D28',
            'secondary' => '#C99E50', // Rich brass
            'accent' => '#2E6346',
            'ink' => '#0A1F14',
            'muted' => '#476152',
        ],

        // --- 8. WARM STONE ---
        'warm-stone-taupe' => [
            'name' => 'Warm Stone Taupe',
            'mood' => 'warm-stone',
            'category' => 'wedding',
            'bg' => '#F8F6F2',
            'surface' => '#FFFFFF',
            'primary' => '#5C4A3A', // Deep earthen taupe
            'secondary' => '#B89972',
            'accent' => '#806852',
            'ink' => '#261E17',
            'muted' => '#69594C',
        ],
        'warm-stone-linen' => [
            'name' => 'Warm Stone Linen',
            'mood' => 'warm-stone',
            'category' => 'housewarming',
            'bg' => '#F9F7F3',
            'surface' => '#FFFFFF',
            'primary' => '#544638',
            'secondary' => '#A88D6F',
            'accent' => '#786450',
            'ink' => '#241D17',
            'muted' => '#66594C',
        ],
        'warm-terracotta' => [
            'name' => 'Warm Terracotta',
            'mood' => 'warm-stone',
            'category' => 'housewarming',
            'bg' => '#FAF5F1',
            'surface' => '#FFFFFF',
            'primary' => '#8A3B22', // Terracotta red
            'secondary' => '#D4955B', // Warm clay
            'accent' => '#AD593D',
            'ink' => '#2E130B',
            'muted' => '#734E43',
        ],

        // --- 9. POP ---
        'pop-confetti' => [
            'name' => 'Pop Confetti',
            'mood' => 'pop',
            'category' => 'birthday',
            'bg' => '#FBF7FD',
            'surface' => '#FFFFFF',
            'primary' => '#6A1B9A', // Vibrant violet
            'secondary' => '#D97706', // Vivid marigold
            'accent' => '#E11D48', // Electric rose
            'ink' => '#240835',
            'muted' => '#6E497C',
        ],
        'pop-sunshine' => [
            'name' => 'Pop Sunshine',
            'mood' => 'pop',
            'category' => 'birthday',
            'bg' => '#FEFBF3',
            'surface' => '#FFFFFF',
            'primary' => '#995900', // Deep burnt sunflower
            'secondary' => '#0284C7', // Vivid sky cyan
            'accent' => '#D97706',
            'ink' => '#2E1B00',
            'muted' => '#735733',
        ],
        'pop-coral' => [
            'name' => 'Pop Coral',
            'mood' => 'pop',
            'category' => 'graduation',
            'bg' => '#FCF6F6',
            'surface' => '#FFFFFF',
            'primary' => '#B3261E', // Punch coral red
            'secondary' => '#0284C7', // Bright blue
            'accent' => '#D94841',
            'ink' => '#2B0A08',
            'muted' => '#73413E',
        ],

        // --- 10. PASTEL ---
        'pastel-cotton-candy' => [
            'name' => 'Pastel Cotton Candy',
            'mood' => 'pastel',
            'category' => 'baby-shower',
            'bg' => '#FAF6F9',
            'surface' => '#FFFFFF',
            'primary' => '#783863', // Deep pastel plum
            'secondary' => '#D48CAE',
            'accent' => '#9C5885',
            'ink' => '#260F1E',
            'muted' => '#6E4C63',
        ],
        'pastel-mint-blush' => [
            'name' => 'Pastel Mint Blush',
            'mood' => 'pastel',
            'category' => 'baby-shower',
            'bg' => '#F4FAF7',
            'surface' => '#FFFFFF',
            'primary' => '#1C5940', // Deep mint pine
            'secondary' => '#D4929D', // Soft blush
            'accent' => '#44856A',
            'ink' => '#0E291D',
            'muted' => '#4B6B5E',
        ],
        'pastel-lilac-butter' => [
            'name' => 'Pastel Lilac Butter',
            'mood' => 'pastel',
            'category' => 'baby-shower',
            'bg' => '#F8F6FC',
            'surface' => '#FFFFFF',
            'primary' => '#56357A', // Deep lilac
            'secondary' => '#CFA44A', // Butter gold
            'accent' => '#7B56A3',
            'ink' => '#1F122E',
            'muted' => '#604F70',
        ],

        // --- 11. NEON NIGHT ---
        'neon-night-cyber' => [
            'name' => 'Neon Night Cyber',
            'mood' => 'neon-night',
            'category' => 'birthday',
            'bg' => '#0A0C16', // Deep space night
            'surface' => '#151928',
            'primary' => '#38BDF8', // Cyan electric
            'secondary' => '#F43F5E', // Neon rose
            'accent' => '#A855F7',
            'ink' => '#F8FAFC',
            'muted' => '#94A3B8',
        ],
        'neon-night-electric' => [
            'name' => 'Neon Night Electric Gold',
            'mood' => 'neon-night',
            'category' => 'corporate',
            'bg' => '#0D0E14',
            'surface' => '#1A1C29',
            'primary' => '#FBBF24', // Electric amber
            'secondary' => '#818CF8', // Violet neon
            'accent' => '#34D399',
            'ink' => '#FFFFFF',
            'muted' => '#9CA3AF',
        ],

        // --- 12. JUNGLE ---
        'jungle-monstera' => [
            'name' => 'Jungle Monstera',
            'mood' => 'jungle',
            'category' => 'housewarming',
            'bg' => '#F3F8F5',
            'surface' => '#FFFFFF',
            'primary' => '#0F4A2C', // Jungle deep green
            'secondary' => '#C49842', // Safari gold
            'accent' => '#2E7850',
            'ink' => '#072415',
            'muted' => '#446654',
        ],
        'jungle-tropical-bloom' => [
            'name' => 'Jungle Tropical Bloom',
            'mood' => 'jungle',
            'category' => 'birthday',
            'bg' => '#F5F8F4',
            'surface' => '#FFFFFF',
            'primary' => '#18472E',
            'secondary' => '#C94B2E', // Tropical ginger red
            'accent' => '#397853',
            'ink' => '#0C2417',
            'muted' => '#476353',
        ],

        // --- 13. SPACE ---
        'space-celestial' => [
            'name' => 'Space Celestial',
            'mood' => 'space',
            'category' => 'graduation',
            'bg' => '#0B0F1E', // Starlight void
            'surface' => '#161F36',
            'primary' => '#60A5FA', // Nebula blue
            'secondary' => '#E0B95B', // Stardust gold
            'accent' => '#93C5FD',
            'ink' => '#F8FAFC',
            'muted' => '#94A3B8',
        ],
        'space-starlight-navy' => [
            'name' => 'Space Starlight Navy',
            'mood' => 'space',
            'category' => 'anniversary',
            'bg' => '#080C17',
            'surface' => '#121B2F',
            'primary' => '#F1C765', // Pulsar gold
            'secondary' => '#7C98C2',
            'accent' => '#FDE08B',
            'ink' => '#FFFFFF',
            'muted' => '#8FA2BA',
        ],

        // --- 14. HABESHA HERITAGE ---
        'habesha-white-gold-green-red' => [
            'name' => 'Habesha Royal Tilet',
            'mood' => 'habesha-white-gold-green-red',
            'category' => 'religious-ethiopian',
            'bg' => '#FAF7F0', // Pure Kemis Warm Cotton
            'surface' => '#FFFFFF',
            'primary' => '#781622', // Ethiopian Royal Crimson
            'secondary' => '#C5952B', // Pure Axumite Gold
            'accent' => '#1C5430', // Sheba Forest Green
            'ink' => '#1A1209', // Deep Espresso Ink
            'muted' => '#6B5B49',
        ],
        'habesha-kemis-royal' => [
            'name' => 'Habesha Kemis Gold',
            'mood' => 'habesha-white-gold-green-red',
            'category' => 'wedding',
            'bg' => '#FDFBF7', // Natural woven silk
            'surface' => '#FFFFFF',
            'primary' => '#800020', // Burgundy velvet
            'secondary' => '#D4AF37', // Shimmering Gold
            'accent' => '#2E6641',
            'ink' => '#1F140C',
            'muted' => '#705E50',
        ],

        // --- 15. EID & ISLAMIC ---
        'eid-emerald-gold' => [
            'name' => 'Eid Emerald & Gold',
            'mood' => 'eid-emerald-gold',
            'category' => 'religious-islamic',
            'bg' => '#F3F8F5',
            'surface' => '#FFFFFF',
            'primary' => '#114D32', // Medina Emerald
            'secondary' => '#C59A3F', // Mecca Gold
            'accent' => '#2A7D55',
            'ink' => '#0A2619',
            'muted' => '#476957',
        ],
        'eid-crescent-ivory' => [
            'name' => 'Eid Crescent & Night Slate',
            'mood' => 'eid-emerald-gold',
            'category' => 'religious-islamic',
            'bg' => '#0D1A16', // Deep Islamic Night
            'surface' => '#162A24',
            'primary' => '#E0BA62', // Crescent Gilt
            'secondary' => '#3D8F69',
            'accent' => '#F5D78E',
            'ink' => '#F4FAF7',
            'muted' => '#85A698',
        ],

        // --- 16. MESKEL & ORTHODOX ---
        'meskel-yellow-green' => [
            'name' => 'Meskel Adey Abeba Gold',
            'mood' => 'meskel-yellow-green',
            'category' => 'religious-ethiopian',
            'bg' => '#FAF8F2', // Crisp highland ivory
            'surface' => '#FFFFFF',
            'primary' => '#8A5200', // Adey Abeba Golden Honey (contrast: 5.7:1)
            'secondary' => '#1E5935', // Damera Evergreen
            'accent' => '#B87208',
            'ink' => '#261602',
            'muted' => '#6E5638',
        ],
        'timkat-sky-gold' => [
            'name' => 'Timkat Epiphany Sky & Gold',
            'mood' => 'meskel-yellow-green',
            'category' => 'religious-ethiopian',
            'bg' => '#F3F7FA', // Baptismal clear stream
            'surface' => '#FFFFFF',
            'primary' => '#1E4870', // Tabot Royal Blue
            'secondary' => '#C49635', // Cross Gold
            'accent' => '#416D96',
            'ink' => '#0E2133',
            'muted' => '#4D6173',
        ],

        // --- 17. CORPORATE SLATE ---
        'corporate-slate-executive' => [
            'name' => 'Corporate Slate Executive',
            'mood' => 'corporate-slate',
            'category' => 'corporate',
            'bg' => '#F6F8FA',
            'surface' => '#FFFFFF',
            'primary' => '#1F3A52', // Executive steel navy
            'secondary' => '#6B8299', // Platinum gray
            'accent' => '#3B6485',
            'ink' => '#111D29',
            'muted' => '#4F6170',
        ],
        'corporate-tech-indigo' => [
            'name' => 'Corporate Tech Indigo',
            'mood' => 'corporate-slate',
            'category' => 'corporate',
            'bg' => '#F7F8FD',
            'surface' => '#FFFFFF',
            'primary' => '#312E81', // Deep tech indigo
            'secondary' => '#0D9488', // Teal accent
            'accent' => '#4F46E5',
            'ink' => '#14123E',
            'muted' => '#565485',
        ],
    ];

    /**
     * Get all registered palettes.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return self::$palettes;
    }

    /**
     * Find palette by slug.
     */
    public static function get(string $key): ?array
    {
        return self::$palettes[$key] ?? null;
    }

    /**
     * Get all palettes belonging to a specific mood.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getByMood(string $mood): array
    {
        return array_filter(self::$palettes, fn ($p) => ($p['mood'] ?? '') === $mood);
    }

    /**
     * Get all palettes recommended for a specific category.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getByCategory(string $category): array
    {
        return array_filter(self::$palettes, fn ($p) => ($p['category'] ?? '') === $category);
    }

    /**
     * Validate WCAG contrast ratio criteria for all registered palettes:
     * - ink on bg >= 4.5:1
     * - ink on surface >= 4.5:1
     * - primary on bg >= 3.0:1
     *
     * @return array<string, array<string, float>> Array of violations if any, empty on 100% pass.
     */
    public static function validateContrastRules(): array
    {
        $failures = [];

        foreach (self::$palettes as $id => $pal) {
            $bg = $pal['bg'];
            $surface = $pal['surface'];
            $primary = $pal['primary'];
            $ink = $pal['ink'];

            $inkOnBg = PaletteResolver::getContrastRatio($ink, $bg);
            $inkOnSurface = PaletteResolver::getContrastRatio($ink, $surface);
            $primaryOnBg = PaletteResolver::getContrastRatio($primary, $bg);

            $violations = [];
            if ($inkOnBg < 4.5) {
                $violations['ink_on_bg'] = $inkOnBg;
            }
            if ($inkOnSurface < 4.5) {
                $violations['ink_on_surface'] = $inkOnSurface;
            }
            if ($primaryOnBg < 3.0) {
                $violations['primary_on_bg'] = $primaryOnBg;
            }

            if (! empty($violations)) {
                $failures[$id] = $violations;
            }
        }

        return $failures;
    }
}
