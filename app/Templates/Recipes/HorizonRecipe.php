<?php

namespace App\Templates\Recipes;

class HorizonRecipe extends AbstractRecipe
{
    public function getId(): string
    {
        return 'horizon';
    }

    public function getName(): string
    {
        return 'Horizon';
    }

    public function getDescription(): string
    {
        return 'Panoramic illustrated scenery dominating the upper canvas with formal reception details on an anchored lower card.';
    }

    public function build(RecipeSpec $spec): array
    {
        $fonts = $spec->getFontRoles();
        $sceneAnchor = $spec->ornamentId ?: match ($spec->category) {
            'birthday' => 'balloon-arch',
            'baby-shower' => 'cloud-trio',
            'housewarming' => 'house-line-roof',
            'wedding' => 'floral-arch-wreath',
            'graduation' => 'crest-rays',
            default => 'laurel-wreath-pair',
        };
        $divider = $this->defaultDividerOrnament($spec->category);

        $layers = [
            // 1. Non-text: Upper scenic anchor ornament
            $this->makeOrnamentLayer('ornament-horizon-sky', $sceneAnchor, 280, 96, 520, 220, [
                'tint' => ['c1' => '@primary', 'c2' => '@secondary', 'c3' => '@accent'],
            ]),

            // 2. Non-text: Flanking sparkle/sprig left
            $this->makeOrnamentLayer('ornament-flank-left', 'sparkle-trio', 120, 160, 120, 120, [
                'tint' => ['c1' => '@secondary', 'c2' => '@primary', 'c3' => '@accent'],
            ]),

            // 3. Non-text: Flanking sparkle/sprig right
            $this->makeOrnamentLayer('ornament-flank-right', 'sparkle-trio', 840, 160, 120, 120, [
                'tint' => ['c1' => '@secondary', 'c2' => '@primary', 'c3' => '@accent'],
                'flipX' => true,
            ]),

            // Text 1: Opening line in horizon sky
            $this->makeTextLayer(
                'text-opening',
                $spec->opening,
                120,
                336,
                840,
                56,
                $fonts['script_or_accent'],
                32,
                $spec,
                ['fill' => '@primary', 'fontWeight' => '400']
            ),

            // Text 2: Title centered above paper panel
            $this->makeTextLayer(
                'text-title',
                $spec->title,
                112,
                408,
                856,
                192,
                $fonts['display'],
                88,
                $spec,
                ['fill' => '@primary', 'fontWeight' => '700', 'autoFit' => true]
            ),

            // 4. Non-text: Bottom paper card panel
            $this->makeShapeLayer('shape-bottom-panel', 'rect', 96, 624, 888, 768, [
                'fill' => '@surface',
                'stroke' => '@secondary',
                'strokeWidth' => 2,
                'cornerRadius' => 20,
            ]),

            // Text 3: Invitation line on paper panel
            $this->makeTextLayer(
                'text-invitation',
                $spec->invitation,
                120,
                656,
                840,
                96,
                $fonts['body'],
                34,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400']
            ),

            // 5. Non-text: Panel divider ornament
            $this->makeOrnamentLayer('ornament-panel-divider', $divider, 412, 768, 256, 48, [
                'tint' => ['c1' => '@secondary', 'c2' => '@primary', 'c3' => '@accent'],
            ]),

            // Text 4: Details
            $this->makeTextLayer(
                'text-details',
                $spec->details,
                120,
                832,
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
                1096,
                840,
                72,
                $fonts['script_or_accent'],
                32,
                $spec,
                ['fill' => '@primary', 'fontWeight' => '400']
            ),

            // Text 6: RSVP
            $this->makeTextLayer(
                'text-rsvp',
                $spec->rsvp,
                120,
                1240,
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
