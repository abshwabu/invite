<?php

namespace App\Templates\Recipes;

class FramedCenterRecipe extends AbstractRecipe
{
    public function getId(): string
    {
        return 'framed-center';
    }

    public function getName(): string
    {
        return 'Framed Center';
    }

    public function getDescription(): string
    {
        return 'Architectural double border with crowned anchor ornament and elegant centered typographic stack.';
    }

    public function build(RecipeSpec $spec): array
    {
        $fonts = $spec->getFontRoles();
        $anchor = $spec->ornamentId ?: $this->defaultAnchorOrnament($spec->category);
        $divider = $spec->dividerOrnament ?: $this->defaultDividerOrnament($spec->category);
        $borderStyle = $spec->borderStyle ?: 'double-rule';

        $layers = [
            // 1. Non-text: Border
            $this->makeBorderLayer('border-main', $borderStyle, 48, 48, 984, 1432, [
                'stroke' => '@primary',
                'strokeWidth' => 2,
                'inset' => 0,
            ]),

            // 2. Non-text: Top Crown Ornament
            $this->makeOrnamentLayer('ornament-top', $anchor, 440, 112, 200, 136, [
                'tint' => ['c1' => '@primary', 'c2' => '@secondary', 'c3' => '@accent'],
            ]),

            // Text 1: Opening line
            $this->makeTextLayer(
                'text-opening',
                $spec->opening,
                120,
                264,
                840,
                56,
                $fonts['body'],
                32,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400', 'letterSpacing' => 1]
            ),

            // Text 2: Event Title (Display)
            $this->makeTextLayer(
                'text-title',
                $spec->title,
                112,
                328,
                856,
                168,
                $fonts['display'],
                88,
                $spec,
                ['fill' => '@primary', 'fontWeight' => '700', 'autoFit' => true]
            ),

            // Text 3: Host Names
            $this->makeTextLayer(
                'text-hosts',
                $spec->hostNames,
                120,
                504,
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
                560,
                840,
                80,
                $fonts['body'],
                32,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400']
            ),

            // 3. Non-text: Center Divider Ornament
            $this->makeOrnamentLayer('ornament-divider', $divider, 412, 656, 256, 40, [
                'tint' => ['c1' => '@secondary', 'c2' => '@primary', 'c3' => '@accent'],
            ]),

            // Text 5: Details (Date, Time, Venue)
            $this->makeTextLayer(
                'text-details',
                $spec->details,
                120,
                712,
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
                952,
                840,
                64,
                $fonts['script_or_accent'],
                32,
                $spec,
                ['fill' => '@primary', 'fontWeight' => '400']
            ),

            // 4. Non-text: Bottom flourish
            $this->makeOrnamentLayer('ornament-bottom', 'double-rule-ornament', 440, 1032, 200, 36, [
                'tint' => ['c1' => '@secondary', 'c2' => '@primary', 'c3' => '@accent'],
            ]),

            // Text 7: RSVP
            $this->makeTextLayer(
                'text-rsvp',
                $spec->rsvp,
                120,
                1088,
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
