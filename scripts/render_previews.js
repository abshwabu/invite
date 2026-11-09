import fs from 'fs';
import path from 'path';
import { createCanvas, GlobalFonts } from '@napi-rs/canvas';

const FONTS_DIR = path.resolve(process.cwd(), 'public/fonts');
const STORAGE_TEMPLATES_DIR = path.resolve(process.cwd(), 'storage/app/public/templates');

if (!fs.existsSync(STORAGE_TEMPLATES_DIR)) {
    fs.mkdirSync(STORAGE_TEMPLATES_DIR, { recursive: true });
}

// Register all fonts
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

const sampleReplacements = {
    '{{guest_name}}': 'Almaz & Dawit',
    '{{event_title}}': 'Celebration of Joy',
    '{{date}}': 'Sunday, January 10, 2027',
    '{{time}}': '2:00 PM',
    '{{venue}}': 'Sheraton Addis, Lalibela Ballroom',
    '{{host_names}}': 'Abebe & Chaltu'
};

function renderDesignToCanvas(design) {
    const scale = 0.5; // Render 540x764 for fast crisp preview thumbnails
    const width = Math.round(design.width * scale);
    const height = Math.round(design.height * scale);

    const canvas = createCanvas(width, height);
    const ctx = canvas.getContext('2d');

    // 1. Background
    const bg = design.background;
    if (bg && bg.type === 'color') {
        ctx.fillStyle = bg.value;
        ctx.fillRect(0, 0, width, height);
    } else {
        ctx.fillStyle = '#FFFFFF';
        ctx.fillRect(0, 0, width, height);
    }

    // 2. Layers
    if (Array.isArray(design.layers)) {
        for (const layer of design.layers) {
            ctx.save();
            ctx.globalAlpha = layer.opacity !== undefined ? layer.opacity : 1.0;

            if (layer.type === 'shape') {
                const lx = layer.x * scale;
                const ly = layer.y * scale;
                const lw = layer.width * scale;
                const lh = layer.height * scale;

                if (layer.shape === 'rect') {
                    if (layer.fill && layer.fill !== 'transparent') {
                        ctx.fillStyle = layer.fill;
                        ctx.fillRect(lx, ly, lw, lh);
                    }
                    if (layer.stroke && layer.strokeWidth) {
                        ctx.strokeStyle = layer.stroke;
                        ctx.lineWidth = Math.max(1, layer.strokeWidth * scale);
                        ctx.strokeRect(lx, ly, lw, lh);
                    }
                } else if (layer.shape === 'line') {
                    ctx.strokeStyle = layer.stroke || '#000000';
                    ctx.lineWidth = Math.max(1, (layer.strokeWidth || 1) * scale);
                    ctx.beginPath();
                    ctx.moveTo(lx, ly);
                    ctx.lineTo(lx + lw, ly);
                    ctx.stroke();
                } else if (layer.shape === 'circle') {
                    const radius = Math.min(lw, lh) / 2;
                    ctx.beginPath();
                    ctx.arc(lx + radius, ly + radius, radius, 0, Math.PI * 2);
                    if (layer.fill && layer.fill !== 'transparent') {
                        ctx.fillStyle = layer.fill;
                        ctx.fill();
                    }
                    if (layer.stroke && layer.strokeWidth) {
                        ctx.strokeStyle = layer.stroke;
                        ctx.lineWidth = Math.max(1, layer.strokeWidth * scale);
                        ctx.stroke();
                    }
                }
            } else if (layer.type === 'text') {
                let text = layer.text || '';
                for (const [token, val] of Object.entries(sampleReplacements)) {
                    text = text.replace(new RegExp(token.replace(/[{}]/g, '\\$&'), 'g'), val);
                }

                const fontSize = Math.max(10, Math.round((layer.fontSize || 24) * scale));
                const fontFamily = layer.fontFamily || 'Noto Sans Ethiopic';
                const fontWeight = layer.fontWeight || 400;

                ctx.font = `${fontWeight} ${fontSize}px "${fontFamily}", sans-serif`;
                ctx.fillStyle = layer.fill || '#000000';
                ctx.textBaseline = 'top';

                const align = layer.align || 'center';
                let startX = layer.x * scale;
                if (align === 'center') {
                    ctx.textAlign = 'center';
                    startX += (layer.width * scale) / 2;
                } else if (align === 'right') {
                    ctx.textAlign = 'right';
                    startX += layer.width * scale;
                } else {
                    ctx.textAlign = 'left';
                }

                const lines = text.split('\n');
                const lineHeight = (layer.lineHeight || 1.3) * fontSize;
                let currentY = layer.y * scale;

                for (const line of lines) {
                    ctx.fillText(line, startX, currentY);
                    currentY += lineHeight;
                }
            }

            ctx.restore();
        }
    }

    return canvas;
}

// Read input file from arguments
const inputFile = process.argv[2];
if (!inputFile || !fs.existsSync(inputFile)) {
    console.error('Usage: node render_previews.js <templates.json>');
    process.exit(1);
}

const templates = JSON.parse(fs.readFileSync(inputFile, 'utf8'));
console.log(`Rendering previews for ${templates.length} templates...`);

let rendered = 0;
for (const t of templates) {
    try {
        const previewRel = t.preview_path.replace(/^\/storage\//, '').replace(/^storage\//, '');
        const outPath = path.join(process.cwd(), 'storage/app/public', previewRel);
        const dir = path.dirname(outPath);
        if (!fs.existsSync(dir)) {
            fs.mkdirSync(dir, { recursive: true });
        }

        const design = typeof t.design_json === 'string' ? JSON.parse(t.design_json) : t.design_json;
        const canvas = renderDesignToCanvas(design);
        const buf = canvas.toBuffer('image/png');
        fs.writeFileSync(outPath, buf);
        rendered++;
    } catch (e) {
        console.error(`Failed to render template ${t.id} (${t.name}):`, e.message);
    }
}

console.log(`Successfully rendered ${rendered}/${templates.length} preview PNGs.`);
