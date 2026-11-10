const fs = require('fs');
const path = require('path');
const { createCanvas } = require('@napi-rs/canvas');
const { Resvg } = require('@resvg/resvg-js');

const ROOT_DIR = path.resolve(__dirname, '..');
const ORNAMENTS_DIR = path.join(ROOT_DIR, 'resources/ornaments');
const MANIFEST_PATH = path.join(ORNAMENTS_DIR, 'manifest.json');
const OUTPUT_DIR = path.join(ROOT_DIR, 'storage/app/ornament-sheets');

if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
}

const manifest = JSON.parse(fs.readFileSync(MANIFEST_PATH, 'utf-8'));

// Two distinct test palettes
const PALETTES = {
    A: {
        name: 'Royal Gold & Crimson',
        c1: '#B8860B',
        c2: '#8B0000',
        c3: '#D4AF37',
        lightBg: '#FAF7F2',
        darkBg: '#14171A'
    },
    B: {
        name: 'Emerald & Copper',
        c1: '#1E5E41',
        c2: '#B85D19',
        c3: '#5C9E7A',
        lightBg: '#F3F6F4',
        darkBg: '#0D1B16'
    }
};

function tintSvg(svgContent, c1, c2, c3) {
    return svgContent
        .replace(/\{\{c1\}\}/g, c1)
        .replace(/\{\{c2\}\}/g, c2)
        .replace(/\{\{c3\}\}/g, c3);
}

function renderSvgToPng(svgString, targetWidth, targetHeight) {
    try {
        const resvg = new Resvg(svgString, {
            fitTo: {
                mode: 'width',
                value: targetWidth
            }
        });
        const pngData = resvg.render();
        return pngData.asPng();
    } catch (err) {
        console.error('Resvg render error:', err);
        return null;
    }
}

async function generateSheetForSet(setName) {
    const ornaments = manifest.filter(o => o.set === setName);
    if (ornaments.length === 0) {
        console.warn(`No ornaments found for set "${setName}".`);
        return;
    }

    console.log(`Generating contact sheet for "${setName}" (${ornaments.length} ornaments)...`);

    // Layout configuration
    // Card width = 540, height = 300
    // 2 cards per row
    const cols = 2;
    const rows = Math.ceil(ornaments.length / cols);
    const cardWidth = 560;
    const cardHeight = 320;
    const pad = 30;
    const headerHeight = 100;

    const canvasWidth = pad * 2 + cols * cardWidth + (cols - 1) * pad;
    const canvasHeight = pad * 2 + headerHeight + rows * cardHeight + (rows - 1) * pad;

    const canvas = createCanvas(canvasWidth, canvasHeight);
    const ctx = canvas.getContext('2d');

    // Canvas background
    ctx.fillStyle = '#0B0F19';
    ctx.fillRect(0, 0, canvasWidth, canvasHeight);

    // Header
    ctx.fillStyle = '#F3F4F6';
    ctx.font = 'bold 32px sans-serif';
    ctx.fillText(`Ornament Contact Sheet: ${setName.toUpperCase()}`, pad, pad + 40);

    ctx.fillStyle = '#9CA3AF';
    ctx.font = '16px sans-serif';
    ctx.fillText(`Total Ornaments: ${ornaments.length} | Palettes: A (Gold/Crimson), B (Emerald/Copper) on Light & Dark`, pad, pad + 70);

    const { loadImage } = require('@napi-rs/canvas');

    for (let i = 0; i < ornaments.length; i++) {
        const o = ornaments[i];
        const col = i % cols;
        const row = Math.floor(i / cols);

        const cardX = pad + col * (cardWidth + pad);
        const cardY = pad + headerHeight + row * (cardHeight + pad);

        // Card container
        ctx.fillStyle = '#1A2234';
        ctx.strokeStyle = '#2E3A52';
        ctx.lineWidth = 1.5;
        ctx.beginPath();
        ctx.roundRect(cardX, cardY, cardWidth, cardHeight, 10);
        ctx.fill();
        ctx.stroke();

        // Card title and details
        ctx.fillStyle = '#FFFFFF';
        ctx.font = 'bold 16px sans-serif';
        ctx.fillText(o.id, cardX + 16, cardY + 28);

        ctx.fillStyle = '#94A3B8';
        ctx.font = '12px sans-serif';
        ctx.fillText(`${o.name_en} (${o.name_am}) • anchor: ${o.anchor} • aspect: ${o.defaultAspect}`, cardX + 16, cardY + 48);

        // Read raw SVG
        const svgPath = path.join(ORNAMENTS_DIR, o.set, `${o.id}.svg`);
        if (!fs.existsSync(svgPath)) {
            ctx.fillStyle = '#EF4444';
            ctx.fillText(`Missing file: ${o.id}.svg`, cardX + 16, cardY + 80);
            continue;
        }

        const rawSvg = fs.readFileSync(svgPath, 'utf-8');

        // 4 Preview Panels:
        // P1: Palette A Light, P2: Palette A Dark
        // P3: Palette B Light, P4: Palette B Dark
        const previewW = 120;
        const previewH = 110;
        const previewPad = 12;
        const startX = cardX + 16;
        const startY = cardY + 60;

        const panels = [
            { pal: PALETTES.A, bg: PALETTES.A.lightBg, label: 'Pal A (Light)', x: startX, y: startY },
            { pal: PALETTES.A, bg: PALETTES.A.darkBg, label: 'Pal A (Dark)', x: startX + previewW + previewPad, y: startY },
            { pal: PALETTES.B, bg: PALETTES.B.lightBg, label: 'Pal B (Light)', x: startX + (previewW + previewPad) * 2, y: startY },
            { pal: PALETTES.B, bg: PALETTES.B.darkBg, label: 'Pal B (Dark)', x: startX + (previewW + previewPad) * 3, y: startY }
        ];

        for (const p of panels) {
            // Draw panel background
            ctx.fillStyle = p.bg;
            ctx.strokeStyle = '#374151';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.roundRect(p.x, p.y, previewW, previewH, 6);
            ctx.fill();
            ctx.stroke();

            // Label
            ctx.fillStyle = '#64748B';
            ctx.font = '10px sans-serif';
            ctx.fillText(p.label, p.x + 6, p.y + previewH + 15);

            // Render SVG with this palette
            const tinted = tintSvg(rawSvg, p.pal.c1, p.pal.c2, p.pal.c3);
            const pngBuf = renderSvgToPng(tinted, 300, 300);

            if (pngBuf) {
                const img = await loadImage(pngBuf);
                // Compute aspect-fit inside preview box (with 8px padding)
                const maxW = previewW - 16;
                const maxH = previewH - 16;
                let drawW = maxW;
                let drawH = drawW / o.defaultAspect;
                if (drawH > maxH) {
                    drawH = maxH;
                    drawW = drawH * o.defaultAspect;
                }
                const drawX = p.x + (previewW - drawW) / 2;
                const drawY = p.y + (previewH - drawH) / 2;
                ctx.drawImage(img, drawX, drawY, drawW, drawH);
            }
        }
    }

    const outPath = path.join(OUTPUT_DIR, `${setName}.png`);
    const buffer = canvas.toBuffer('image/png');
    fs.writeFileSync(outPath, buffer);
    console.log(`Saved contact sheet to ${outPath} (${(buffer.length / 1024).toFixed(1)} KB)`);
}

async function main() {
    const targetSet = process.argv[2] || 'all';

    const sets = [
        'wedding',
        'anniversary',
        'funeral',
        'faith-symbols',
        'religious-islamic',
        'religious-ethiopian'
    ];

    if (targetSet === 'all') {
        for (const s of sets) {
            await generateSheetForSet(s);
        }
    } else {
        await generateSheetForSet(targetSet);
    }
}

main().catch(err => {
    console.error(err);
    process.exit(1);
});
