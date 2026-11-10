<?php

namespace App\Templates\Recipes;

class CollageRecipe extends AbstractRecipe
{
    public function getId(): string
    {
        return 'collage';
    }

    public function getName(): string
    {
        return 'Collage';
    }

    public function getDescription(): string
    {
        return 'Playful layered scrap-art aesthetic with washi tape accents, floating confetti, and exuberant celebratory typography.';
    }

    public function build(RecipeSpec $spec): array
    {
        $fonts = $spec->getFontRoles();
        $confetti = $spec->ornamentId ?: match ($spec->category) {
            'birthday', 'baby-shower' => 'confetti-scatter-a',
            default => 'sparkle-trio',
        };

        $layers = [
            // 1. Non-text: Tilted backdrop layered card
            $this->makeShapeLayer('shape-card-backdrop', 'rect', 104, 104, 872, 1320, [
                'fill' => '@surface',
                'stroke' => '@secondary',
                'strokeWidth' => 2,
                'cornerRadius' => 16,
            ]),

            // 2. Non-text: Washi tape strip at the top
            $this->makeShapeLayer('shape-tape-top', 'rect', 440, 84, 200, 36, [
                'fill' => '@accent',
                'stroke' => '@secondary',
                'strokeWidth' => 1,
                'opacity' => 0.8,
            ]),

            // 3. Non-text: Confetti scatter top-left
            $this->makeOrnamentLayer('ornament-confetti-top', $confetti, 120, 144, 200, 160, [
                'tint' => ['c1' => '@primary', 'c2' => '@secondary', 'c3' => '@accent'],
            ]),

            // 4. Non-text: Confetti scatter bottom-right
            $this->makeOrnamentLayer('ornament-confetti-bottom', $confetti, 760, 1160, 200, 160, [
                'tint' => ['c1' => '@secondary', 'c2' => '@primary', 'c3' => '@accent'],
                'flipX' => true,
                'flipY' => true,
            ]),

            // Text 1: Opening line
            $this->makeTextLayer(
                'text-opening',
                $spec->opening,
                144,
                240,
                792,
                64,
                $fonts['script_or_accent'],
                34,
                $spec,
                ['fill' => '@primary', 'fontWeight' => '400']
            ),

            // Text 2: Title (Festive display)
            $this->makeTextLayer(
                'text-title',
                $spec->title,
                144,
                320,
                792,
                200,
                $fonts['display'],
                88,
                $spec,
                ['fill' => '@primary', 'fontWeight' => '700', 'autoFit' => true]
            ),

            // Text 3: Invitation line
            $this->makeTextLayer(
                'text-invitation',
                $spec->invitation,
                144,
                536,
                792,
                112,
                $fonts['body'],
                34,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400']
            ),

            // 5. Non-text: Center divider star/flourish
            $this->makeOrnamentLayer('ornament-center-flourish', 'double-rule-ornament', 440, 664, 200, 48, [
                'tint' => ['c1' => '@primary', 'c2' => '@secondary', 'c3' => '@accent'],
            ]),

            // Text 4: Details
            $this->makeTextLayer(
                'text-details',
                $spec->details,
                144,
                736,
                792,
                240,
                $fonts['body'],
                32,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400', 'lineHeight' => 1.45]
            ),

            // Text 5: Closing line
            $this->makeTextLayer(
                'text-closing',
                $spec->closing,
                144,
                1000,
                792,
                72,
                $fonts['script_or_accent'],
                32,
                $spec,
                ['fill' => '@primary', 'fontWeight' => '400']
            ),

            // Text 6: RSVP
            $this->makeTextLayer(
                'text-rsvp',
                $spec->rsvp,
                144,
                1160,
                600,
                80,
                $fonts['body'],
                30,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400', 'textAlign' => 'left']
            ),
        ];

        return $this->wrapDesign($spec, $layers);
    }
}
