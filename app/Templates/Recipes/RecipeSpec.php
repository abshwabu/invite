<?php

namespace App\Templates\Recipes;

use App\Templates\Copy\CopyLibrary;
use App\Templates\Fonts\FontPairingLibrary;
use App\Templates\Palettes\PaletteLibrary;

class RecipeSpec
{
    public string $key;
    public string $category;
    public string $recipeId;
    public string $paletteId;
    public string $pairingId;
    public string $locale;
    public string $nameEn;
    public string $nameAm;
    public string $title;
    public string $hostNames;
    public string $opening;
    public string $invitation;
    public string $details;
    public string $closing;
    public string $rsvp;
    public string $textureKind;
    public float $textureOpacity;
    public ?string $ornamentId;
    public ?string $dividerOrnament;
    public ?string $cornerOrnament;
    public ?string $borderStyle;
    public array $tags;
    public string $suiteKey;
    public array $customData;

    /**
     * Map recipe shorthand code (R1-R12) to registered recipe ID.
     */
    public const RECIPE_CODE_MAP = [
        'R1' => 'framed-center',
        'R2' => 'arch-window',
        'R3' => 'split-band',
        'R4' => 'corner-florals',
        'R5' => 'poster-stack',
        'R6' => 'medallion',
        'R7' => 'ticket',
        'R8' => 'wash',
        'R9' => 'horizon',
        'R10' => 'collage',
        'R11' => 'band',
        'R12' => 'tall-scroll',
    ];

    /**
     * Map friendly font pairing aliases.
     */
    public const FONT_PAIRING_ALIAS_MAP = [
        'cormorant-pinyon' => 'romantic-editorial',
        'cinzel-great-vibes' => 'classic-ceremonial',
        'garamond-abyssinica' => 'regal-heritage',
        'italiana-manrope' => 'modern-luxury',
        'dm-serif-manrope' => 'warm-editorial',
        'montserrat-manrope' => 'bold-contemporary',
        'oswald-space-grotesk' => 'architectural-modern',
        'space-grotesk-montserrat' => 'creative-tech',
        'fredoka-baloo' => 'playful-celebration',
        'baloo-manrope' => 'sweet-storybook',
        'pacifico-caveat' => 'whimsical-script',
        'cinzel-garamond' => 'academic-honor',
        'cormorant-garamond' => 'solemn-memorial',
        'cinzel-abyssinica' => 'sacred-tradition',
        'dm-serif-garamond' => 'cozy-hearth',
        'oswald-pacifico' => 'cosmic-party',
        'playfair-garamond' => 'fine-art-botanical',
        'montserrat-italiana' => 'clean-minimalist',
    ];

    /**
     * Map friendly palette prefix aliases.
     */
    public const PALETTE_ALIAS_MAP = [
        'garden' => 'garden-rose',
        'burgundy' => 'burgundy-velvet',
        'dusty-blue' => 'dusty-blue-slate',
        'sober-charcoal' => 'sober-charcoal',
        'midnight-gold' => 'midnight-gold-classic',
        'deep-navy' => 'deep-navy-dove',
        'forest' => 'forest-hush',
        'warm-stone' => 'warm-stone-taupe',
        'pop' => 'pop-fiesta-sun',
        'pastel' => 'pastel-candy-sweet',
        'neon' => 'neon-cyber-cyan',
        'jungle' => 'jungle-emerald',
        'space' => 'space-aurora',
        'habesha' => 'habesha-white-gold-green-red',
        'eid' => 'eid-emerald-gold',
        'meskel' => 'meskel-yellow-green',
        'corporate' => 'corporate-slate',
    ];

    public function __construct(array $attributes = [])
    {
        $this->key = $attributes['key'] ?? 'template-' . uniqid();
        $this->category = $attributes['category'] ?? 'wedding';
        $this->nameEn = $attributes['name_en'] ?? ($attributes['name'] ?? 'Template');
        $this->nameAm = $attributes['name_am'] ?? $this->nameEn;
        $this->locale = $attributes['locale'] ?? 'en';
        $this->suiteKey = $attributes['suite_key'] ?? $this->key;
        $this->tags = $attributes['tags'] ?? [];
        $this->customData = $attributes['customData'] ?? [];

        // 1. Resolve Recipe ID
        $recipeInput = $attributes['recipe'] ?? ($attributes['recipeId'] ?? 'framed-center');
        $this->recipeId = self::RECIPE_CODE_MAP[strtoupper($recipeInput)] ?? $recipeInput;

        // 2. Resolve Palette ID
        $paletteInput = $attributes['palette'] ?? ($attributes['paletteId'] ?? 'garden-rose');
        $this->paletteId = self::PALETTE_ALIAS_MAP[$paletteInput] ?? $paletteInput;
        if (!PaletteLibrary::get($this->paletteId)) {
            $this->paletteId = 'garden-rose';
        }

        // 3. Resolve Font Pairing ID
        $fontsInput = $attributes['fonts'] ?? ($attributes['pairingId'] ?? 'classic-ceremonial');
        $this->pairingId = self::FONT_PAIRING_ALIAS_MAP[$fontsInput] ?? $fontsInput;
        if (!FontPairingLibrary::get($this->pairingId)) {
            $this->pairingId = 'classic-ceremonial';
        }

        // 4. Resolve Background Texture
        $this->textureKind = $attributes['texture'] ?? ($attributes['textureKind'] ?? 'paper');
        $this->textureOpacity = (float) ($attributes['textureOpacity'] ?? 0.08);

        // 5. Resolve Ornaments
        $ornamentsInput = $attributes['ornaments'] ?? [];
        $this->ornamentId = $attributes['ornamentId'] ?? ($ornamentsInput['top'] ?? ($ornamentsInput['anchor'] ?? null));
        $this->dividerOrnament = $ornamentsInput['divider'] ?? null;
        $this->cornerOrnament = $ornamentsInput['corner'] ?? null;
        $this->borderStyle = $attributes['border'] ?? ($attributes['borderStyle'] ?? null);

        // 6. Copy Variant
        $copyConfig = $attributes['copy'] ?? [];
        $copyVariant = is_array($copyConfig) ? ($copyConfig['variant'] ?? 0) : 0;
        // Normalize 1-indexed to 0-indexed if > 0 and 1/2
        $vIdx = ($copyVariant >= 1 && $copyVariant <= 3) ? ($copyVariant - 1) : 0;

        // 7. Title
        $defaultTitles = [
            'wedding' => ['en' => 'Helen & Dawit', 'am' => 'ሄለን እና ዳዊት'],
            'anniversary' => ['en' => 'Tigist & Henok', 'am' => 'ትዕግሥት እና ሄኖክ'],
            'birthday' => ['en' => 'Yonas is Turning 30', 'am' => 'የዮናስ 30ኛ ዓመት ልደት'],
            'baby-shower' => ['en' => 'Baby Senait', 'am' => 'ትንሿ ሰናይት'],
            'graduation' => ['en' => 'Dr. Biruk Tadesse', 'am' => 'ዶ/ር ቢሩክ ታደሰ'],
            'corporate' => ['en' => 'Tech Summit 2026', 'am' => 'ዓመታዊ የቴክኖሎጂ ጉባኤ'],
            'housewarming' => ['en' => 'The Habte Family', 'am' => 'የሀብቴ ቤተሰብ አዲስ ቤት'],
            'funeral' => ['en' => 'In Memory of Ato Bekele', 'am' => 'የአቶ በቀለ ወልዴ መታሰቢያ'],
            'religious' => ['en' => 'Annual Feast of Blessing', 'am' => 'ዓመታዊ የክብር እና የበረከት በዓል'],
            'faith-symbols' => ['en' => 'Annual Thanksgiving', 'am' => 'ዓመታዊ የምስጋና በዓል'],
            'religious-islamic' => ['en' => 'Bilal & Fatima', 'am' => 'ቢላል እና ፋጢማ'],
            'religious-ethiopian' => ['en' => 'Kidan & Meron', 'am' => 'ኪዳን እና ሜሮን'],
            'other' => ['en' => 'Community Gala Celebration', 'am' => 'የማህበረሰብ የክብር በዓል'],
        ];

        $lang = $this->locale === 'am' ? 'am' : 'en';
        $this->title = $attributes['title'] ?? ($defaultTitles[$this->category][$lang] ?? ($defaultTitles['wedding'][$lang]));

        // 8. Full Information Stack: Host Names
        $this->hostNames = $attributes['host_names'] ?? $attributes['hostNames'] ?? (
            $this->locale === 'am'
                ? 'በክብር አዘጋጆች፡ {{host_names}}'
                : 'Graciously Hosted by {{host_names}}'
        );

        // 9. Information Stack: Opening line
        $this->opening = $attributes['opening'] ?? (
            CopyLibrary::get($this->category, 'opening_line', $vIdx, $this->locale)
            ?? 'Together with their families'
        );

        // 10. Information Stack: Invitation line
        $this->invitation = $attributes['invitation'] ?? (
            CopyLibrary::get($this->category, 'invitation_line', $vIdx, $this->locale)
            ?? 'request the honour of your presence to celebrate'
        );

        // 11. Information Stack: Date, Time, Venue
        $baseDetails = CopyLibrary::get($this->category, 'details_label_lines', $vIdx, $this->locale);
        $this->details = $attributes['details'] ?? (
            $baseDetails
            ?: ($this->locale === 'am'
                ? "ቀን: {{date}} | ሰዓት: {{time}}\nቦታ: {{venue}}\nሸራተን አዲስ ሆቴል፣ አዲስ አበባ"
                : "Date: {{date}} | Time: {{time}}\nVenue: {{venue}}\nSheraton Addis Hotel, Addis Ababa")
        );

        // 12. Information Stack: Closing line
        $this->closing = $attributes['closing'] ?? (
            CopyLibrary::get($this->category, 'closing_line', $vIdx, $this->locale)
            ?? 'We look forward to celebrating together.'
        );

        // 13. Information Stack: RSVP line
        $this->rsvp = $attributes['rsvp'] ?? (
            CopyLibrary::get($this->category, 'rsvp_line', $vIdx, $this->locale)
            ?? 'Kindly respond by visiting our link or calling below'
        );
    }

    public function getPalette(): array
    {
        $pal = PaletteLibrary::get($this->paletteId);
        if ($pal) {
            return [
                'bg' => $pal['bg'],
                'surface' => $pal['surface'],
                'primary' => $pal['primary'],
                'secondary' => $pal['secondary'],
                'accent' => $pal['accent'],
                'ink' => $pal['ink'],
                'muted' => $pal['muted'],
            ];
        }

        return [
            'bg' => '#FAF7F2',
            'surface' => '#FFFFFF',
            'primary' => '#1F2937',
            'secondary' => '#D4AF37',
            'accent' => '#9CA3AF',
            'ink' => '#111827',
            'muted' => '#6B7280',
        ];
    }

    public function getFontRoles(): array
    {
        return FontPairingLibrary::resolveRoles($this->pairingId, $this->locale);
    }
}
