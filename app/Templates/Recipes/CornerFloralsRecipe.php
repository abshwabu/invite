<?php

namespace App\Templates\Recipes;

class CornerFloralsRecipe extends AbstractRecipe
{
    public function getId(): string
    {
        return 'corner-florals';
    }

    public function getName(): string
    {
        return 'Corner Florals';
    }

    public function getDescription(): string
    {
        return 'Dramatic corner sprays in two opposite corners creating dynamic diagonal balance with pure central text breathing space.';
    }

    protected function cornerOrnamentForCategory(string $category): string
    {
        return match ($category) {
            'religious-ethiopian' => 'tilet-corner-01',
            'religious-islamic' => 'arabesque-corner',
            'corporate', 'graduation' => 'geo-triangles-corner',
            'birthday', 'baby-shower' => 'bunting-pastel',
            default => 'floral-corner-garden',
        };
    }

    public function build(RecipeSpec $spec): array
    {
        $fonts = $spec->getFontRoles();
        $corner = $spec->cornerOrnament ?: ($spec->ornamentId ?: $this->cornerOrnamentForCategory($spec->category));
        $divider = $spec->dividerOrnament ?: $this->defaultDividerOrnament($spec->category);

        $layers = [
            // 1. Non-text: Thin inset frame
            $this->makeBorderLayer('border-inner', 'single-rule', 64, 64, 952, 1400, [
                'stroke' => '@secondary',
                'strokeWidth' => 1,
            ]),

            // 2. Non-text: Top-left corner ornament
            $this->makeOrnamentLayer('ornament-corner-top-left', $corner, 48, 48, 240, 240, [
                'tint' => ['c1' => '@primary', 'c2' => '@secondary', 'c3' => '@accent'],
            ]),

            // 3. Non-text: Bottom-right corner ornament (mirrored diagonally)
            $this->makeOrnamentLayer('ornament-corner-bottom-right', $corner, 792, 1240, 240, 240, [
                'tint' => ['c1' => '@primary', 'c2' => '@secondary', 'c3' => '@accent'],
                'flipX' => true,
                'flipY' => true,
            ]),

            // Text 1: Opening line
            $this->makeTextLayer(
                'text-opening',
                $spec->opening,
                144,
                264,
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
                328,
                792,
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
                144,
                504,
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
                560,
                792,
                80,
                $fonts['body'],
                32,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400']
            ),

            // 4. Non-text: Center divider ornament
            $this->makeOrnamentLayer('ornament-center-divider', $divider, 412, 656, 256, 40, [
                'tint' => ['c1' => '@secondary', 'c2' => '@primary', 'c3' => '@accent'],
            ]),

            // Text 5: Details
            $this->makeTextLayer(
                'text-details',
                $spec->details,
                144,
                712,
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
                952,
                792,
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
                144,
                1032,
                640,
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
