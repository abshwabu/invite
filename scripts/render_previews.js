import fs from 'fs';
import path from 'path';
import crypto from 'crypto';
import { renderDesignToNodeCanvas } from './node_card_renderer.js';

const STORAGE_TEMPLATES_DIR = path.resolve(process.cwd(), 'storage/app/public/templates');
const HASHES_FILE = path.join(STORAGE_TEMPLATES_DIR, '.preview_hashes.json');

if (!fs.existsSync(STORAGE_TEMPLATES_DIR)) {
    fs.mkdirSync(STORAGE_TEMPLATES_DIR, { recursive: true });
}

// Read input file from arguments
const inputFile = process.argv[2];
const force = process.argv.includes('--force');

if (!inputFile || !fs.existsSync(inputFile)) {
    console.error('Usage: node render_previews.js <templates.json> [--force]');
    process.exit(1);
}

let previewHashes = {};
if (fs.existsSync(HASHES_FILE)) {
    try {
        previewHashes = JSON.parse(fs.readFileSync(HASHES_FILE, 'utf8'));
    } catch {
        previewHashes = {};
    }
}

const templates = JSON.parse(fs.readFileSync(inputFile, 'utf8'));
console.log(`Processing previews for ${templates.length} templates (force=${force})...`);

const outputs = {};
let renderedCount = 0;
let skippedCount = 0;

for (const t of templates) {
    try {
        const rawDesign = typeof t.design_json === 'string' ? JSON.parse(t.design_json) : t.design_json;
        // Derive clean base slug from suite_key or preview_path or id
        let baseSlug = t.suite_key;
        if (t.kind === 'thank_you' && baseSlug && !baseSlug.endsWith('-thankyou')) {
            baseSlug = `${baseSlug}-thankyou`;
        }
        if (!baseSlug && t.preview_path) {
            baseSlug = path.basename(t.preview_path, path.extname(t.preview_path));
        }
        if (!baseSlug) {
            baseSlug = `template_${t.id}`;
        }

        const designHash = crypto.createHash('sha256')
            .update(JSON.stringify(rawDesign) + (t.name || '') + baseSlug)
            .digest('hex');

        // File paths
        const enPngPath = path.join(STORAGE_TEMPLATES_DIR, `${baseSlug}.png`);
        const enWebp640Path = path.join(STORAGE_TEMPLATES_DIR, `${baseSlug}_640.webp`);
        const enWebp320Path = path.join(STORAGE_TEMPLATES_DIR, `${baseSlug}_320.webp`);

        const amPngPath = path.join(STORAGE_TEMPLATES_DIR, `${baseSlug}_am.png`);
        const amWebp640Path = path.join(STORAGE_TEMPLATES_DIR, `${baseSlug}_am_640.webp`);
        const amWebp320Path = path.join(STORAGE_TEMPLATES_DIR, `${baseSlug}_am_320.webp`);

        const filesExist = fs.existsSync(enPngPath) &&
            fs.existsSync(enWebp640Path) &&
            fs.existsSync(enWebp320Path) &&
            fs.existsSync(amPngPath) &&
            fs.existsSync(amWebp640Path) &&
            fs.existsSync(amWebp320Path);

        const isCached = !force && filesExist && previewHashes[t.id] === designHash;

        if (isCached) {
            previewHashes[t.id] = designHash;
            skippedCount++;
        } else {
            // Render English: 1080px (PNG), 640px (WebP), 320px (WebP)
            const cEn1080 = renderDesignToNodeCanvas(rawDesign, { scale: 1.0, lang: 'en' });
            fs.writeFileSync(enPngPath, cEn1080.toBuffer('image/png'));

            const cEn640 = renderDesignToNodeCanvas(rawDesign, { scale: 640 / 1080, lang: 'en' });
            fs.writeFileSync(enWebp640Path, cEn640.toBuffer('image/webp'));

            const cEn320 = renderDesignToNodeCanvas(rawDesign, { scale: 320 / 1080, lang: 'en' });
            fs.writeFileSync(enWebp320Path, cEn320.toBuffer('image/webp'));

            // Render Amharic: 1080px (PNG), 640px (WebP), 320px (WebP)
            const cAm1080 = renderDesignToNodeCanvas(rawDesign, { scale: 1.0, lang: 'am' });
            fs.writeFileSync(amPngPath, cAm1080.toBuffer('image/png'));

            const cAm640 = renderDesignToNodeCanvas(rawDesign, { scale: 640 / 1080, lang: 'am' });
            fs.writeFileSync(amWebp640Path, cAm640.toBuffer('image/webp'));

            const cAm320 = renderDesignToNodeCanvas(rawDesign, { scale: 320 / 1080, lang: 'am' });
            fs.writeFileSync(amWebp320Path, cAm320.toBuffer('image/webp'));

            previewHashes[t.id] = designHash;
            renderedCount++;

            // Periodically persist hashes
            if (renderedCount % 10 === 0) {
                fs.writeFileSync(HASHES_FILE, JSON.stringify(previewHashes, null, 2));
            }
        }

        outputs[t.id] = {
            preview_path: `/storage/templates/${baseSlug}.png`,
            preview_path_am: `/storage/templates/${baseSlug}_am.png`,
            preview_path_640: `/storage/templates/${baseSlug}_640.webp`,
            preview_path_320: `/storage/templates/${baseSlug}_320.webp`,
            preview_path_am_640: `/storage/templates/${baseSlug}_am_640.webp`,
            preview_path_am_320: `/storage/templates/${baseSlug}_am_320.webp`,
        };
    } catch (e) {
        console.error(`Error rendering template ${t.id} (${t.name}):`, e.message);
    }
}

fs.writeFileSync(HASHES_FILE, JSON.stringify(previewHashes, null, 2));

// Save output results mapping so Laravel command can update records
const outputPath = inputFile.replace(/\.json$/, '_output.json');
fs.writeFileSync(outputPath, JSON.stringify(outputs, null, 2));

console.log(`Finished: ${renderedCount} rendered, ${skippedCount} skipped (cached). Total: ${templates.length}`);
