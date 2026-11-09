import { Background, Palette } from '../types/design';
import { resolveColor } from './paletteResolver';

export function createPrng(seed: number) {
    let s = (seed || 1) | 0;
    return function () {
        s = (s + 0x6d2b79f5) | 0;
        let t = Math.imul(s ^ (s >>> 15), 1 | s);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

/**
 * Render procedural background onto any 2D canvas context.
 */
export function renderBackgroundToContext(
    ctx: CanvasRenderingContext2D,
    width: number,
    height: number,
    bg: Background,
    palette?: Partial<Palette>
): void {
    ctx.save();

    // 1. Base Fill or Gradient
    if (bg.type === 'gradient' && typeof bg.value === 'object') {
        const gVal = bg.value;
        let gradient: CanvasGradient;

        if (gVal.type === 'radial') {
            const cx = width / 2;
            const cy = height / 2;
            const radius = Math.hypot(cx, cy);
            gradient = ctx.createRadialGradient(cx, cy, 0, cx, cy, radius);
        } else {
            // Linear gradient with angle (degrees, default 180 = top-to-bottom)
            const angleDeg = gVal.angle !== undefined ? gVal.angle : 180;
            const angleRad = (angleDeg * Math.PI) / 180;
            const halfW = width / 2;
            const halfH = height / 2;
            const length = Math.abs(width * Math.sin(angleRad)) + Math.abs(height * Math.cos(angleRad));

            const x0 = halfW - (Math.sin(angleRad) * length) / 2;
            const y0 = halfH - (Math.cos(angleRad) * length) / 2;
            const x1 = halfW + (Math.sin(angleRad) * length) / 2;
            const y1 = halfH + (Math.cos(angleRad) * length) / 2;

            gradient = ctx.createLinearGradient(x0, y0, x1, y1);
        }

        const stops = gVal.stops || [
            [0, '@bg'],
            [1, '@surface'],
        ];

        for (const stop of stops) {
            let offset = 0;
            let colorVal = '#FFFFFF';
            if (Array.isArray(stop)) {
                offset = stop[0];
                colorVal = stop[1];
            } else if (typeof stop === 'object') {
                offset = stop.offset;
                colorVal = stop.color;
            }
            gradient.addColorStop(Math.max(0, Math.min(1, offset)), resolveColor(colorVal, palette));
        }

        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, width, height);
    } else {
        // Plain color or @role
        const colorVal = typeof bg.value === 'string' ? bg.value : '@bg';
        ctx.fillStyle = resolveColor(colorVal, palette);
        ctx.fillRect(0, 0, width, height);
    }

    // 2. Procedural Seed-Based Texture
    const texture = bg.texture;
    if (texture && texture.kind && texture.kind !== 'none') {
        renderTexture(ctx, width, height, texture.kind, texture.opacity || 0.08, texture.seed || 1, palette);
    }

    // 3. Vignette
    if (bg.vignette && bg.vignette > 0) {
        renderVignette(ctx, width, height, Math.min(1.0, bg.vignette));
    }

    ctx.restore();
}

function renderTexture(
    ctx: CanvasRenderingContext2D,
    width: number,
    height: number,
    kind: string,
    opacity: number,
    seed: number,
    palette?: Partial<Palette>
): void {
    const prng = createPrng(seed);
    ctx.save();
    ctx.globalAlpha = opacity;

    if (kind === 'grain') {
        // High frequency film grain
        const tileSize = 256;
        const imgData = ctx.createImageData(tileSize, tileSize);
        const data = imgData.data;

        for (let i = 0; i < data.length; i += 4) {
            const noise = Math.floor((prng() - 0.5) * 80);
            const val = Math.max(0, Math.min(255, 128 + noise));
            data[i] = val;
            data[i + 1] = val;
            data[i + 2] = val;
            data[i + 3] = 255;
        }

        // Draw tiled pattern using offscreen canvas if available or direct loop
        for (let x = 0; x < width; x += tileSize) {
            for (let y = 0; y < height; y += tileSize) {
                // Direct put or fill
                ctx.putImageData(imgData, x, y);
            }
        }
    } else if (kind === 'paper') {
        // Organic paper with subtle fiber specks
        ctx.strokeStyle = '#000000';
        ctx.fillStyle = '#000000';
        const fiberCount = Math.floor((width * height) / 2500);

        for (let i = 0; i < fiberCount; i++) {
            const x = prng() * width;
            const y = prng() * height;
            const len = 2 + prng() * 6;
            const angle = prng() * Math.PI * 2;
            const fiberAlpha = 0.05 + prng() * 0.15;

            ctx.globalAlpha = opacity * fiberAlpha;
            ctx.lineWidth = 0.5 + prng() * 0.8;
            ctx.beginPath();
            ctx.moveTo(x, y);
            ctx.lineTo(x + Math.cos(angle) * len, y + Math.sin(angle) * len);
            ctx.stroke();
        }
    } else if (kind === 'linen') {
        // Cross-hatch woven thread texture
        ctx.strokeStyle = '#FFFFFF';
        ctx.lineWidth = 1;
        const step = 6;

        for (let x = 0; x < width; x += step) {
            const alpha = 0.05 + prng() * 0.1;
            ctx.globalAlpha = opacity * alpha;
            ctx.beginPath();
            ctx.moveTo(x, 0);
            ctx.lineTo(x, height);
            ctx.stroke();
        }

        ctx.strokeStyle = '#000000';
        for (let y = 0; y < height; y += step) {
            const alpha = 0.05 + prng() * 0.1;
            ctx.globalAlpha = opacity * alpha;
            ctx.beginPath();
            ctx.moveTo(0, y);
            ctx.lineTo(width, y);
            ctx.stroke();
        }
    } else if (kind === 'watercolor') {
        // Soft layered translucent watercolor blooms
        const blobCount = 18;
        const primaryColor = resolveColor('@primary', palette);

        for (let i = 0; i < blobCount; i++) {
            const bx = prng() * width;
            const by = prng() * height;
            const radius = Math.min(width, height) * (0.15 + prng() * 0.35);

            const radial = ctx.createRadialGradient(bx, by, radius * 0.1, bx, by, radius);
            radial.addColorStop(0, primaryColor);
            radial.addColorStop(1, 'rgba(255, 255, 255, 0)');

            ctx.globalAlpha = opacity * (0.2 + prng() * 0.3);
            ctx.fillStyle = radial;
            ctx.beginPath();
            ctx.arc(bx, by, radius, 0, Math.PI * 2);
            ctx.fill();
        }
    }

    ctx.restore();
}

function renderVignette(ctx: CanvasRenderingContext2D, width: number, height: number, strength: number): void {
    ctx.save();
    const cx = width / 2;
    const cy = height / 2;
    const innerRadius = Math.min(width, height) * 0.42;
    const outerRadius = Math.hypot(cx, cy);

    const grad = ctx.createRadialGradient(cx, cy, innerRadius, cx, cy, outerRadius);
    grad.addColorStop(0, 'rgba(0,0,0,0)');
    grad.addColorStop(1, `rgba(0,0,0,${Math.min(0.85, strength * 0.65)})`);

    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, width, height);
    ctx.restore();
}
