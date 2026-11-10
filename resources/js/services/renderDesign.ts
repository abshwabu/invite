import { DesignV2, Design, Palette, Layer, TextLayer, ShapeLayer, BorderLayer, OrnamentLayer, PatternLayer, ImageLayer } from '../types/design';
import { resolveColor, DEFAULT_PALETTE } from './paletteResolver';
import { renderBackgroundToContext } from './backgroundRenderer';
import { loadTintedSvg } from './ornamentRegistry';

export interface RenderOptions {
    data?: Record<string, string>;
    lang?: 'en' | 'am' | 'both';
    palette?: Partial<Palette>;
    scale?: number;
    svgRasterizer?: (svgString: string, width: number, height: number) => any;
    imageLoader?: (src: string) => any;
}

const DEFAULT_REPLACEMENTS: Record<string, string> = {
    '{{guest_name}}': 'Almaz & Dawit',
    '{{event_title}}': 'Celebration of Joy',
    '{{date}}': 'Sunday, January 10, 2027',
    '{{time}}': '2:00 PM',
    '{{venue}}': 'Sheraton Addis, Lalibela Ballroom',
    '{{host_names}}': 'Abebe & Chaltu',
};

/**
 * Replace placeholders in text.
 */
export function resolvePlaceholders(text: string, data?: Record<string, string>): string {
    if (!text) return '';
    let result = String(text);

    const normalizedData: Record<string, string> = {};
    if (data) {
        for (const [k, v] of Object.entries(data)) {
            const token = k.startsWith('{{') ? k : `{{${k}}}`;
            normalizedData[token] = String(v ?? '');
        }
    }

    const replacements = { ...DEFAULT_REPLACEMENTS, ...normalizedData };

    for (const [token, val] of Object.entries(replacements)) {
        result = result.replaceAll(token, String(val ?? ''));
    }

    return result;
}

/**
 * Single shared render function for canvas contexts.
 */
export function renderDesignToCanvas(
    canvas: any,
    design: Design,
    options: RenderOptions = {}
): any {
    const scale = options.scale !== undefined ? options.scale : 1.0;
    const lang = options.lang || 'en';
    const effectivePalette = {
        ...DEFAULT_PALETTE,
        ...((design as DesignV2).palette || {}),
        ...(options.palette || {}),
    };

    const canvasW = Math.round((design.width || 1080) * scale);
    const canvasH = Math.round((design.height || 1528) * scale);

    if (canvas.width !== canvasW || canvas.height !== canvasH) {
        canvas.width = canvasW;
        canvas.height = canvasH;
    }

    const ctx = canvas.getContext('2d');
    if (!ctx) return canvas;

    // 1. Render Background (Gradients + Procedural Textures + Vignette)
    const bg = design.background || { type: 'color', value: '@bg' };
    renderBackgroundToContext(ctx, canvasW, canvasH, bg, effectivePalette);

    // 2. Render Layers in Order (bottom to top)
    const layers = design.layers || [];
    for (const layer of layers) {
        renderLayer(ctx, layer, scale, lang, effectivePalette, options);
    }

    return canvas;
}

export function renderLayer(
    ctx: CanvasRenderingContext2D,
    layer: Layer,
    scale: number,
    lang: 'en' | 'am' | 'both',
    palette: Palette,
    options: RenderOptions
): void {
    ctx.save();

    // Common transforms & properties
    const opacity = layer.opacity !== undefined ? layer.opacity : 1.0;
    ctx.globalAlpha = Math.max(0, Math.min(1, opacity));

    if (layer.blendMode && layer.blendMode !== 'normal') {
        ctx.globalCompositeOperation = layer.blendMode as GlobalCompositeOperation;
    }

    const lx = layer.x * scale;
    const ly = layer.y * scale;
    const lw = layer.width * scale;
    const lh = layer.height * scale;

    // Translation & Rotation
    const cx = lx + lw / 2;
    const cy = ly + lh / 2;

    if (layer.rotation) {
        ctx.translate(cx, cy);
        ctx.rotate((layer.rotation * Math.PI) / 180);
        ctx.translate(-cx, -cy);
    }

    if (layer.flipX || layer.flipY) {
        ctx.translate(cx, cy);
        ctx.scale(layer.flipX ? -1 : 1, layer.flipY ? -1 : 1);
        ctx.translate(-cx, -cy);
    }

    switch (layer.type) {
        case 'border':
            renderBorderLayer(ctx, layer, lx, ly, lw, lh, scale, palette);
            break;
        case 'ornament':
            renderOrnamentLayer(ctx, layer, lx, ly, lw, lh, scale, palette, options);
            break;
        case 'shape':
            renderShapeLayer(ctx, layer, lx, ly, lw, lh, scale, palette);
            break;
        case 'pattern':
            renderPatternLayer(ctx, layer, lx, ly, lw, lh, scale, palette, options);
            break;
        case 'text':
            renderTextLayer(ctx, layer, lx, ly, lw, lh, scale, lang, palette, options);
            break;
        case 'image':
            renderImageLayer(ctx, layer, lx, ly, lw, lh, scale, options);
            break;
        case 'group':
            if (Array.isArray(layer.children)) {
                for (const child of layer.children) {
                    renderLayer(ctx, child, scale, lang, palette, options);
                }
            }
            break;
    }

    ctx.restore();
}

export function renderBorderLayer(
    ctx: CanvasRenderingContext2D,
    layer: BorderLayer,
    x: number,
    y: number,
    w: number,
    h: number,
    scale: number,
    palette: Palette
): void {
    const stroke = resolveColor(layer.stroke || '@primary', palette);
    const strokeWidth = Math.max(1, (layer.strokeWidth || 2) * scale);
    const inset = (layer.inset || 40) * scale;
    const bx = x + inset;
    const by = y + inset;
    const bw = Math.max(10, w - inset * 2);
    const bh = Math.max(10, h - inset * 2);

    ctx.save();
    ctx.strokeStyle = stroke;
    ctx.lineWidth = strokeWidth;

    if (layer.style === 'single-rule') {
        ctx.strokeRect(bx, by, bw, bh);
    } else if (layer.style === 'double-rule') {
        ctx.strokeRect(bx, by, bw, bh);
        const gap = (layer.gap || 6) * scale;
        if (bw > gap * 2 && bh > gap * 2) {
            ctx.lineWidth = Math.max(1, strokeWidth * 0.7);
            ctx.strokeRect(bx + gap, by + gap, bw - gap * 2, bh - gap * 2);
        }
    } else if (layer.style === 'dotted') {
        ctx.setLineDash([strokeWidth * 2, strokeWidth * 2]);
        ctx.strokeRect(bx, by, bw, bh);
    } else if (layer.style === 'ornate-corners') {
        // Double frame with accent corner squares
        ctx.strokeRect(bx, by, bw, bh);
        const cornerSize = 16 * scale;
        ctx.fillStyle = stroke;
        ctx.fillRect(bx - cornerSize / 2, by - cornerSize / 2, cornerSize, cornerSize);
        ctx.fillRect(bx + bw - cornerSize / 2, by - cornerSize / 2, cornerSize, cornerSize);
        ctx.fillRect(bx - cornerSize / 2, by + bh - cornerSize / 2, cornerSize, cornerSize);
        ctx.fillRect(bx + bw - cornerSize / 2, by + bh - cornerSize / 2, cornerSize, cornerSize);
    } else if (layer.style === 'tilet-band') {
        // Traditional woven tilet band top and bottom
        ctx.strokeRect(bx, by, bw, bh);
        const bandH = 12 * scale;
        ctx.fillStyle = resolveColor('@secondary', palette);
        ctx.fillRect(bx, by, bw, bandH);
        ctx.fillRect(bx, by + bh - bandH, bw, bandH);
    } else {
        ctx.strokeRect(bx, by, bw, bh);
    }

    ctx.restore();
}

export function renderOrnamentLayer(
    ctx: CanvasRenderingContext2D,
    layer: OrnamentLayer,
    x: number,
    y: number,
    w: number,
    h: number,
    scale: number,
    palette: Palette,
    options: RenderOptions
): void {
    const tintedSvg = loadTintedSvg(layer.assetId, layer.tint, palette);
    if (!tintedSvg) return;

    if (options.svgRasterizer) {
        const img = options.svgRasterizer(tintedSvg, Math.round(w), Math.round(h));
        if (img) {
            ctx.drawImage(img, x, y, w, h);
            return;
        }
    }

    // Browser fallback: draw SVG via data URL or Image element
    if (typeof window !== 'undefined' && window.Image) {
        const img = new window.Image();
        img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(tintedSvg);
        if (img.complete) {
            ctx.drawImage(img, x, y, w, h);
        }
    }
}

export function renderShapeLayer(
    ctx: CanvasRenderingContext2D,
    layer: ShapeLayer,
    x: number,
    y: number,
    w: number,
    h: number,
    scale: number,
    palette: Palette
): void {
    ctx.save();

    const fill = layer.fill ? resolveColor(layer.fill, palette) : null;
    const stroke = layer.stroke ? resolveColor(layer.stroke, palette) : null;
    const strokeWidth = layer.strokeWidth ? Math.max(1, layer.strokeWidth * scale) : 0;

    ctx.beginPath();
    if (layer.shape === 'rect') {
        const radius = (layer.cornerRadius ? (typeof layer.cornerRadius === 'number' ? layer.cornerRadius : layer.cornerRadius[0]) : 0) * scale;
        if (radius > 0 && typeof ctx.roundRect === 'function') {
            ctx.roundRect(x, y, w, h, radius);
        } else {
            ctx.rect(x, y, w, h);
        }
    } else if (layer.shape === 'circle') {
        const r = Math.min(w, h) / 2;
        ctx.arc(x + r, y + r, r, 0, Math.PI * 2);
    } else if (layer.shape === 'ellipse') {
        ctx.ellipse(x + w / 2, y + h / 2, w / 2, h / 2, 0, 0, Math.PI * 2);
    } else if (layer.shape === 'arch') {
        // Cathedral rounded top arch with straight bottom
        const r = w / 2;
        ctx.moveTo(x, y + h);
        ctx.lineTo(x, y + r);
        ctx.arc(x + r, y + r, r, Math.PI, 0, false);
        ctx.lineTo(x + w, y + h);
        ctx.closePath();
    } else if (layer.shape === 'line') {
        ctx.moveTo(x, y + h / 2);
        ctx.lineTo(x + w, y + h / 2);
    } else if (layer.shape === 'star') {
        // 8-point geometric star
        const cx = x + w / 2;
        const cy = y + h / 2;
        const outerR = Math.min(w, h) / 2;
        const innerR = outerR * 0.45;
        const points = 8;
        for (let i = 0; i < points * 2; i++) {
            const r = i % 2 === 0 ? outerR : innerR;
            const angle = (i * Math.PI) / points;
            const px = cx + Math.cos(angle) * r;
            const py = cy + Math.sin(angle) * r;
            if (i === 0) ctx.moveTo(px, py);
            else ctx.lineTo(px, py);
        }
        ctx.closePath();
    }

    if (fill && fill !== 'transparent') {
        ctx.fillStyle = fill;
        ctx.fill();
    }

    if (stroke && strokeWidth > 0) {
        ctx.strokeStyle = stroke;
        ctx.lineWidth = strokeWidth;
        ctx.stroke();
    }

    ctx.restore();
}

export function renderPatternLayer(
    ctx: CanvasRenderingContext2D,
    layer: PatternLayer,
    x: number,
    y: number,
    w: number,
    h: number,
    scale: number,
    palette: Palette,
    options: RenderOptions
): void {
    const tileW = Math.max(16, (layer.tileWidth || 60) * scale);
    const tileH = Math.max(16, (layer.tileHeight || 60) * scale);
    const gapX = (layer.gapX || 0) * scale;
    const gapY = (layer.gapY || 0) * scale;

    const tintedSvg = loadTintedSvg(layer.assetId, layer.tint, palette);
    if (!tintedSvg) return;

    let tileImg: any = null;
    if (options.svgRasterizer) {
        tileImg = options.svgRasterizer(tintedSvg, Math.round(tileW), Math.round(tileH));
    }

    ctx.save();
    // Clip pattern area
    ctx.beginPath();
    ctx.rect(x, y, w, h);
    ctx.clip();

    for (let px = x; px < x + w; px += tileW + gapX) {
        for (let py = y; py < y + h; py += tileH + gapY) {
            if (tileImg) {
                ctx.drawImage(tileImg, px, py, tileW, tileH);
            }
        }
    }
    ctx.restore();
}

export function renderImageLayer(
    ctx: CanvasRenderingContext2D,
    layer: ImageLayer,
    x: number,
    y: number,
    w: number,
    h: number,
    scale: number,
    options: RenderOptions
): void {
    if (!layer.src) return;

    let img: any = null;
    if (options.imageLoader) {
        img = options.imageLoader(layer.src);
    } else if (typeof window !== 'undefined' && window.Image) {
        img = new window.Image();
        img.src = layer.src;
    }

    if (!img) return;

    ctx.save();
    if (layer.mask === 'circle') {
        const r = Math.min(w, h) / 2;
        ctx.beginPath();
        ctx.arc(x + r, y + r, r, 0, Math.PI * 2);
        ctx.clip();
    } else if (layer.mask === 'oval') {
        ctx.beginPath();
        ctx.ellipse(x + w / 2, y + h / 2, w / 2, h / 2, 0, 0, Math.PI * 2);
        ctx.clip();
    } else if (layer.mask === 'arch') {
        const r = w / 2;
        ctx.beginPath();
        ctx.moveTo(x, y + h);
        ctx.lineTo(x, y + r);
        ctx.arc(x + r, y + r, r, Math.PI, 0, false);
        ctx.lineTo(x + w, y + h);
        ctx.closePath();
        ctx.clip();
    } else if (layer.mask === 'rounded') {
        const r = 24 * scale;
        if (typeof ctx.roundRect === 'function') {
            ctx.beginPath();
            ctx.roundRect(x, y, w, h, r);
            ctx.clip();
        }
    }

    const imgX = x + ((layer.maskOffsetX || 0) * scale);
    const imgY = y + ((layer.maskOffsetY || 0) * scale);

    try {
        ctx.drawImage(img, imgX, imgY, w, h);
    } catch {
        // Image not yet loaded or invalid
    }
    ctx.restore();
}

export function renderTextLayer(
    ctx: CanvasRenderingContext2D,
    layer: TextLayer,
    x: number,
    y: number,
    w: number,
    h: number,
    scale: number,
    lang: 'en' | 'am' | 'both',
    palette: Palette,
    options: RenderOptions
): void {
    // 1. Determine string content
    let rawText = '';
    if (layer.i18n) {
        if (lang === 'both') {
            const amPart = layer.i18n.am || '';
            const enPart = layer.i18n.en || '';
            rawText = amPart && enPart ? `${amPart}\n${enPart}` : amPart || enPart || layer.text || '';
        } else if (lang === 'am') {
            rawText = layer.i18n.am || layer.text || '';
        } else {
            rawText = layer.i18n.en || layer.text || '';
        }
    } else {
        rawText = layer.text || '';
    }

    const text = resolvePlaceholders(rawText, options.data);
    if (!text.trim()) return;

    ctx.save();

    // 2. Resolve font & sizes
    let fontSize = Math.max(8, Math.round((layer.fontSize || 28) * scale));
    const fontFamily = layer.fontFamily || 'Noto Serif Ethiopic';
    const fontWeight = layer.fontWeight || 400;
    const fontStyle = layer.fontStyle || 'normal';
    const fill = resolveColor(layer.fill || '@ink', palette);

    // AutoFit logic: adjust fontSize down to minSize if text overflows
    if (layer.autoFit && layer.autoFit.minSize) {
        const minSize = Math.max(8, Math.round(layer.autoFit.minSize * scale));
        while (fontSize > minSize) {
            ctx.font = `${fontStyle} ${fontWeight} ${fontSize}px "${fontFamily}", sans-serif`;
            const lines = text.split('\n');
            let maxLineWidth = 0;
            for (const line of lines) {
                const measure = ctx.measureText(line);
                if (measure.width > maxLineWidth) maxLineWidth = measure.width;
            }
            if (maxLineWidth <= w) break;
            fontSize -= 1;
        }
    }

    ctx.font = `${fontStyle} ${fontWeight} ${fontSize}px "${fontFamily}", sans-serif`;
    ctx.fillStyle = fill;
    ctx.textBaseline = 'top';

    // 3. Shadow
    if (layer.shadow) {
        ctx.shadowColor = resolveColor(layer.shadow.color || '#000000', palette);
        ctx.shadowBlur = (layer.shadow.blur || 4) * scale;
        ctx.shadowOffsetX = (layer.shadow.x || 0) * scale;
        ctx.shadowOffsetY = (layer.shadow.y || 2) * scale;
    }

    // 4. Curved Text
    if (layer.curve && layer.curve !== 0) {
        renderCurvedText(ctx, text, x + w / 2, y + h / 2, layer.curve * scale, fontSize);
        ctx.restore();
        return;
    }

    // 5. Standard Multiline Text
    const align = layer.align || 'center';
    let startX = x;
    if (align === 'center') {
        ctx.textAlign = 'center';
        startX = x + w / 2;
    } else if (align === 'right') {
        ctx.textAlign = 'right';
        startX = x + w;
    } else {
        ctx.textAlign = 'left';
    }

    const lines = text.split('\n');
    const lineHeight = (layer.lineHeight || 1.3) * fontSize;
    let currentY = y;

    if (layer.verticalAlign === 'middle') {
        const totalH = lines.length * lineHeight;
        currentY = y + Math.max(0, (h - totalH) / 2);
    } else if (layer.verticalAlign === 'bottom') {
        const totalH = lines.length * lineHeight;
        currentY = y + Math.max(0, h - totalH);
    }

    for (const line of lines) {
        ctx.fillText(line, startX, currentY);
        if (layer.stroke && layer.strokeWidth) {
            ctx.strokeStyle = resolveColor(layer.stroke, palette);
            ctx.lineWidth = Math.max(1, layer.strokeWidth * scale);
            ctx.strokeText(line, startX, currentY);
        }
        currentY += lineHeight;
    }

    ctx.restore();
}

/**
 * Render curved text along an arc of radius R.
 */
export function renderCurvedText(
    ctx: CanvasRenderingContext2D,
    text: string,
    cx: number,
    cy: number,
    radius: number,
    fontSize: number
): void {
    ctx.save();
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';

    const chars = Array.from(text);
    const totalLength = chars.reduce((acc, char) => acc + ctx.measureText(char).width, 0);
    const totalAngle = totalLength / radius;
    let currentAngle = -totalAngle / 2;

    for (const char of chars) {
        const charW = ctx.measureText(char).width;
        const charAngle = charW / radius;
        const midAngle = currentAngle + charAngle / 2;

        ctx.save();
        const px = cx + Math.sin(midAngle) * radius;
        const py = cy - Math.cos(midAngle) * radius;

        ctx.translate(px, py);
        ctx.rotate(midAngle);
        ctx.fillText(char, 0, 0);
        ctx.restore();

        currentAngle += charAngle;
    }

    ctx.restore();
}
