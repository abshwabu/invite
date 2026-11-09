import fs from 'fs';
import path from 'path';
import crypto from 'crypto';
import { createCanvas, GlobalFonts, Image } from '@napi-rs/canvas';
import { Resvg } from '@resvg/resvg-js';

const FONTS_DIR = path.resolve(process.cwd(), 'public/fonts');
const ORNAMENTS_DIR = path.resolve(process.cwd(), 'resources/ornaments');

// 1. Register self-hosted fonts
const fontsMap = [
    { file: '7cHPv50vjIepfJVOZZgcpQ5B9FBTH9KGNfhSTgtoow1KVnIvyBoMSzUMacb-T35OK6Dj.ttf', family: 'Noto Sans Ethiopic' },
    { file: 'V8mjoR7-XjwJ8_Au3Ti5tXj5Rd83frpWLK4d-taxqWw2HMWjDxBAg5S_0QsrggxCzSUjkQ.ttf', family: 'Noto Serif Ethiopic' },
    { file: '8vIU7ww63mVu7gtR-kwKxNvkNOjw-tbnTYo.ttf', family: 'Cinzel' },
    { file: 'nuFRD-vYSZviVYUb_rj3ij__anPXDTnCjmHKM4nYO7KN_qiTbtY.ttf', family: 'Playfair Display' },
    { file: 'RWmMoKWR9v4ksMfaWd_JN-XC.ttf', family: 'Great Vibes' },
    { file: 'JTUHjIg1_i6t8kCHKm4532VJOt5-QNFgpCtr6Ew-.ttf', family: 'Montserrat' },
    { file: 'TK3_WkUHHAIjg75cFRf3bXL8LICs1_FvgUE.ttf', family: 'Oswald' },
    { file: 'WnznHAc5bAfYB2QRah7pcpNvOx-pjfJ9SII.ttf', family: 'Caveat' }
];

for (const f of fontsMap) {
    const fontPath = path.join(FONTS_DIR, f.file);
    if (fs.existsSync(fontPath)) {
        GlobalFonts.registerFromPath(fontPath, f.family);
    }
}

// 2. Load Ornament Manifest
let ornamentsManifest = [];
const manifestPath = path.join(ORNAMENTS_DIR, 'manifest.json');
if (fs.existsSync(manifestPath)) {
    ornamentsManifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
}

export const DEFAULT_PALETTE = {
    bg: '#FAF7F2',
    surface: '#FFFFFF',
    primary: '#800020',
    secondary: '#D4AF37',
    accent: '#C5A059',
    ink: '#1F2937',
    muted: '#6B7280'
};

const DEFAULT_REPLACEMENTS = {
    '{{guest_name}}': 'Almaz & Dawit',
    '{{event_title}}': 'Celebration of Joy',
    '{{date}}': 'Sunday, January 10, 2027',
    '{{time}}': '2:00 PM',
    '{{venue}}': 'Sheraton Addis, Lalibela Ballroom',
    '{{host_names}}': 'Abebe & Chaltu'
};

export function resolveColor(color, palette) {
    const pal = { ...DEFAULT_PALETTE, ...(palette || {}) };
    if (typeof color === 'string') {
        const trimmed = color.trim();
        if (trimmed.startsWith('@')) {
            const role = trimmed.substring(1);
            return pal[role] || DEFAULT_PALETTE[role] || '#000000';
        }
        return trimmed;
    }
    return color || '#000000';
}

export function loadAndTintSvg(assetId, tint = {}, palette = {}) {
    const item = ornamentsManifest.find(o => o.id === assetId);
    if (!item) return null;

    const svgPath = path.join(ORNAMENTS_DIR, item.set, `${assetId}.svg`);
    if (!fs.existsSync(svgPath)) return null;

    let svg = fs.readFileSync(svgPath, 'utf8');

    const c1 = resolveColor(tint.c1 || '@primary', palette);
    const c2 = resolveColor(tint.c2 || '@secondary', palette);
    const c3 = resolveColor(tint.c3 || '@accent', palette);

    return svg
        .replaceAll('{{c1}}', c1)
        .replaceAll('{{c2}}', c2)
        .replaceAll('{{c3}}', c3);
}

export function rasterizeSvgWithResvg(svgString, targetWidth, targetHeight) {
    try {
        const resvg = new Resvg(svgString, {
            fitTo: { mode: 'width', value: Math.max(1, Math.round(targetWidth)) }
        });
        const pngData = resvg.render();
        const img = new Image();
        img.src = pngData.asPng();
        return img;
    } catch (e) {
        return null;
    }
}

export function createPrng(seed) {
    let s = (seed || 1) | 0;
    return function() {
        s = (s + 0x6D2B79F5) | 0;
        let t = Math.imul(s ^ (s >>> 15), 1 | s);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

export function renderDesignToNodeCanvas(design, options = {}) {
    const scale = options.scale !== undefined ? options.scale : 0.5;
    const lang = options.lang || 'en';
    const effectivePalette = {
        ...DEFAULT_PALETTE,
        ...(design.palette || {}),
        ...(options.palette || {})
    };

    const width = Math.round((design.width || 1080) * scale);
    const height = Math.round((design.height || 1528) * scale);

    const canvas = createCanvas(width, height);
    const ctx = canvas.getContext('2d');

    // 1. Render Background
    const bg = design.background || { type: 'color', value: '@bg' };
    renderBackground(ctx, width, height, bg, effectivePalette);

    // 2. Render Layers
    const layers = design.layers || [];
    for (const layer of layers) {
        renderLayer(ctx, layer, scale, lang, effectivePalette, options);
    }

    return canvas;
}

function renderBackground(ctx, width, height, bg, palette) {
    ctx.save();

    if (bg.type === 'gradient' && typeof bg.value === 'object') {
        const gVal = bg.value;
        let grad;
        if (gVal.type === 'radial') {
            grad = ctx.createRadialGradient(width / 2, height / 2, 0, width / 2, height / 2, Math.hypot(width / 2, height / 2));
        } else {
            const angleDeg = gVal.angle !== undefined ? gVal.angle : 180;
            const angleRad = (angleDeg * Math.PI) / 180;
            const halfW = width / 2;
            const halfH = height / 2;
            const length = Math.abs(width * Math.sin(angleRad)) + Math.abs(height * Math.cos(angleRad));

            const x0 = halfW - (Math.sin(angleRad) * length) / 2;
            const y0 = halfH - (Math.cos(angleRad) * length) / 2;
            const x1 = halfW + (Math.sin(angleRad) * length) / 2;
            const y1 = halfH + (Math.cos(angleRad) * length) / 2;
            grad = ctx.createLinearGradient(x0, y0, x1, y1);
        }

        const stops = gVal.stops || [[0, '@bg'], [1, '@surface']];
        for (const stop of stops) {
            const offset = Array.isArray(stop) ? stop[0] : stop.offset;
            const colorVal = Array.isArray(stop) ? stop[1] : stop.color;
            grad.addColorStop(Math.max(0, Math.min(1, offset)), resolveColor(colorVal, palette));
        }

        ctx.fillStyle = grad;
        ctx.fillRect(0, 0, width, height);
    } else {
        const colorVal = typeof bg.value === 'string' ? bg.value : '@bg';
        ctx.fillStyle = resolveColor(colorVal, palette);
        ctx.fillRect(0, 0, width, height);
    }

    // Procedural textures
    const texture = bg.texture;
    if (texture && texture.kind && texture.kind !== 'none') {
        const prng = createPrng(texture.seed || 1);
        ctx.save();
        ctx.globalAlpha = texture.opacity || 0.08;

        if (texture.kind === 'grain') {
            const imgData = ctx.createImageData(128, 128);
            for (let i = 0; i < imgData.data.length; i += 4) {
                const n = Math.floor((prng() - 0.5) * 80);
                const val = Math.max(0, Math.min(255, 128 + n));
                imgData.data[i] = val;
                imgData.data[i + 1] = val;
                imgData.data[i + 2] = val;
                imgData.data[i + 3] = 255;
            }
            for (let x = 0; x < width; x += 128) {
                for (let y = 0; y < height; y += 128) {
                    ctx.putImageData(imgData, x, y);
                }
            }
        } else if (texture.kind === 'paper') {
            ctx.strokeStyle = '#000000';
            const fiberCount = Math.floor((width * height) / 2500);
            for (let i = 0; i < fiberCount; i++) {
                const x = prng() * width;
                const y = prng() * height;
                const len = 2 + prng() * 6;
                const angle = prng() * Math.PI * 2;
                ctx.globalAlpha = (texture.opacity || 0.08) * (0.05 + prng() * 0.15);
                ctx.lineWidth = 0.5 + prng() * 0.8;
                ctx.beginPath();
                ctx.moveTo(x, y);
                ctx.lineTo(x + Math.cos(angle) * len, y + Math.sin(angle) * len);
                ctx.stroke();
            }
        } else if (texture.kind === 'linen') {
            ctx.strokeStyle = '#FFFFFF';
            const step = 6;
            for (let x = 0; x < width; x += step) {
                ctx.globalAlpha = (texture.opacity || 0.08) * (0.05 + prng() * 0.1);
                ctx.beginPath();
                ctx.moveTo(x, 0);
                ctx.lineTo(x, height);
                ctx.stroke();
            }
            ctx.strokeStyle = '#000000';
            for (let y = 0; y < height; y += step) {
                ctx.globalAlpha = (texture.opacity || 0.08) * (0.05 + prng() * 0.1);
                ctx.beginPath();
                ctx.moveTo(0, y);
                ctx.lineTo(width, y);
                ctx.stroke();
            }
        } else if (texture.kind === 'watercolor') {
            const primary = resolveColor('@primary', palette);
            for (let i = 0; i < 18; i++) {
                const bx = prng() * width;
                const by = prng() * height;
                const radius = Math.min(width, height) * (0.15 + prng() * 0.35);
                const radial = ctx.createRadialGradient(bx, by, radius * 0.1, bx, by, radius);
                radial.addColorStop(0, primary);
                radial.addColorStop(1, 'rgba(255, 255, 255, 0)');
                ctx.globalAlpha = (texture.opacity || 0.08) * (0.2 + prng() * 0.3);
                ctx.fillStyle = radial;
                ctx.beginPath();
                ctx.arc(bx, by, radius, 0, Math.PI * 2);
                ctx.fill();
            }
        }
        ctx.restore();
    }

    // Vignette
    if (bg.vignette && bg.vignette > 0) {
        const v = Math.min(1.0, bg.vignette);
        const cx = width / 2;
        const cy = height / 2;
        const grad = ctx.createRadialGradient(cx, cy, Math.min(width, height) * 0.42, cx, cy, Math.hypot(cx, cy));
        grad.addColorStop(0, 'rgba(0,0,0,0)');
        grad.addColorStop(1, `rgba(0,0,0,${v * 0.65})`);
        ctx.fillStyle = grad;
        ctx.fillRect(0, 0, width, height);
    }

    ctx.restore();
}

function renderLayer(ctx, layer, scale, lang, palette, options) {
    ctx.save();
    ctx.globalAlpha = layer.opacity !== undefined ? Math.max(0, Math.min(1, layer.opacity)) : 1.0;

    const lx = layer.x * scale;
    const ly = layer.y * scale;
    const lw = layer.width * scale;
    const lh = layer.height * scale;
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

    if (layer.type === 'border') {
        const stroke = resolveColor(layer.stroke || '@primary', palette);
        const strokeWidth = Math.max(1, (layer.strokeWidth || 2) * scale);
        const inset = (layer.inset || 40) * scale;
        const bx = lx + inset;
        const by = ly + inset;
        const bw = Math.max(10, lw - inset * 2);
        const bh = Math.max(10, lh - inset * 2);

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
            ctx.strokeRect(bx, by, bw, bh);
            const cornerSize = 16 * scale;
            ctx.fillStyle = stroke;
            ctx.fillRect(bx - cornerSize / 2, by - cornerSize / 2, cornerSize, cornerSize);
            ctx.fillRect(bx + bw - cornerSize / 2, by - cornerSize / 2, cornerSize, cornerSize);
            ctx.fillRect(bx - cornerSize / 2, by + bh - cornerSize / 2, cornerSize, cornerSize);
            ctx.fillRect(bx + bw - cornerSize / 2, by + bh - cornerSize / 2, cornerSize, cornerSize);
        } else if (layer.style === 'tilet-band') {
            ctx.strokeRect(bx, by, bw, bh);
            const bandH = 12 * scale;
            ctx.fillStyle = resolveColor('@secondary', palette);
            ctx.fillRect(bx, by, bw, bandH);
            ctx.fillRect(bx, by + bh - bandH, bw, bandH);
        } else {
            ctx.strokeRect(bx, by, bw, bh);
        }
    } else if (layer.type === 'ornament') {
        const svg = loadAndTintSvg(layer.assetId, layer.tint, palette);
        if (svg) {
            const img = rasterizeSvgWithResvg(svg, lw, lh);
            if (img) {
                ctx.drawImage(img, lx, ly, lw, lh);
            }
        }
    } else if (layer.type === 'pattern') {
        const svg = loadAndTintSvg(layer.assetId, layer.tint, palette);
        if (svg) {
            const tileW = Math.max(16, (layer.tileWidth || 60) * scale);
            const tileH = Math.max(16, (layer.tileHeight || 60) * scale);
            const gapX = (layer.gapX || 0) * scale;
            const gapY = (layer.gapY || 0) * scale;
            const tileImg = rasterizeSvgWithResvg(svg, tileW, tileH);
            if (tileImg) {
                ctx.save();
                ctx.beginPath();
                ctx.rect(lx, ly, lw, lh);
                ctx.clip();
                for (let px = lx; px < lx + lw; px += tileW + gapX) {
                    for (let py = ly; py < ly + lh; py += tileH + gapY) {
                        ctx.drawImage(tileImg, px, py, tileW, tileH);
                    }
                }
                ctx.restore();
            }
        }
    } else if (layer.type === 'shape') {
        const fill = layer.fill ? resolveColor(layer.fill, palette) : null;
        const stroke = layer.stroke ? resolveColor(layer.stroke, palette) : null;
        const strokeWidth = layer.strokeWidth ? Math.max(1, layer.strokeWidth * scale) : 0;

        ctx.beginPath();
        if (layer.shape === 'rect') {
            const radius = (layer.cornerRadius || 0) * scale;
            if (radius > 0 && typeof ctx.roundRect === 'function') {
                ctx.roundRect(lx, ly, lw, lh, radius);
            } else {
                ctx.rect(lx, ly, lw, lh);
            }
        } else if (layer.shape === 'circle') {
            const r = Math.min(lw, lh) / 2;
            ctx.arc(lx + r, ly + r, r, 0, Math.PI * 2);
        } else if (layer.shape === 'arch') {
            const r = lw / 2;
            ctx.moveTo(lx, ly + lh);
            ctx.lineTo(lx, ly + r);
            ctx.arc(lx + r, ly + r, r, Math.PI, 0, false);
            ctx.lineTo(lx + lw, ly + lh);
            ctx.closePath();
        } else if (layer.shape === 'line') {
            ctx.moveTo(lx, ly + lh / 2);
            ctx.lineTo(lx + lw, ly + lh / 2);
        } else if (layer.shape === 'star') {
            const scx = lx + lw / 2;
            const scy = ly + lh / 2;
            const outerR = Math.min(lw, lh) / 2;
            const innerR = outerR * 0.45;
            for (let i = 0; i < 16; i++) {
                const r = i % 2 === 0 ? outerR : innerR;
                const angle = (i * Math.PI) / 8;
                const px = scx + Math.cos(angle) * r;
                const py = scy + Math.sin(angle) * r;
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
    } else if (layer.type === 'text') {
        let rawText = '';
        if (layer.i18n) {
            if (lang === 'both') {
                const am = layer.i18n.am || '';
                const en = layer.i18n.en || '';
                rawText = am && en ? `${am}\n${en}` : am || en || layer.text || '';
            } else if (lang === 'am') {
                rawText = layer.i18n.am || layer.text || '';
            } else {
                rawText = layer.i18n.en || layer.text || '';
            }
        } else {
            rawText = layer.text || '';
        }

        const replacements = { ...DEFAULT_REPLACEMENTS, ...(options.data || {}) };
        for (const [key, val] of Object.entries(replacements)) {
            const token = key.startsWith('{{') ? key : `{{${key}}}`;
            rawText = rawText.replaceAll(token, String(val ?? ''));
        }

        let fontSize = Math.max(8, Math.round((layer.fontSize || 28) * scale));
        const fontFamily = layer.fontFamily || 'Noto Serif Ethiopic';
        const fontWeight = layer.fontWeight || 400;
        const fontStyle = layer.fontStyle || 'normal';
        const fill = resolveColor(layer.fill || '@ink', palette);

        // AutoFit
        if (layer.autoFit && layer.autoFit.minSize) {
            const minSize = Math.max(8, Math.round(layer.autoFit.minSize * scale));
            while (fontSize > minSize) {
                ctx.font = `${fontStyle} ${fontWeight} ${fontSize}px "${fontFamily}", sans-serif`;
                const lines = rawText.split('\n');
                let maxW = 0;
                for (const line of lines) {
                    const m = ctx.measureText(line);
                    if (m.width > maxW) maxW = m.width;
                }
                if (maxW <= lw) break;
                fontSize -= 1;
            }
        }

        ctx.font = `${fontStyle} ${fontWeight} ${fontSize}px "${fontFamily}", sans-serif`;
        ctx.fillStyle = fill;
        ctx.textBaseline = 'top';

        if (layer.curve && layer.curve !== 0) {
            renderCurvedText(ctx, rawText, lx + lw / 2, ly + lh / 2, layer.curve * scale);
        } else {
            const align = layer.align || 'center';
            let startX = lx;
            if (align === 'center') {
                ctx.textAlign = 'center';
                startX = lx + lw / 2;
            } else if (align === 'right') {
                ctx.textAlign = 'right';
                startX = lx + lw;
            } else {
                ctx.textAlign = 'left';
            }

            const lines = rawText.split('\n');
            const lineHeight = (layer.lineHeight || 1.3) * fontSize;
            let currentY = ly;

            for (const line of lines) {
                ctx.fillText(line, startX, currentY);
                if (layer.stroke && layer.strokeWidth) {
                    ctx.strokeStyle = resolveColor(layer.stroke, palette);
                    ctx.lineWidth = Math.max(1, layer.strokeWidth * scale);
                    ctx.strokeText(line, startX, currentY);
                }
                currentY += lineHeight;
            }
        }
    }

    ctx.restore();
}

function renderCurvedText(ctx, text, cx, cy, radius) {
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

export function computeSha256(buffer) {
    return crypto.createHash('sha256').update(buffer).digest('hex');
}
