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
        $divider = $this->defaultDividerOrnament($spec->category);

        $layers = [
            // 1. Non-text: Left vertical dividing spine
            $this->makeShapeLayer('shape-vertical-spine', 'line', 248, 96, 4, 1336, [
                'stroke' => '@secondary',
                'strokeWidth' => 2,
            ]),

            // 2. Non-text: Left column vertical floral/motif vine
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

            // Text 1: Opening line (Right column)
            $this->makeTextLayer(
                'text-opening',
                $spec->opening,
                280,
                128,
                704,
                56,
                $fonts['body'],
                32,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400', 'textAlign' => 'left']
            ),

            // Text 2: Title (Right column)
            $this->makeTextLayer(
                'text-title',
                $spec->title,
                280,
                200,
                704,
                240,
                $fonts['display'],
                88,
                $spec,
                ['fill' => '@primary', 'fontWeight' => '700', 'textAlign' => 'left', 'autoFit' => true]
            ),

            // Text 3: Invitation line (Right column)
            $this->makeTextLayer(
                'text-invitation',
                $spec->invitation,
                280,
                456,
                704,
                112,
                $fonts['body'],
                34,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400', 'textAlign' => 'left']
            ),

            // 5. Non-text: Horizontal accent divider in right column
            $this->makeOrnamentLayer('ornament-right-divider', $divider, 280, 584, 256, 48, [
                'tint' => ['c1' => '@secondary', 'c2' => '@primary', 'c3' => '@accent'],
            ]),

            // Text 4: Details (Right column)
            $this->makeTextLayer(
                'text-details',
                $spec->details,
                280,
                656,
                704,
                264,
                $fonts['body'],
                32,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400', 'textAlign' => 'left', 'lineHeight' => 1.45]
            ),

            // Text 5: Closing line (Right column)
            $this->makeTextLayer(
                'text-closing',
                $spec->closing,
                280,
                944,
                704,
                72,
                $fonts['script_or_accent'],
                34,
                $spec,
                ['fill' => '@primary', 'fontWeight' => '400', 'textAlign' => 'left']
            ),

            // Text 6: RSVP (Right column)
            $this->makeTextLayer(
                'text-rsvp',
                $spec->rsvp,
                280,
                1080,
                704,
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
