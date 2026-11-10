<?php

namespace App\Templates\Recipes;

class SplitBandRecipe extends AbstractRecipe
{
    public function getId(): string
    {
        return 'split-band';
    }

    public function getName(): string
    {
        return 'Split Band';
    }

    public function getDescription(): string
    {
        return 'Dramatic 35% solid or textured top color band hosting an anchor emblem, with clean structured content below.';
    }

    public function build(RecipeSpec $spec): array
    {
        $fonts = $spec->getFontRoles();
        $anchor = $spec->ornamentId ?: $this->defaultAnchorOrnament($spec->category);
        $divider = $spec->dividerOrnament ?: $this->defaultDividerOrnament($spec->category);

        $layers = [
            // 1. Non-text: 35% Top Color Band Shape
            $this->makeShapeLayer('shape-top-band', 'rect', 0, 0, 1080, 480, [
                'fill' => '@surface',
                'stroke' => '@secondary',
                'strokeWidth' => 0,
            ]),

            // 2. Non-text: Centerpiece Ornament inside top band
            $this->makeOrnamentLayer('ornament-band-center', $anchor, 412, 112, 256, 200, [
                'tint' => ['c1' => '@primary', 'c2' => '@secondary', 'c3' => '@accent'],
            ]),

            // 3. Non-text: Dividing accent rule
            $this->makeShapeLayer('shape-divider-line', 'line', 96, 480, 888, 4, [
                'stroke' => '@primary',
                'strokeWidth' => 3,
            ]),

            // Text 1: Opening line in band
            $this->makeTextLayer(
                'text-opening',
                $spec->opening,
                112,
                336,
                856,
                56,
                $fonts['script_or_accent'],
                32,
                $spec,
                ['fill' => '@primary', 'fontWeight' => '400']
            ),

            // Text 2: Host Names in band
            $this->makeTextLayer(
                'text-hosts',
                $spec->hostNames,
                112,
                400,
                856,
                48,
                $fonts['body'],
                28,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400']
            ),

            // Text 3: Title (Big display below band)
            $this->makeTextLayer(
                'text-title',
                $spec->title,
                112,
                512,
                856,
                168,
                $fonts['display'],
                88,
                $spec,
                ['fill' => '@primary', 'fontWeight' => '700', 'autoFit' => true]
            ),

            // Text 4: Invitation line
            $this->makeTextLayer(
                'text-invitation',
                $spec->invitation,
                120,
                688,
                840,
                80,
                $fonts['body'],
                32,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400']
            ),

            // 4. Non-text: Bottom decorative divider
            $this->makeOrnamentLayer('ornament-divider', $divider, 412, 784, 256, 40, [
                'tint' => ['c1' => '@secondary', 'c2' => '@primary', 'c3' => '@accent'],
            ]),

            // Text 5: Details
            $this->makeTextLayer(
                'text-details',
                $spec->details,
                120,
                840,
                840,
                224,
                $fonts['body'],
                30,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400', 'lineHeight' => 1.45]
            ),

            // Text 6: Closing line
            $this->makeTextLayer(
                'text-closing',
                $spec->closing,
                120,
                1080,
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
                1160,
                840,
                64,
                $fonts['body'],
                30,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400']
            ),
        ];

        return $this->wrapDesign($spec, $layers);
    }
}
