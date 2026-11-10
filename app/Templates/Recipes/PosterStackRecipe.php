<?php

namespace App\Templates\Recipes;

class PosterStackRecipe extends AbstractRecipe
{
    public function getId(): string
    {
        return 'poster-stack';
    }

    public function getName(): string
    {
        return 'Poster Stack';
    }

    public function getDescription(): string
    {
        return 'Bold modernist asymmetric Swiss layout with dominant left-aligned headline and structured architectural column.';
    }

    public function build(RecipeSpec $spec): array
    {
        $fonts = $spec->getFontRoles();
        $geometricOrnament = $spec->ornamentId ?: match ($spec->category) {
            'corporate', 'graduation' => 'line-grid-field',
            'birthday' => 'starburst',
            'wedding', 'anniversary' => 'geometric-divider',
            default => 'geo-triangles-corner',
        };

        $layers = [
            // 1. Non-text: Left vertical accent stripe
            $this->makeShapeLayer('shape-left-accent', 'rect', 96, 96, 8, 1336, [
                'fill' => '@primary',
                'stroke' => '@primary',
                'strokeWidth' => 0,
            ]),

            // 2. Non-text: Geometric emblem in top-right
            $this->makeOrnamentLayer('ornament-top-right', $geometricOrnament, 752, 96, 232, 232, [
                'tint' => ['c1' => '@primary', 'c2' => '@secondary', 'c3' => '@accent'],
            ]),

            // 3. Non-text: Horizontal dividing rule
            $this->makeShapeLayer('shape-divider-line', 'line', 128, 640, 856, 4, [
                'stroke' => '@secondary',
                'strokeWidth' => 2,
            ]),

            // Text 1: Opening / Prehead (Left-aligned)
            $this->makeTextLayer(
                'text-opening',
                $spec->opening,
                128,
                112,
                600,
                56,
                $fonts['body'],
                30,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '600', 'textAlign' => 'left', 'letterSpacing' => 1]
            ),

            // Text 2: Huge Headline Title (Left-aligned, 100px)
            $this->makeTextLayer(
                'text-title',
                $spec->title,
                128,
                184,
                824,
                432,
                $fonts['display'],
                100,
                $spec,
                ['fill' => '@primary', 'fontWeight' => '700', 'textAlign' => 'left', 'autoFit' => true]
            ),

            // Text 3: Invitation line
            $this->makeTextLayer(
                'text-invitation',
                $spec->invitation,
                128,
                664,
                824,
                96,
                $fonts['body'],
                34,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400', 'textAlign' => 'left']
            ),

            // 4. Non-text: Small corner accent block
            $this->makeShapeLayer('shape-corner-accent', 'rect', 960, 1400, 24, 24, [
                'fill' => '@secondary',
                'stroke' => '@secondary',
                'strokeWidth' => 0,
            ]),

            // Text 4: Bold details column
            $this->makeTextLayer(
                'text-details',
                $spec->details,
                128,
                784,
                824,
                264,
                $fonts['body'],
                34,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '600', 'textAlign' => 'left', 'lineHeight' => 1.45]
            ),

            // Text 5: Closing line
            $this->makeTextLayer(
                'text-closing',
                $spec->closing,
                128,
                1080,
                824,
                72,
                $fonts['script_or_accent'],
                32,
                $spec,
                ['fill' => '@primary', 'fontWeight' => '400', 'textAlign' => 'left']
            ),

            // Text 6: RSVP line
            $this->makeTextLayer(
                'text-rsvp',
                $spec->rsvp,
                128,
                1216,
                824,
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
