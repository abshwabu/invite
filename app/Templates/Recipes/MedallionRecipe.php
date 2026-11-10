<?php

namespace App\Templates\Recipes;

class MedallionRecipe extends AbstractRecipe
{
    public function getId(): string
    {
        return 'medallion';
    }

    public function getName(): string
    {
        return 'Medallion';
    }

    public function getDescription(): string
    {
        return 'A noble circular medallion seal cradling the event insignia, floating gracefully above a structured details panel.';
    }

    protected function medallionOrnamentForCategory(string $category): string
    {
        return match ($category) {
            'religious-ethiopian' => 'tilet-medallion',
            'corporate', 'graduation' => 'badge-seal',
            'anniversary' => 'numeral-medallion-frame',
            'religious-islamic' => 'star-12-rosette',
            'birthday' => 'number-frame-circle',
            default => 'wreath-round-eucalyptus',
        };
    }

    public function build(RecipeSpec $spec): array
    {
        $fonts = $spec->getFontRoles();
        $medallionOrnament = $spec->ornamentId ?: $this->medallionOrnamentForCategory($spec->category);
        $divider = $this->defaultDividerOrnament($spec->category);

        $layers = [
            // 1. Non-text: Medallion circular background surface
            $this->makeShapeLayer('shape-medallion-bg', 'circle', 360, 160, 360, 360, [
                'fill' => '@surface',
                'stroke' => '@secondary',
                'strokeWidth' => 3,
            ]),

            // 2. Non-text: Medallion framing ornament
            $this->makeOrnamentLayer('ornament-medallion-frame', $medallionOrnament, 340, 140, 400, 400, [
                'tint' => ['c1' => '@primary', 'c2' => '@secondary', 'c3' => '@accent'],
            ]),

            // Text 1: Opening line (floating gracefully above medallion)
            $this->makeTextLayer(
                'text-opening',
                $spec->opening,
                112,
                96,
                856,
                48,
                $fonts['body'],
                30,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400', 'letterSpacing' => 1]
            ),

            // Text 2: Title centered within the medallion
            $this->makeTextLayer(
                'text-title',
                $spec->title,
                380,
                264,
                320,
                152,
                $fonts['display'],
                64,
                $spec,
                ['fill' => '@primary', 'fontWeight' => '700', 'autoFit' => true]
            ),

            // Text 3: Invitation line below medallion
            $this->makeTextLayer(
                'text-invitation',
                $spec->invitation,
                120,
                576,
                840,
                104,
                $fonts['body'],
                34,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400']
            ),

            // 3. Non-text: Divider ornament
            $this->makeOrnamentLayer('ornament-divider', $divider, 412, 704, 256, 48, [
                'tint' => ['c1' => '@secondary', 'c2' => '@primary', 'c3' => '@accent'],
            ]),

            // Text 4: Details
            $this->makeTextLayer(
                'text-details',
                $spec->details,
                120,
                776,
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
                1040,
                840,
                72,
                $fonts['script_or_accent'],
                34,
                $spec,
                ['fill' => '@primary', 'fontWeight' => '400']
            ),

            // 4. Non-text: Bottom accent flourish
            $this->makeOrnamentLayer('ornament-bottom', 'double-rule-ornament', 440, 1136, 200, 40, [
                'tint' => ['c1' => '@secondary', 'c2' => '@primary', 'c3' => '@accent'],
            ]),

            // Text 6: RSVP
            $this->makeTextLayer(
                'text-rsvp',
                $spec->rsvp,
                120,
                1208,
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
