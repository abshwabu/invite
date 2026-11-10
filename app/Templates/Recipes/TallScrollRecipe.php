<?php

namespace App\Templates\Recipes;

class TallScrollRecipe extends AbstractRecipe
{
    public function getId(): string
    {
        return 'tall-scroll';
    }

    public function getName(): string
    {
        return 'Tall Scroll';
    }

    public function getDescription(): string
    {
        return 'Elongated editorial parchment format featuring a vertical spine with trailing botanical or ornamental accents beside a right-aligned text column.';
    }

    public function build(RecipeSpec $spec): array
    {
        $fonts = $spec->getFontRoles();
        $divider = $spec->dividerOrnament ?: $this->defaultDividerOrnament($spec->category);

        $layers = [
            // 1. Non-text: Left vertical dividing spine
            $this->makeShapeLayer('shape-vertical-spine', 'line', 248, 96, 4, 1336, [
                'stroke' => '@secondary',
                'strokeWidth' => 2,
            ]),

            // 2. Non-text: Left column vertical floral vine
            $this->makeOrnamentLayer('ornament-left-vine', 'leaf-chain', 112, 120, 112, 440, [
                'tint' => ['c1' => '@primary', 'c2' => '@secondary', 'c3' => '@accent'],
            ]),

            // 3. Non-text: Left column badge seal
            $this->makeShapeLayer('shape-left-badge', 'circle', 112, 608, 112, 112, [
                'fill' => '@surface',
                'stroke' => '@primary',
                'strokeWidth' => 2,
            ]),

            // 4. Non-text: Left column lower vine
            $this->makeOrnamentLayer('ornament-left-lower-vine', 'leaf-chain', 112, 768, 112, 440, [
                'tint' => ['c1' => '@primary', 'c2' => '@secondary', 'c3' => '@accent'],
                'flipY' => true,
            ]),

            // Text 1: Opening line
            $this->makeTextLayer(
                'text-opening',
                $spec->opening,
                280,
                112,
                704,
                48,
                $fonts['body'],
                32,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400', 'textAlign' => 'left']
            ),

            // Text 2: Title
            $this->makeTextLayer(
                'text-title',
                $spec->title,
                280,
                168,
                704,
                200,
                $fonts['display'],
                88,
                $spec,
                ['fill' => '@primary', 'fontWeight' => '700', 'textAlign' => 'left', 'autoFit' => true]
            ),

            // Text 3: Host Names
            $this->makeTextLayer(
                'text-hosts',
                $spec->hostNames,
                280,
                376,
                704,
                48,
                $fonts['body'],
                28,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400', 'textAlign' => 'left']
            ),

            // Text 4: Invitation line
            $this->makeTextLayer(
                'text-invitation',
                $spec->invitation,
                280,
                432,
                704,
                72,
                $fonts['body'],
                32,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400', 'textAlign' => 'left']
            ),

            // 5. Non-text: Horizontal accent divider in right column
            $this->makeOrnamentLayer('ornament-right-divider', $divider, 280, 520, 256, 40, [
                'tint' => ['c1' => '@secondary', 'c2' => '@primary', 'c3' => '@accent'],
            ]),

            // Text 5: Details (Date, Time, Venue)
            $this->makeTextLayer(
                'text-details',
                $spec->details,
                280,
                576,
                704,
                224,
                $fonts['body'],
                30,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400', 'textAlign' => 'left', 'lineHeight' => 1.45]
            ),

            // Text 6: Closing line
            $this->makeTextLayer(
                'text-closing',
                $spec->closing,
                280,
                816,
                704,
                64,
                $fonts['script_or_accent'],
                32,
                $spec,
                ['fill' => '@primary', 'fontWeight' => '400', 'textAlign' => 'left']
            ),

            // Text 7: RSVP
            $this->makeTextLayer(
                'text-rsvp',
                $spec->rsvp,
                280,
                896,
                704,
                64,
                $fonts['body'],
                30,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400', 'textAlign' => 'left']
            ),
        ];

        return $this->wrapDesign($spec, $layers);
    }
}
