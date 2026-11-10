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
        $divider = $this->defaultDividerOrnament($spec->category);

        $layers = [
            // 1. Non-text: Outer arch panel
            $this->makeShapeLayer('shape-arch-panel', 'arch', 112, 120, 856, 1288, [
                'fill' => '@surface',
                'stroke' => '@secondary',
                'strokeWidth' => 2,
            ]),

            // 2. Non-text: Crown ornament at the peak of the arch
            $this->makeOrnamentLayer('ornament-crown', $anchor, 440, 96, 200, 136, [
                'tint' => ['c1' => '@primary', 'c2' => '@secondary', 'c3' => '@accent'],
            ]),

            // Text 1: Opening line
            $this->makeTextLayer(
                'text-opening',
                $spec->opening,
                144,
                248,
                792,
                64,
                $fonts['body'],
                32,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400']
            ),

            // Text 2: Title (Grand display)
            $this->makeTextLayer(
                'text-title',
                $spec->title,
                144,
                328,
                792,
                184,
                $fonts['display'],
                84,
                $spec,
                ['fill' => '@primary', 'fontWeight' => '700', 'autoFit' => true]
            ),

            // Text 3: Invitation line
            $this->makeTextLayer(
                'text-invitation',
                $spec->invitation,
                144,
                528,
                792,
                112,
                $fonts['body'],
                34,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400']
            ),

            // 3. Non-text: Center divider
            $this->makeOrnamentLayer('ornament-divider', $divider, 420, 664, 240, 48, [
                'tint' => ['c1' => '@secondary', 'c2' => '@primary', 'c3' => '@accent'],
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
                34,
                $spec,
                ['fill' => '@primary', 'fontWeight' => '400']
            ),

            // 4. Non-text: Bottom flourish
            $this->makeOrnamentLayer('ornament-bottom', 'double-rule-ornament', 440, 1096, 200, 40, [
                'tint' => ['c1' => '@secondary', 'c2' => '@primary', 'c3' => '@accent'],
            ]),

            // Text 6: RSVP
            $this->makeTextLayer(
                'text-rsvp',
                $spec->rsvp,
                144,
                1160,
                792,
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
