<?php

namespace App\Templates\Recipes;

class ArchWindowRecipe extends AbstractRecipe
{
    public function getId(): string
    {
        return 'arch-window';
    }

    public function getName(): string
    {
        return 'Arch Window';
    }

    public function getDescription(): string
    {
        return 'Cathedral or sanctuary arch silhouette holding an inner paper sanctuary crowned by floral or faith motifs.';
    }

    public function build(RecipeSpec $spec): array
    {
        $fonts = $spec->getFontRoles();
        $anchor = $spec->ornamentId ?: $this->defaultAnchorOrnament($spec->category);
        $divider = $spec->dividerOrnament ?: $this->defaultDividerOrnament($spec->category);

        $layers = [
            // 1. Non-text: Outer arch panel
            $this->makeShapeLayer('shape-arch-panel', 'arch', 112, 120, 856, 1288, [
                'fill' => '@surface',
                'stroke' => '@secondary',
                'strokeWidth' => 2,
            ]),

            // 2. Non-text: Crown ornament at peak
            $this->makeOrnamentLayer('ornament-crown', $anchor, 440, 96, 200, 128, [
                'tint' => ['c1' => '@primary', 'c2' => '@secondary', 'c3' => '@accent'],
            ]),

            // Text 1: Opening line
            $this->makeTextLayer(
                'text-opening',
                $spec->opening,
                144,
                240,
                792,
                56,
                $fonts['body'],
                32,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400']
            ),

            // Text 2: Title
            $this->makeTextLayer(
                'text-title',
                $spec->title,
                144,
                304,
                792,
                168,
                $fonts['display'],
                84,
                $spec,
                ['fill' => '@primary', 'fontWeight' => '700', 'autoFit' => true]
            ),

            // Text 3: Host Names
            $this->makeTextLayer(
                'text-hosts',
                $spec->hostNames,
                144,
                480,
                792,
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
                144,
                536,
                792,
                80,
                $fonts['body'],
                32,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400']
            ),

            // 3. Non-text: Center divider
            $this->makeOrnamentLayer('ornament-divider', $divider, 420, 632, 240, 40, [
                'tint' => ['c1' => '@secondary', 'c2' => '@primary', 'c3' => '@accent'],
            ]),

            // Text 5: Details
            $this->makeTextLayer(
                'text-details',
                $spec->details,
                144,
                688,
                792,
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
                144,
                928,
                792,
                64,
                $fonts['script_or_accent'],
                32,
                $spec,
                ['fill' => '@primary', 'fontWeight' => '400']
            ),

            // 4. Non-text: Bottom flourish
            $this->makeOrnamentLayer('ornament-bottom', 'double-rule-ornament', 440, 1008, 200, 36, [
                'tint' => ['c1' => '@secondary', 'c2' => '@primary', 'c3' => '@accent'],
            ]),

            // Text 7: RSVP
            $this->makeTextLayer(
                'text-rsvp',
                $spec->rsvp,
                144,
                1064,
                792,
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
