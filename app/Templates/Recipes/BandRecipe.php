<?php

namespace App\Templates\Recipes;

class BandRecipe extends AbstractRecipe
{
    public function getId(): string
    {
        return 'band';
    }

    public function getName(): string
    {
        return 'Band';
    }

    public function getDescription(): string
    {
        return 'Imperial ceremonial layout framed by authentic woven Ethiopian Tilet or geometric bands across top and bottom margins.';
    }

    protected function bandOrnamentForCategory(string $category): string
    {
        return match ($category) {
            'corporate', 'graduation' => 'diagonal-bands',
            'religious-islamic' => 'geometric-divider',
            default => 'tilet-band-straight',
        };
    }

    public function build(RecipeSpec $spec): array
    {
        $fonts = $spec->getFontRoles();
        $band = $spec->ornamentId ?: $this->bandOrnamentForCategory($spec->category);
        $divider = $this->defaultDividerOrnament($spec->category);

        $layers = [
            // 1. Non-text: Top woven pattern band
            $this->makeOrnamentLayer('ornament-band-top', $band, 96, 96, 888, 72, [
                'tint' => ['c1' => '@primary', 'c2' => '@secondary', 'c3' => '@accent'],
            ]),

            // 2. Non-text: Bottom woven pattern band
            $this->makeOrnamentLayer('ornament-band-bottom', $band, 96, 1360, 888, 72, [
                'tint' => ['c1' => '@primary', 'c2' => '@secondary', 'c3' => '@accent'],
                'flipY' => true,
            ]),

            // 3. Non-text: Top delicate divider
            $this->makeOrnamentLayer('ornament-divider-top', 'double-rule-ornament', 440, 192, 200, 36, [
                'tint' => ['c1' => '@secondary', 'c2' => '@primary', 'c3' => '@accent'],
            ]),

            // Text 1: Opening line
            $this->makeTextLayer(
                'text-opening',
                $spec->opening,
                120,
                256,
                840,
                56,
                $fonts['body'],
                32,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400', 'letterSpacing' => 1]
            ),

            // Text 2: Title (Regal Display)
            $this->makeTextLayer(
                'text-title',
                $spec->title,
                112,
                328,
                856,
                192,
                $fonts['display'],
                88,
                $spec,
                ['fill' => '@primary', 'fontWeight' => '700', 'autoFit' => true]
            ),

            // Text 3: Invitation line
            $this->makeTextLayer(
                'text-invitation',
                $spec->invitation,
                120,
                536,
                840,
                104,
                $fonts['body'],
                34,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400']
            ),

            // 4. Non-text: Center decorative emblem
            $this->makeOrnamentLayer('ornament-center-emblem', $divider, 412, 656, 256, 48, [
                'tint' => ['c1' => '@secondary', 'c2' => '@primary', 'c3' => '@accent'],
            ]),

            // Text 4: Details
            $this->makeTextLayer(
                'text-details',
                $spec->details,
                120,
                728,
                840,
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
                120,
                992,
                840,
                72,
                $fonts['script_or_accent'],
                34,
                $spec,
                ['fill' => '@primary', 'fontWeight' => '400']
            ),

            // 5. Non-text: Bottom divider
            $this->makeOrnamentLayer('ornament-divider-bottom', 'double-rule-ornament', 440, 1088, 200, 36, [
                'tint' => ['c1' => '@secondary', 'c2' => '@primary', 'c3' => '@accent'],
            ]),

            // Text 6: RSVP
            $this->makeTextLayer(
                'text-rsvp',
                $spec->rsvp,
                120,
                1160,
                840,
                80,
                $fonts['body'],
                30,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400']
            ),
        ];

        return $this->wrapDesign($spec, $layers);
    }
}
