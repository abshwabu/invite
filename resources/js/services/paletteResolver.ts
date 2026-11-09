import { Palette, DesignV2 } from '../types/design';

export const DEFAULT_PALETTE: Palette = {
    bg: '#FAF7F2',
    surface: '#FFFFFF',
    primary: '#800020',
    secondary: '#D4AF37',
    accent: '#C5A059',
    ink: '#1F2937',
    muted: '#6B7280',
};

/**
 * Resolve a color string or "@role" against a palette.
 */
export function resolveColor(color: any, palette?: Partial<Palette>): string {
    const pal = { ...DEFAULT_PALETTE, ...(palette || {}) };

    if (typeof color === 'string') {
        const trimmed = color.trim();
        if (trimmed.startsWith('@')) {
            const role = trimmed.substring(1);
            return pal[role] || (DEFAULT_PALETTE as any)[role] || '#000000';
        }
        return trimmed;
    }

    return color || '#000000';
}

/**
 * Swap or apply a palette to a design object.
 */
export function applyPalette(design: DesignV2, newPalette: Partial<Palette>): DesignV2 {
    return {
        ...design,
        palette: {
            ...DEFAULT_PALETTE,
            ...(design.palette || {}),
            ...newPalette,
        },
    };
}

/**
 * Derive a harmonious palette from a single base color hex.
 */
export function deriveHarmoniousPalette(baseHex: string): Palette {
    const hex = normalizeHex(baseHex);
    const [r, g, b] = hexToRgb(hex);
    const [h, s, l] = rgbToHsl(r, g, b);

    const luminance = 0.2126 * (r / 255) + 0.7152 * (g / 255) + 0.0722 * (b / 255);

    const primary = hex;
    const secHue = (h + 35) % 360;
    const secondary = hslToHex(secHue, Math.max(0.4, Math.min(0.85, s * 0.9)), Math.max(0.35, Math.min(0.65, l)));
    const accent = hslToHex((h + 45) % 360, 0.65, 0.60);

    let bg: string;
    let surface: string;
    let ink: string;
    let muted: string;

    if (luminance < 0.4) {
        bg = hslToHex(h, 0.15, 0.97);
        surface = '#FFFFFF';
        ink = hslToHex(h, 0.25, 0.12);
        muted = hslToHex(h, 0.15, 0.45);
    } else {
        bg = hslToHex(h, 0.20, 0.96);
        surface = '#FFFFFF';
        ink = hslToHex(h, 0.40, 0.10);
        muted = hslToHex(h, 0.20, 0.40);
    }

    return {
        bg,
        surface,
        primary,
        secondary,
        accent,
        ink,
        muted,
    };
}

export function normalizeHex(hex: string): string {
    let clean = hex.trim().replace(/^#/, '');
    if (clean.length === 3) {
        clean = clean[0] + clean[0] + clean[1] + clean[1] + clean[2] + clean[2];
    }
    return '#' + clean.substring(0, 6).toLowerCase();
}

export function hexToRgb(hex: string): [number, number, number] {
    const clean = hex.replace(/^#/, '');
    return [
        parseInt(clean.substring(0, 2), 16) || 0,
        parseInt(clean.substring(2, 4), 16) || 0,
        parseInt(clean.substring(4, 6), 16) || 0,
    ];
}

export function rgbToHsl(r: number, g: number, b: number): [number, number, number] {
    r /= 255;
    g /= 255;
    b /= 255;

    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    const diff = max - min;
    const l = (max + min) / 2;

    let h = 0;
    let s = 0;

    if (diff !== 0) {
        s = l > 0.5 ? diff / (2 - max - min) : diff / (max + min);
        switch (max) {
            case r:
                h = (g - b) / diff + (g < b ? 6 : 0);
                break;
            case g:
                h = (b - r) / diff + 2;
                break;
            case b:
                h = (r - g) / diff + 4;
                break;
        }
        h /= 6;
    }

    return [Math.round(h * 360), s, l];
}

export function hslToHex(h: number, s: number, l: number): string {
    h = ((h % 360) + 360) % 360;
    h /= 360;

    const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
    const p = 2 * l - q;

    const r = hueToRgb(p, q, h + 1 / 3);
    const g = hueToRgb(p, q, h);
    const b = hueToRgb(p, q, h - 1 / 3);

    const toHex = (c: number) => {
        const hex = Math.round(c * 255).toString(16);
        return hex.length === 1 ? '0' + hex : hex;
    };

    return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
}

function hueToRgb(p: number, q: number, t: number): number {
    if (t < 0) t += 1;
    if (t > 1) t -= 1;
    if (t < 1 / 6) return p + (q - p) * 6 * t;
    if (t < 1 / 2) return q;
    if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
    return p;
}
