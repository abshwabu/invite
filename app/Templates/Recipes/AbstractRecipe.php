<?php

namespace App\Templates\Recipes;

abstract class AbstractRecipe
{
    public const CANVAS_WIDTH = 1080;
    public const CANVAS_HEIGHT = 1528;
    public const SAFE_MARGIN = 96;
    public const CONTENT_WIDTH = 888; // 1080 - 2 * 96
    public const CONTENT_HEIGHT = 1336; // 1528 - 2 * 96

    abstract public function getId(): string;
    abstract public function getName(): string;
    abstract public function getDescription(): string;
    abstract public function build(RecipeSpec $spec): array;

    /**
     * Snap coordinate to 8px grid.
     */
    protected function snap8(float|int $value): int
    {
        return (int) (round($value / 8) * 8);
    }

    /**
     * Choose default anchor ornament for category.
     */
    protected function defaultAnchorOrnament(string $category): string
    {
        return match ($category) {
            'wedding' => 'floral-arch-wreath',
            'anniversary' => 'laurel-wreath-pair',
            'birthday' => 'balloon-cluster',
            'baby-shower' => 'cloud-trio',
            'graduation' => 'crest-rays',
            'corporate' => 'badge-seal',
            'housewarming' => 'jebena-coffee-set-warm',
            'funeral' => 'dove-ascending',
            'faith-symbols' => 'ethiopian-orthodox-cross-lalibela',
            'religious-islamic' => 'star-12-rosette',
            'religious-ethiopian' => 'meskel-cross-ornate',
            default => 'double-rule-ornament',
        };
    }

    /**
     * Choose secondary decorative divider ornament for category.
     */
    protected function defaultDividerOrnament(string $category): string
    {
        return match ($category) {
            'religious-ethiopian', 'wedding' => 'tilet-divider-01',
            'religious-islamic' => 'geometric-divider',
            'corporate', 'graduation' => 'line-grid-field',
            'birthday', 'baby-shower' => 'sparkle-trio',
            default => 'floral-divider',
        };
    }

    /**
     * Helper to create a compliant text layer.
     */
    protected function makeTextLayer(
        string $id,
        string $text,
        int $x,
        int $y,
        int $width,
        int $height,
        string $fontFamily,
        float|int $fontSize,
        RecipeSpec $spec,
        array $options = []
    ): array {
        $isAmharic = ($spec->locale === 'am') || (bool) preg_match('/[\x{1200}-\x{137F}]/u', $text);

        // Enforce safe margins (>= 64px, default 96px)
        $minX = self::SAFE_MARGIN;
        $maxX = self::CANVAS_WIDTH - self::SAFE_MARGIN;
        $minY = self::SAFE_MARGIN;
        $maxY = self::CANVAS_HEIGHT - self::SAFE_MARGIN;

        $clampedX = max($minX, min($x, $maxX - 100));
        $clampedY = max($minY, min($y, $maxY - 50));
        $clampedW = min($width, $maxX - $clampedX);
        $clampedH = min($height, $maxY - $clampedY);

        $lineHeight = $isAmharic ? max(1.35, (float) ($options['lineHeight'] ?? 1.4)) : (float) ($options['lineHeight'] ?? 1.3);
        $letterSpacing = $isAmharic ? 0.0 : (float) ($options['letterSpacing'] ?? 0.0);

        $layer = [
            'id' => $id,
            'type' => 'text',
            'x' => $this->snap8($clampedX),
            'y' => $this->snap8($clampedY),
            'width' => $this->snap8($clampedW),
            'height' => $this->snap8($clampedH),
            'text' => $text,
            'fontFamily' => $fontFamily,
            'fontSize' => (float) $fontSize,
            'fontWeight' => $options['fontWeight'] ?? '400',
            'fill' => $options['fill'] ?? '@ink',
            'textAlign' => $options['textAlign'] ?? 'center',
            'lineHeight' => (float) $lineHeight,
            'letterSpacing' => (float) $letterSpacing,
            'opacity' => (float) ($options['opacity'] ?? 1.0),
        ];

        if (!empty($options['autoFit'])) {
            $layer['autoFit'] = is_array($options['autoFit']) ? $options['autoFit'] : [
                'enabled' => true,
                'minSize' => 48,
                'step' => 2,
            ];
        }

        return $layer;
    }

    /**
     * Helper to create an ornament layer.
     */
    protected function makeOrnamentLayer(
        string $id,
        string $assetId,
        int $x,
        int $y,
        int $width,
        int $height,
        array $options = []
    ): array {
        return [
            'id' => $id,
            'type' => 'ornament',
            'assetId' => $assetId,
            'x' => $this->snap8($x),
            'y' => $this->snap8($y),
            'width' => $this->snap8($width),
            'height' => $this->snap8($height),
            'tint' => $options['tint'] ?? [
                'c1' => '@primary',
                'c2' => '@secondary',
                'c3' => '@accent',
            ],
            'flipX' => (bool) ($options['flipX'] ?? false),
            'flipY' => (bool) ($options['flipY'] ?? false),
            'opacity' => (float) ($options['opacity'] ?? 1.0),
            'rotation' => (float) ($options['rotation'] ?? 0),
        ];
    }

    /**
     * Helper to create a border layer.
     */
    protected function makeBorderLayer(
        string $id,
        string $style = 'double-rule',
        int $x = 48,
        int $y = 48,
        int $width = 984,
        int $height = 1432,
        array $options = []
    ): array {
        return [
            'id' => $id,
            'type' => 'border',
            'style' => $style,
            'x' => $this->snap8($x),
            'y' => $this->snap8($y),
            'width' => $this->snap8($width),
            'height' => $this->snap8($height),
            'stroke' => $options['stroke'] ?? '@primary',
            'strokeWidth' => (int) ($options['strokeWidth'] ?? 2),
            'inset' => (int) ($options['inset'] ?? 0),
            'opacity' => (float) ($options['opacity'] ?? 1.0),
        ];
    }

    /**
     * Helper to create a shape layer.
     */
    protected function makeShapeLayer(
        string $id,
        string $shape,
        int $x,
        int $y,
        int $width,
        int $height,
        array $options = []
    ): array {
        $layer = [
            'id' => $id,
            'type' => 'shape',
            'shape' => $shape,
            'x' => $this->snap8($x),
            'y' => $this->snap8($y),
            'width' => $this->snap8($width),
            'height' => $this->snap8($height),
            'fill' => $options['fill'] ?? '@surface',
            'stroke' => $options['stroke'] ?? '@secondary',
            'strokeWidth' => (int) ($options['strokeWidth'] ?? 1),
            'opacity' => (float) ($options['opacity'] ?? 1.0),
        ];

        if (isset($options['cornerRadius'])) {
            $layer['cornerRadius'] = (int) $options['cornerRadius'];
        }

        return $layer;
    }

    /**
     * Wrap layers in a complete Design JSON v2 structure.
     */
    protected function wrapDesign(RecipeSpec $spec, array $layers, array $backgroundOverrides = []): array
    {
        return [
            'version' => 2,
            'width' => self::CANVAS_WIDTH,
            'height' => self::CANVAS_HEIGHT,
            'palette' => $spec->getPalette(),
            'background' => array_merge([
                'type' => 'color',
                'value' => '@bg',
                'texture' => [
                    'kind' => $spec->textureKind,
                    'opacity' => $spec->textureOpacity,
                    'seed' => 1,
                ],
                'vignette' => 0,
            ], $backgroundOverrides),
            'layers' => $layers,
        ];
    }
}
