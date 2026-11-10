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
        $dividerOrnament = $spec->dividerOrnament ?: 'geometric-divider';

        $layers = [
            // 0. Non-text: Outer architectural border
            $this->makeBorderLayer('border-main', 'single-rule', 48, 48, 984, 1432, [
                'stroke' => '@secondary',
                'strokeWidth' => 2,
                'opacity' => 0.6,
            ]),

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

            // 3. Non-text: Dividing ornament
            $this->makeOrnamentLayer('ornament-divider', $dividerOrnament, 128, 640, 240, 32, [
                'tint' => ['c1' => '@secondary', 'c2' => '@primary', 'c3' => '@accent'],
            ]),

            // Text 1: Opening / Prehead
            $this->makeTextLayer(
                'text-opening',
                $spec->opening,
                128,
                112,
                600,
                48,
                $fonts['body'],
                30,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '600', 'textAlign' => 'left', 'letterSpacing' => 1]
            ),

            // Text 2: Host Names
            $this->makeTextLayer(
                'text-hosts',
                $spec->hostNames,
                128,
                168,
                600,
                48,
                $fonts['body'],
                28,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400', 'textAlign' => 'left']
            ),

            // Text 3: Huge Headline Title
            $this->makeTextLayer(
                'text-title',
                $spec->title,
                128,
                224,
                824,
                400,
                $fonts['display'],
                96,
                $spec,
                ['fill' => '@primary', 'fontWeight' => '700', 'textAlign' => 'left', 'autoFit' => true]
            ),

            // Text 4: Invitation line
            $this->makeTextLayer(
                'text-invitation',
                $spec->invitation,
                128,
                656,
                824,
                72,
                $fonts['body'],
                32,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400', 'textAlign' => 'left']
            ),

            // 4. Non-text: Small corner accent block
            $this->makeShapeLayer('shape-corner-accent', 'rect', 960, 1400, 24, 24, [
                'fill' => '@secondary',
                'stroke' => '@secondary',
                'strokeWidth' => 0,
            ]),

            // Text 5: Bold details column
            $this->makeTextLayer(
                'text-details',
                $spec->details,
                128,
                744,
                824,
                240,
                $fonts['body'],
                32,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '600', 'textAlign' => 'left', 'lineHeight' => 1.45]
            ),

            // Text 6: Closing line
            $this->makeTextLayer(
                'text-closing',
                $spec->closing,
                128,
                1000,
                824,
                64,
                $fonts['script_or_accent'],
                30,
                $spec,
                ['fill' => '@primary', 'fontWeight' => '400', 'textAlign' => 'left']
            ),

            // Text 7: RSVP line
            $this->makeTextLayer(
                'text-rsvp',
                $spec->rsvp,
                128,
                1080,
                824,
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
