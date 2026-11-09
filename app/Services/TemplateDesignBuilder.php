<?php

namespace App\Services;

use Illuminate\Support\Str;

class TemplateDesignBuilder
{
    public const WIDTH = 1080;
    public const HEIGHT = 1528;

    /**
     * Build an invitation design JSON with varied styling.
     */
    public static function buildInvitation(array $theme): array
    {
        $id = fn () => Str::uuid()->toString();

        $bg = $theme['background'];
        $primaryColor = $theme['primaryColor'];
        $secondaryColor = $theme['secondaryColor'] ?? $primaryColor;
        $textColor = $theme['textColor'];
        $accentColor = $theme['accentColor'] ?? $primaryColor;
        $titleFont = $theme['titleFont'] ?? 'Cinzel';
        $bodyFont = $theme['bodyFont'] ?? 'Noto Sans Ethiopic';
        $isFuneral = ($theme['is_funeral'] ?? false) === true;

        $layers = [];

        // 1. Decorative border (if not skipped)
        if (! ($theme['no_border'] ?? false)) {
            $borderPadding = $theme['border_padding'] ?? 40;
            $layers[] = [
                'id' => $id(),
                'type' => 'shape',
                'shape' => 'rect',
                'x' => $borderPadding,
                'y' => $borderPadding,
                'width' => self::WIDTH - ($borderPadding * 2),
                'height' => self::HEIGHT - ($borderPadding * 2),
                'stroke' => $secondaryColor,
                'strokeWidth' => $isFuneral ? 1.5 : ($theme['border_width'] ?? 2),
                'fill' => 'transparent',
                'opacity' => $isFuneral ? 0.4 : 0.8,
                'locked' => true,
            ];

            if (! $isFuneral && ($theme['double_border'] ?? true)) {
                $innerPadding = $borderPadding + 14;
                $layers[] = [
                    'id' => $id(),
                    'type' => 'shape',
                    'shape' => 'rect',
                    'x' => $innerPadding,
                    'y' => $innerPadding,
                    'width' => self::WIDTH - ($innerPadding * 2),
                    'height' => self::HEIGHT - ($innerPadding * 2),
                    'stroke' => $secondaryColor,
                    'strokeWidth' => 1,
                    'fill' => 'transparent',
                    'opacity' => 0.4,
                    'locked' => true,
                ];
            }
        }

        // 2. Category Header / Top Pre-title
        $layers[] = [
            'id' => $id(),
            'type' => 'text',
            'x' => 90,
            'y' => 180,
            'width' => 900,
            'height' => 50,
            'text' => $theme['pre_title'] ?? 'CORDIALLY INVITED',
            'fontFamily' => $theme['pre_title_font'] ?? $titleFont,
            'fontSize' => $theme['pre_title_size'] ?? 26,
            'fontWeight' => 600,
            'fill' => $accentColor,
            'align' => 'center',
            'letterSpacing' => 4,
            'lineHeight' => 1.2,
        ];

        // 3. Guest Honor Line (with placeholder {{guest_name}})
        $layers[] = [
            'id' => $id(),
            'type' => 'text',
            'x' => 90,
            'y' => 270,
            'width' => 900,
            'height' => 60,
            'text' => $theme['guest_line'] ?? 'Dear {{guest_name}}',
            'fontFamily' => $bodyFont,
            'fontSize' => 32,
            'fontWeight' => 500,
            'fill' => $textColor,
            'align' => 'center',
            'lineHeight' => 1.2,
        ];

        // 4. Main Event Title / Celebrant (with placeholder {{event_title}})
        $layers[] = [
            'id' => $id(),
            'type' => 'text',
            'x' => 70,
            'y' => 400,
            'width' => 940,
            'height' => 180,
            'text' => $theme['event_title'] ?? '{{event_title}}',
            'fontFamily' => $titleFont,
            'fontSize' => $theme['title_size'] ?? 64,
            'fontWeight' => $theme['title_weight'] ?? 700,
            'fill' => $primaryColor,
            'align' => 'center',
            'lineHeight' => 1.25,
            'letterSpacing' => $theme['title_spacing'] ?? 1,
        ];

        // 5. Decorative Divider Line (unless funeral simple)
        if (! $isFuneral) {
            $layers[] = [
                'id' => $id(),
                'type' => 'shape',
                'shape' => 'line',
                'x' => 390,
                'y' => 630,
                'width' => 300,
                'height' => 2,
                'stroke' => $secondaryColor,
                'strokeWidth' => 2,
                'opacity' => 0.7,
            ];
        }

        // 6. Subheading / Invitation Message
        $layers[] = [
            'id' => $id(),
            'type' => 'text',
            'x' => 100,
            'y' => 680,
            'width' => 880,
            'height' => 120,
            'text' => $theme['invitation_message'] ?? 'Please join us in celebrating this joyous occasion with family and friends.',
            'fontFamily' => $bodyFont,
            'fontSize' => 26,
            'fontWeight' => 400,
            'fill' => $textColor,
            'align' => 'center',
            'lineHeight' => 1.5,
        ];

        // 7. Date & Time Block
        $layers[] = [
            'id' => $id(),
            'type' => 'text',
            'x' => 90,
            'y' => 860,
            'width' => 900,
            'height' => 60,
            'text' => '📅  {{date}}',
            'fontFamily' => $titleFont,
            'fontSize' => 34,
            'fontWeight' => 600,
            'fill' => $primaryColor,
            'align' => 'center',
            'lineHeight' => 1.2,
        ];

        $layers[] = [
            'id' => $id(),
            'type' => 'text',
            'x' => 90,
            'y' => 930,
            'width' => 900,
            'height' => 50,
            'text' => '⏰  {{time}}',
            'fontFamily' => $bodyFont,
            'fontSize' => 28,
            'fontWeight' => 500,
            'fill' => $textColor,
            'align' => 'center',
            'lineHeight' => 1.2,
        ];

        // 8. Venue Block
        $layers[] = [
            'id' => $id(),
            'type' => 'text',
            'x' => 100,
            'y' => 1040,
            'width' => 880,
            'height' => 80,
            'text' => '📍  {{venue}}',
            'fontFamily' => $bodyFont,
            'fontSize' => 28,
            'fontWeight' => 500,
            'fill' => $textColor,
            'align' => 'center',
            'lineHeight' => 1.3,
        ];

        // 9. Host Names Block
        $layers[] = [
            'id' => $id(),
            'type' => 'text',
            'x' => 100,
            'y' => 1230,
            'width' => 880,
            'height' => 80,
            'text' => 'Graciously Hosted by:\n{{host_names}}',
            'fontFamily' => $titleFont,
            'fontSize' => 26,
            'fontWeight' => 600,
            'fill' => $accentColor,
            'align' => 'center',
            'lineHeight' => 1.4,
        ];

        return [
            'version' => 1,
            'width' => self::WIDTH,
            'height' => self::HEIGHT,
            'background' => $bg,
            'layers' => $layers,
        ];
    }

    /**
     * Build a thank-you design JSON with category-appropriate gratitude message.
     */
    public static function buildThankYou(array $theme): array
    {
        $id = fn () => Str::uuid()->toString();

        $bg = $theme['background'];
        $primaryColor = $theme['primaryColor'];
        $secondaryColor = $theme['secondaryColor'] ?? $primaryColor;
        $textColor = $theme['textColor'];
        $titleFont = $theme['titleFont'] ?? 'Cinzel';
        $bodyFont = $theme['bodyFont'] ?? 'Noto Sans Ethiopic';
        $isFuneral = ($theme['is_funeral'] ?? false) === true;

        $layers = [];

        // Border
        $borderPadding = 45;
        $layers[] = [
            'id' => $id(),
            'type' => 'shape',
            'shape' => 'rect',
            'x' => $borderPadding,
            'y' => $borderPadding,
            'width' => self::WIDTH - ($borderPadding * 2),
            'height' => self::HEIGHT - ($borderPadding * 2),
            'stroke' => $secondaryColor,
            'strokeWidth' => $isFuneral ? 1.5 : 2,
            'fill' => 'transparent',
            'opacity' => $isFuneral ? 0.4 : 0.7,
            'locked' => true,
        ];

        // Header: "THANK YOU" or "ምስጋና"
        $layers[] = [
            'id' => $id(),
            'type' => 'text',
            'x' => 90,
            'y' => 240,
            'width' => 900,
            'height' => 130,
            'text' => $theme['thank_you_title'] ?? 'Thank You',
            'fontFamily' => $titleFont,
            'fontSize' => $theme['title_size'] ?? 72,
            'fontWeight' => 700,
            'fill' => $primaryColor,
            'align' => 'center',
            'lineHeight' => 1.2,
            'letterSpacing' => 2,
        ];

        // Recipient Line
        $layers[] = [
            'id' => $id(),
            'type' => 'text',
            'x' => 90,
            'y' => 410,
            'width' => 900,
            'height' => 50,
            'text' => 'Dearest {{guest_name}},',
            'fontFamily' => $bodyFont,
            'fontSize' => 30,
            'fontWeight' => 500,
            'fill' => $textColor,
            'align' => 'center',
            'lineHeight' => 1.2,
        ];

        // Gratitude message
        $layers[] = [
            'id' => $id(),
            'type' => 'text',
            'x' => 100,
            'y' => 520,
            'width' => 880,
            'height' => 280,
            'text' => $theme['thank_you_message'],
            'fontFamily' => $bodyFont,
            'fontSize' => 28,
            'fontWeight' => 400,
            'fill' => $textColor,
            'align' => 'center',
            'lineHeight' => 1.6,
        ];

        // Event reference
        $layers[] = [
            'id' => $id(),
            'type' => 'text',
            'x' => 100,
            'y' => 890,
            'width' => 880,
            'height' => 70,
            'text' => 'In celebration of {{event_title}}',
            'fontFamily' => $titleFont,
            'fontSize' => 28,
            'fontWeight' => 600,
            'fill' => $secondaryColor,
            'align' => 'center',
            'lineHeight' => 1.3,
        ];

        // Signature from hosts
        $layers[] = [
            'id' => $id(),
            'type' => 'text',
            'x' => 100,
            'y' => 1100,
            'width' => 880,
            'height' => 120,
            'text' => "With heartfelt appreciation,\n{{host_names}}",
            'fontFamily' => $titleFont,
            'fontSize' => 30,
            'fontWeight' => 600,
            'fill' => $primaryColor,
            'align' => 'center',
            'lineHeight' => 1.5,
        ];

        return [
            'version' => 1,
            'width' => self::WIDTH,
            'height' => self::HEIGHT,
            'background' => $bg,
            'layers' => $layers,
        ];
    }
}
