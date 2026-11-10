export type PaletteRole = 'bg' | 'surface' | 'primary' | 'secondary' | 'accent' | 'ink' | 'muted';

export interface Palette {
    bg: string;
    surface: string;
    primary: string;
    secondary: string;
    accent: string;
    ink: string;
    muted: string;
    [key: string]: string;
}

export type TextureKind = 'none' | 'paper' | 'linen' | 'grain' | 'watercolor';

export interface BackgroundTexture {
    kind: TextureKind;
    opacity: number;
    seed: number;
}

export interface GradientStop {
    offset: number;
    color: string;
}

export interface GradientValue {
    type: 'linear' | 'radial';
    angle?: number;
    stops: GradientStop[] | [number, string][];
}

export type BackgroundType = 'color' | 'gradient' | 'image' | 'pattern';

export interface Background {
    type: BackgroundType;
    value: string | GradientValue;
    texture?: BackgroundTexture;
    vignette?: number;
}

export type BlendMode = 'normal' | 'multiply' | 'screen' | 'overlay';

export interface BaseLayer {
    id: string;
    name?: string;
    type: LayerType;
    x: number;
    y: number;
    width: number;
    height: number;
    rotation?: number;
    opacity?: number;
    locked?: boolean;
    flipX?: boolean;
    flipY?: boolean;
    blendMode?: BlendMode;
}

export interface TextShadow {
    color: string;
    blur: number;
    x: number;
    y: number;
    opacity?: number;
}

export interface TextAutoFit {
    minSize: number;
    maxLines?: number;
}

export interface TextI18n {
    en?: string;
    am?: string;
}

export interface TextLayer extends BaseLayer {
    type: 'text';
    text?: string;
    i18n?: TextI18n;
    fontFamily?: string;
    fontSize?: number;
    fontWeight?: number | string;
    fontStyle?: 'normal' | 'italic';
    fill?: string | GradientValue;
    align?: 'left' | 'center' | 'right';
    verticalAlign?: 'top' | 'middle' | 'bottom';
    lineHeight?: number;
    letterSpacing?: number;
    curve?: number; // arc radius in px, 0 = straight
    stroke?: string;
    strokeWidth?: number;
    shadow?: TextShadow;
    autoFit?: TextAutoFit;
}

export interface OrnamentLayer extends BaseLayer {
    type: 'ornament';
    assetId: string;
    tint?: {
        c1?: string;
        c2?: string;
        c3?: string;
        [key: string]: string | undefined;
    };
}

export type BorderStyle =
    | 'double-rule'
    | 'single-rule'
    | 'ornate-corners'
    | 'dotted'
    | 'scalloped'
    | 'tilet-band';

export interface BorderLayer extends BaseLayer {
    type: 'border';
    style: BorderStyle;
    inset?: number;
    stroke?: string;
    strokeWidth?: number;
    gap?: number;
    cornerAssetId?: string | null;
}

export type ShapeKind = 'rect' | 'circle' | 'ellipse' | 'arch' | 'line' | 'polygon' | 'star' | 'ribbon';

export interface ShapeLayer extends BaseLayer {
    type: 'shape';
    shape: ShapeKind;
    fill?: string | GradientValue;
    stroke?: string;
    strokeWidth?: number;
    cornerRadius?: number | number[];
    points?: number[];
}

export type ImageMask = 'none' | 'rect' | 'circle' | 'arch' | 'oval' | 'rounded';
export type ImageObjectFit = 'cover' | 'contain' | 'fill';

export interface ImageLayer extends BaseLayer {
    type: 'image';
    src: string;
    mask?: ImageMask;
    maskOffsetX?: number;
    maskOffsetY?: number;
    objectFit?: ImageObjectFit;
    filter?: string;
}

export interface PatternArea {
    x: number;
    y: number;
    width: number;
    height: number;
}

export interface PatternLayer extends BaseLayer {
    type: 'pattern';
    assetId: string;
    tint?: {
        c1?: string;
        c2?: string;
        c3?: string;
    };
    tileWidth?: number;
    tileHeight?: number;
    gapX?: number;
    gapY?: number;
    rotation?: number;
    area?: PatternArea;
    clipTo?: 'rect' | 'arch' | 'circle';
}

export interface GroupLayer extends BaseLayer {
    type: 'group';
    children: Layer[];
}

export type LayerType = 'text' | 'ornament' | 'border' | 'shape' | 'image' | 'pattern' | 'group';

export type Layer =
    | TextLayer
    | OrnamentLayer
    | BorderLayer
    | ShapeLayer
    | ImageLayer
    | PatternLayer
    | GroupLayer;

export interface DesignV2 {
    version: 2;
    width: number;
    height: number;
    palette: Palette;
    background: Background;
    layers: Layer[];
}

export interface DesignV1 {
    version?: 1;
    width: number;
    height: number;
    background: {
        type: string;
        value: string;
    };
    layers: Array<Record<string, any>>;
}

export type Design = DesignV2 | DesignV1;
