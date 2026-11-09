import fs from 'fs';
import { renderDesignToNodeCanvas, computeSha256 } from './node_card_renderer.js';

function parseArgs() {
    const args = process.argv.slice(2);
    const parsed = {};
    for (const arg of args) {
        if (arg.startsWith('--')) {
            const [key, ...vals] = arg.slice(2).split('=');
            parsed[key] = vals.join('=') || true;
        }
    }
    return parsed;
}

const args = parseArgs();

let design;
if (args.design) {
    if (fs.existsSync(args.design)) {
        design = JSON.parse(fs.readFileSync(args.design, 'utf8'));
    } else {
        design = JSON.parse(args.design);
    }
} else {
    // Read from stdin if available
    try {
        const input = fs.readFileSync(0, 'utf8');
        if (input) design = JSON.parse(input);
    } catch {}
}

if (!design) {
    console.error('Missing design JSON');
    process.exit(1);
}

let paletteOverride = null;
if (args.palette) {
    try {
        paletteOverride = typeof args.palette === 'string' && fs.existsSync(args.palette)
            ? JSON.parse(fs.readFileSync(args.palette, 'utf8'))
            : JSON.parse(args.palette);
    } catch {}
}

const scale = args.scale ? parseFloat(args.scale) : 0.5;
const lang = args.lang || 'en';

const canvas = renderDesignToNodeCanvas(design, {
    scale,
    lang,
    palette: paletteOverride
});

const pngBuffer = canvas.toBuffer('image/png');
const sha256 = computeSha256(pngBuffer);

if (args.out) {
    fs.writeFileSync(args.out, pngBuffer);
}

if (args.hash) {
    console.log(sha256);
} else {
    process.stdout.write(JSON.stringify({
        hash: sha256,
        width: canvas.width,
        height: canvas.height,
        size: pngBuffer.length
    }));
}
