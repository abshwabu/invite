export type PlaceholderKey =
    | '{{guest_name}}'
    | '{{event_title}}'
    | '{{date}}'
    | '{{time}}'
    | '{{venue}}'
    | '{{host_names}}';

export type BackgroundType = 'color' | 'image' | 'gradient';

export interface GradientStop {
    offset: number;
    color: string;
}

export interface GradientDefinition {
    type: 'linear' | 'radial';
    angle?: number;
    stops: GradientStop[];
}

export interface Background {
    type: BackgroundType;
    value: string | GradientDefinition;
}

export type LayerType = 'text' | 'image' | 'shape';
export type ShapeKind = 'rect' | 'circle' | 'line';
export type TextAlign = 'left' | 'center' | 'right' | 'justify';

export interface BaseLayer {
    id: string;
    type: LayerType;
    x: number;
    y: number;
    width: number;
    height: number;
    rotation?: number;
    opacity?: number;
    locked?: boolean;
}

export interface TextLayer extends BaseLayer {
    type: 'text';
    text: string;
    fontFamily?: string;
    fontSize?: number;
    fontWeight?: number | string;
    fill?: string;
    align?: TextAlign;
    lineHeight?: number;
    letterSpacing?: number;
}

export interface ImageLayer extends BaseLayer {
    type: 'image';
    src: string;
}

export interface ShapeLayer extends BaseLayer {
    type: 'shape';
    shape: ShapeKind;
    fill?: string;
    stroke?: string;
    strokeWidth?: number;
}

export type Layer = TextLayer | ImageLayer | ShapeLayer;

export interface Design {
    version: number;
    width: number;
    height: number;
    background: Background;
    layers: Layer[];
}
