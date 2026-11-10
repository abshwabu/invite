import { Palette } from '../types/design';
import { resolveColor } from './paletteResolver';
import { sanitizeSvg } from './svgSanitizer';

export interface OrnamentItem {
    id: string;
    set: string;
    name_en: string;
    name_am: string;
    tags: string[];
    anchor: 'corner' | 'top' | 'bottom' | 'side' | 'center' | 'divider' | 'frame' | 'pattern' | 'scene';
    viewBox: string;
    defaultAspect: number;
    tintSlots: string[];
}

import manifestData from '../../ornaments/manifest.json';

// Eager load all ornament SVGs via Vite raw imports for frontend bundling
const svgModules = import.meta.glob('../../ornaments/**/*.svg', { query: '?raw', import: 'default', eager: true }) as Record<string, string>;

export const ornamentsManifest: OrnamentItem[] = manifestData as OrnamentItem[];

export function getAllOrnaments(): OrnamentItem[] {
    return ornamentsManifest;
}

export function getAllSets(): string[] {
    const sets = new Set<string>();
    for (const item of ornamentsManifest) {
        if (item.set) sets.add(item.set);
    }
    return Array.from(sets);
}

export function getAllTags(): string[] {
    const tags = new Set<string>();
    for (const item of ornamentsManifest) {
        for (const t of item.tags || []) {
            tags.add(t);
        }
    }
    return Array.from(tags).sort();
}

export function getOrnamentsBySet(set: string): OrnamentItem[] {
    return ornamentsManifest.filter(o => o.set === set);
}

export function getOrnamentsByAnchor(anchor: string): OrnamentItem[] {
    return ornamentsManifest.filter(o => o.anchor === anchor);
}

export function getOrnamentsByTag(tag: string): OrnamentItem[] {
    return ornamentsManifest.filter(o => o.tags.includes(tag));
}

export function findOrnament(id: string): OrnamentItem | undefined {
    return ornamentsManifest.find(o => o.id === id);
}

/**
 * Get raw SVG string by id. Works in Vite browser environment and Node fallback.
 */
export function getRawSvg(id: string): string | null {
    const item = findOrnament(id);
    if (!item) return null;

    // 1. Look in bundled Vite modules
    const expectedPath = `../../ornaments/${item.set}/${id}.svg`;
    for (const [key, content] of Object.entries(svgModules)) {
        if (key.endsWith(`/${item.set}/${id}.svg`)) {
            return content;
        }
    }

    return null;
}

/**
 * Load, sanitize, and tint an ornament SVG string.
 */
export function loadTintedSvg(
    id: string,
    tint: { c1?: string; c2?: string; c3?: string } = {},
    palette?: Partial<Palette>,
    rawSvgContent?: string
): string | null {
    const raw = rawSvgContent || getRawSvg(id);
    if (!raw) return null;

    const clean = sanitizeSvg(raw);

    const c1 = resolveColor(tint.c1 || '@primary', palette);
    const c2 = resolveColor(tint.c2 || '@secondary', palette);
    const c3 = resolveColor(tint.c3 || '@accent', palette);

    return clean
        .replaceAll('{{c1}}', c1)
        .replaceAll('{{c2}}', c2)
        .replaceAll('{{c3}}', c3);
}
