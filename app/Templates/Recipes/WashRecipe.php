<?php

namespace App\Templates\Recipes;

class WashRecipe extends AbstractRecipe
{
    public function getId(): string
    {
        return 'wash';
    }

    public function getName(): string
    {
        return 'Wash';
    }

    public function getDescription(): string
    {
        return 'Atmospheric full-bleed gradient wash with an ethereal low-opacity watermark emblem and grand scale typography.';
    }

    public function build(RecipeSpec $spec): array
    {
        $fonts = $spec->getFontRoles();
        $anchor = $spec->ornamentId ?: $this->defaultAnchorOrnament($spec->category);
        $divider = $spec->dividerOrnament ?: $this->defaultDividerOrnament($spec->category);

        $layers = [
            // 1. Non-text: Inset framing rule
            $this->makeBorderLayer('border-wash-rule', 'single-rule', 64, 64, 952, 1400, [
                'stroke' => '@secondary',
                'strokeWidth' => 1,
            ]),

            // 2. Non-text: Large central watermark
            $this->makeOrnamentLayer('ornament-watermark', $anchor, 270, 440, 540, 540, [
                'tint' => ['c1' => '@primary', 'c2' => '@secondary', 'c3' => '@accent'],
                'opacity' => 0.14,
            ]),

            // 3. Non-text: Delicate top crown flourish
            $this->makeOrnamentLayer('ornament-top-flourish', 'double-rule-ornament', 440, 112, 200, 48, [
                'tint' => ['c1' => '@primary', 'c2' => '@secondary', 'c3' => '@accent'],
            ]),

            // Text 1: Opening line
            $this->makeTextLayer(
                'text-opening',
                $spec->opening,
                120,
                200,
                840,
                56,
                $fonts['body'],
                32,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400', 'letterSpacing' => 1]
            ),

            // Text 2: Title
            $this->makeTextLayer(
                'text-title',
                $spec->title,
                112,
                264,
                856,
                200,
                $fonts['display'],
                96,
                $spec,
                ['fill' => '@primary', 'fontWeight' => '700', 'autoFit' => true]
            ),

            // Text 3: Host Names
            $this->makeTextLayer(
                'text-hosts',
                $spec->hostNames,
                120,
                472,
                840,
                48,
                $fonts['body'],
                28,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400']
            ),

            // Text 4: Invitation line
            $this->makeTextLayer(
                'text-invitation',
                $spec->invitation,
                120,
                528,
                840,
                80,
                $fonts['body'],
                34,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400']
            ),

            // 4. Non-text: Center divider
            $this->makeOrnamentLayer('ornament-divider', $divider, 412, 624, 256, 40, [
                'tint' => ['c1' => '@secondary', 'c2' => '@primary', 'c3' => '@accent'],
            ]),

            // Text 5: Details (Date, Time, Venue)
            $this->makeTextLayer(
                'text-details',
                $spec->details,
                120,
                680,
                840,
                224,
                $fonts['body'],
                32,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400', 'lineHeight' => 1.45]
            ),

            // Text 6: Closing line
            $this->makeTextLayer(
                'text-closing',
                $spec->closing,
                120,
                928,
                840,
                64,
                $fonts['script_or_accent'],
                32,
                $spec,
                ['fill' => '@primary', 'fontWeight' => '400']
            ),

            // Text 7: RSVP
            $this->makeTextLayer(
                'text-rsvp',
                $spec->rsvp,
                120,
                1008,
                840,
                64,
                $fonts['body'],
                30,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400']
            ),
        ];

        return $this->wrapDesign($spec, $layers, [
            'type' => 'gradient',
            'value' => [
                'type' => 'linear',
                'angle' => 180,
                'stops' => [
                    ['offset' => 0, 'color' => '@bg'],
                    ['offset' => 1, 'color' => '@surface'],
                ],
            ],
        ]);
    }
}
