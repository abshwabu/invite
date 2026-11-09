export const ALLOWED_TAGS = new Set([
    'svg',
    'g',
    'path',
    'circle',
    'ellipse',
    'rect',
    'line',
    'polyline',
    'polygon',
    'defs',
    'lineargradient',
    'radialgradient',
    'stop',
    'clippath',
]);

export function sanitizeSvg(svgString: string): string {
    const raw = svgString.trim();
    if (!raw) {
        throw new Error('Empty SVG content');
    }

    const lower = raw.toLowerCase();

    // Fast check for forbidden keywords
    if (
        lower.includes('<script') ||
        lower.includes('</script') ||
        lower.includes('<foreignobject') ||
        lower.includes('<image') ||
        lower.includes('javascript:') ||
        lower.includes('data:')
    ) {
        throw new Error('Hostile SVG token detected');
    }

    if (/url\s*\(/i.test(raw)) {
        throw new Error('External url() references in SVG styles are prohibited');
    }

    if (/\son[a-z]+\s*=/i.test(raw)) {
        throw new Error('Inline event handlers (on*) are prohibited in SVG');
    }

    if (/<use\b[^>]*(href|xlink:href)\s*=\s*["'](https?:\/\/|\/\/)[^"']*["']/i.test(raw)) {
        throw new Error('External <use> references are prohibited');
    }

    return raw;
}

export function isValidSvg(svgString: string): boolean {
    try {
        sanitizeSvg(svgString);
        return true;
    } catch {
        return false;
    }
}
