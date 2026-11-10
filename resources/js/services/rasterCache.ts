import { loadTintedSvg } from './ornamentRegistry';
import { Palette } from '../types/design';
import { resolveColor } from './paletteResolver';

// In-memory cache of rendered image elements
const rasterCache = new Map<string, HTMLImageElement>();
const pendingCallbacks = new Map<string, Array<() => void>>();

export function getTintKey(
    assetId: string,
    tint: { c1?: string; c2?: string; c3?: string } = {},
    palette?: Partial<Palette>
): string {
    const c1 = resolveColor(tint.c1 || '@primary', palette);
    const c2 = resolveColor(tint.c2 || '@secondary', palette);
    const c3 = resolveColor(tint.c3 || '@accent', palette);
    return `${assetId}::${c1}::${c2}::${c3}`;
}

/**
 * Get or load cached tinted SVG as an HTMLImageElement.
 * High performance for Konva 30-60fps dragging and rendering.
 */
export function getCachedTintedImage(
    assetId: string,
    tint: { c1?: string; c2?: string; c3?: string } = {},
    palette?: Partial<Palette>,
    onLoad?: () => void
): HTMLImageElement | null {
    if (typeof window === 'undefined') return null;

    const key = getTintKey(assetId, tint, palette);

    if (rasterCache.has(key)) {
        return rasterCache.get(key)!;
    }

    if (onLoad) {
        const callbacks = pendingCallbacks.get(key) || [];
        callbacks.push(onLoad);
        pendingCallbacks.set(key, callbacks);
    }

    if (pendingCallbacks.has(key) && pendingCallbacks.get(key)!.length > 1) {
        return null;
    }

    const svgString = loadTintedSvg(assetId, tint, palette);
    if (!svgString) return null;

    const img = new window.Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => {
        rasterCache.set(key, img);
        const callbacks = pendingCallbacks.get(key) || [];
        pendingCallbacks.delete(key);
        callbacks.forEach(cb => {
            try {
                cb();
            } catch {
                // Ignore callback errors
            }
        });
    };
    img.onerror = () => {
        pendingCallbacks.delete(key);
    };
    img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svgString);

    return null;
}

/**
 * Clear the raster cache (useful on palette overhaul or test teardown).
 */
export function clearRasterCache(): void {
    rasterCache.clear();
    pendingCallbacks.clear();
}
