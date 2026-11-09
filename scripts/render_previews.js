import fs from 'fs';
import path from 'path';
import { renderDesignToNodeCanvas } from './node_card_renderer.js';

const STORAGE_TEMPLATES_DIR = path.resolve(process.cwd(), 'storage/app/public/templates');

if (!fs.existsSync(STORAGE_TEMPLATES_DIR)) {
    fs.mkdirSync(STORAGE_TEMPLATES_DIR, { recursive: true });
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
        const canvas = renderDesignToNodeCanvas(design, { scale: 0.5 });
        const buf = canvas.toBuffer('image/png');
        fs.writeFileSync(outPath, buf);
        rendered++;
    } catch (e) {
        console.error(`Failed to render template ${t.id} (${t.name}):`, e.message);
    }
}

console.log(`Successfully rendered ${rendered}/${templates.length} preview PNGs.`);
