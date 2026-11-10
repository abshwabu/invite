<?php

namespace App\Templates\Recipes;

use App\Templates\Copy\CopyLibrary;
use App\Templates\Fonts\FontPairingLibrary;
use App\Templates\Palettes\PaletteLibrary;

class RecipeSpec
{
    public string $category;
    public string $paletteId;
    public string $pairingId;
    public string $locale;
    public string $title;
    public string $opening;
    public string $invitation;
    public string $details;
    public string $closing;
    public string $rsvp;
    public string $textureKind;
    public float $textureOpacity;
    public ?string $ornamentId;
    public ?string $borderStyle;
    public array $customData;

    public function __construct(array $attributes = [])
    {
        $this->category = $attributes['category'] ?? 'wedding';
        $this->paletteId = $attributes['paletteId'] ?? 'garden-rose';
        $this->pairingId = $attributes['pairingId'] ?? 'classic-ceremonial';
        $this->locale = $attributes['locale'] ?? 'en';
        $this->textureKind = $attributes['textureKind'] ?? 'paper';
        $this->textureOpacity = (float) ($attributes['textureOpacity'] ?? 0.08);
        $this->ornamentId = $attributes['ornamentId'] ?? null;
        $this->borderStyle = $attributes['borderStyle'] ?? null;
        $this->customData = $attributes['customData'] ?? [];

        // Fallback default sample titles if not provided
        $defaultTitles = [
            'wedding' => ['en' => 'Helen & Dawit', 'am' => 'ሄለን እና ዳዊት'],
            'anniversary' => ['en' => 'Tigist & Henok', 'am' => 'ትዕግሥት እና ሄኖክ'],
            'birthday' => ['en' => 'Yonas is Turning 30', 'am' => 'የዮናስ 30ኛ ዓመት ልደት'],
            'baby-shower' => ['en' => 'Baby Senait', 'am' => 'ትንሿ ሰናይት'],
            'graduation' => ['en' => 'Dr. Biruk Tadesse', 'am' => 'ዶ/ር ቢሩክ ታደሰ'],
            'corporate' => ['en' => 'Tech Summit 2026', 'am' => 'ዓመታዊ የቴክኖሎጂ ጉባኤ'],
            'housewarming' => ['en' => 'The Habte Family', 'am' => 'የሀብቴ ቤተሰብ አዲስ ቤት'],
            'funeral' => ['en' => 'In Memory of Ato Bekele', 'am' => 'የአቶ በቀለ ወልዴ መታሰቢያ'],
            'faith-symbols' => ['en' => 'Annual Thanksgiving', 'am' => 'ዓመታዊ የምስጋና በዓል'],
            'religious-islamic' => ['en' => 'Bilal & Fatima', 'am' => 'ቢላል እና ፋጢማ'],
            'religious-ethiopian' => ['en' => 'Kidan & Meron', 'am' => 'ኪዳን እና ሜሮን'],
        ];

        $lang = $this->locale === 'am' ? 'am' : 'en';
        $this->title = $attributes['title'] ?? ($defaultTitles[$this->category][$lang] ?? 'Celebration');

        // Copy strings default from CopyLibrary
        $this->opening = $attributes['opening'] ?? (CopyLibrary::get($this->category, 'opening_line', 0, $this->locale) ?? 'Together with our families');
        $this->invitation = $attributes['invitation'] ?? (CopyLibrary::get($this->category, 'invitation_line', 0, $this->locale) ?? 'cordially invite you to celebrate');
        $this->details = $attributes['details'] ?? (CopyLibrary::get($this->category, 'details_label_lines', 0, $this->locale) ?? "Saturday, November 29, 2026\nSheraton Addis");
        $this->closing = $attributes['closing'] ?? (CopyLibrary::get($this->category, 'closing_line', 0, $this->locale) ?? 'We look forward to celebrating together');
        $this->rsvp = $attributes['rsvp'] ?? (CopyLibrary::get($this->category, 'rsvp_line', 0, $this->locale) ?? 'Kindly RSVP by November 10th');
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
