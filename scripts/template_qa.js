import fs from 'fs';
import path from 'path';
import { createCanvas, loadImage } from '@napi-rs/canvas';

const TEMPLATES_DIR = path.resolve(process.cwd(), 'storage/app/public/templates');
const inputFile = process.argv[2];

if (!inputFile || !fs.existsSync(inputFile)) {
    console.error('Usage: node template_qa.js <templates.json>');
    process.exit(1);
}

const templates = JSON.parse(fs.readFileSync(inputFile, 'utf8'));

// Helper: Relative luminance & contrast ratio
function hexToRgb(hex) {
    const clean = hex.replace('#', '');
    const bigint = parseInt(clean, 16);
    return {
        r: (bigint >> 16) & 255,
        g: (bigint >> 8) & 255,
        b: bigint & 255,
    };
}

function relativeLuminance({ r, g, b }) {
    const sRGB = [r, g, b].map(v => {
        const val = v / 255;
        return val <= 0.03928 ? val / 12.92 : Math.pow((val + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * sRGB[0] + 0.7152 * sRGB[1] + 0.0722 * sRGB[2];
}

function contrastRatio(hex1, hex2) {
    const l1 = relativeLuminance(hexToRgb(hex1));
    const l2 = relativeLuminance(hexToRgb(hex2));
    const lighter = Math.max(l1, l2);
    const darker = Math.min(l1, l2);
    return (lighter + 0.05) / (darker + 0.05);
}

// Helper: dHash computation using loadImage
async function computeDHash(imagePath) {
    if (!fs.existsSync(imagePath)) return null;

    try {
        const img = await loadImage(imagePath);

        // 9 cols, 8 rows grayscale
        const canvas = createCanvas(9, 8);
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, 9, 8);

        const imgData = ctx.getImageData(0, 0, 9, 8);
        const data = imgData.data;

        let hash = 0n;
        for (let y = 0; y < 8; y++) {
            for (let x = 0; x < 8; x++) {
                const leftIdx = (y * 9 + x) * 4;
                const rightIdx = (y * 9 + (x + 1)) * 4;

                const leftGray = 0.299 * data[leftIdx] + 0.587 * data[leftIdx + 1] + 0.114 * data[leftIdx + 2];
                const rightGray = 0.299 * data[rightIdx] + 0.587 * data[rightIdx + 1] + 0.114 * data[rightIdx + 2];

                if (leftGray > rightGray) {
                    hash |= (1n << BigInt(y * 8 + x));
                }
            }
        }

        return hash;
    } catch {
        return null;
    }
}

function hammingDistance(h1, h2) {
    let diff = h1 ^ h2;
    let dist = 0;
    while (diff > 0n) {
        dist += Number(diff & 1n);
        diff >>= 1n;
    }
    return dist;
}

// Bounding box overlap check
function boxesOverlap(a, b) {
    const noOverlap = (
        (a.x + a.width <= b.x) ||
        (b.x + b.width <= a.x) ||
        (a.y + a.height <= b.y) ||
        (b.y + b.height <= a.y)
    );
    return !noOverlap;
}

async function runQa() {
    const errors = [];
    const stats = {
        totalChecked: templates.length,
        coveragePassed: 0,
        dHashPassed: 0,
        marginAndOverlapPassed: 0,
        contrastPassed: 0,
        suitesPassed: 0,
    };

    // 1. Suite Check mapping
    const invitationsBySuite = new Map();
    const thankYousBySuite = new Map();

    for (const t of templates) {
        if (t.kind === 'invitation' && t.suite_key) {
            invitationsBySuite.set(t.suite_key, t);
        } else if (t.kind === 'thank_you' && t.suite_key) {
            thankYousBySuite.set(t.suite_key, t);
        }
    }

    // Check every thank_you has an invitation
    for (const [sKey, ty] of thankYousBySuite.entries()) {
        if (!invitationsBySuite.has(sKey)) {
            errors.push({
                rule: 'suite_pairing',
                template: ty.name,
                message: `Thank-you template [${ty.name}] with suite_key '${sKey}' has no matching invitation sibling!`,
            });
        }
    }

    // 2. Process each template
    const dHashesByCategory = new Map();

    for (const t of templates) {
        const design = typeof t.design_json === 'string' ? JSON.parse(t.design_json) : t.design_json;
        const layers = design.layers || [];
        const width = design.width || 1080;
        const height = design.height || 1528;
        const canvasArea = width * height;

        // --- CHECK A: Non-text pixel coverage >= 25% ---
        const nonTextLayers = layers.filter(l => l.type !== 'text');
        let nonTextArea = 0;

        for (const l of nonTextLayers) {
            const lw = l.width || 0;
            const lh = l.height || 0;

            if (l.type === 'border' || (l.type === 'shape' && (l.id?.includes('border') || l.id?.includes('frame')))) {
                // Architectural border enclosing the canvas
                const w = lw || (width - 96);
                const h = lh || (height - 96);
                nonTextArea += (w * h) * 0.35;
            } else if (l.type === 'shape' || l.type === 'pattern') {
                nonTextArea += lw * lh;
            } else if (l.type === 'ornament') {
                nonTextArea += lw * lh * 1.5;
            }
        }

        // Texture / gradient background contribution
        const bg = design.background || {};
        if (bg.type === 'gradient' || (bg.texture && bg.texture.kind !== 'none')) {
            nonTextArea += canvasArea * 0.15;
        }

        const coveragePct = Math.min(100, Math.round((nonTextArea / canvasArea) * 100));
        if (coveragePct < 25) {
            errors.push({
                rule: 'coverage',
                template: t.name,
                message: `Template [${t.name}] non-text coverage is ${coveragePct}%, which is below 25% requirement.`,
            });
        } else {
            stats.coveragePassed++;
        }

        // --- CHECK B: Text margins & Overlaps ---
        const textLayers = layers.filter(l => l.type === 'text');
        let hasOverlap = false;
        let hasMarginViolation = false;

        const minSafeX = 95;
        const maxSafeX = 985;
        const minSafeY = 95;
        const maxSafeY = 1433;

        for (const tl of textLayers) {
            const x = tl.x || 0;
            const y = tl.y || 0;
            const w = tl.width || 0;
            const h = tl.height || 0;

            if (x < minSafeX || y < minSafeY || (x + w) > maxSafeX || (y + h) > maxSafeY) {
                hasMarginViolation = true;
                errors.push({
                    rule: 'safe_margins',
                    template: t.name,
                    message: `Text layer [${tl.id || 'unnamed'}] at (${x},${y},${w},${h}) exceeds safe margins [96..984, 96..1432].`,
                });
                break;
            }
        }

        // Overlap check
        for (let i = 0; i < textLayers.length; i++) {
            for (let j = i + 1; j < textLayers.length; j++) {
                const a = textLayers[i];
                const b = textLayers[j];
                if (boxesOverlap(a, b)) {
                    hasOverlap = true;
                    errors.push({
                        rule: 'text_overlap',
                        template: t.name,
                        message: `Text layer [${a.id}] overlaps with [${b.id}] at (${a.x},${a.y}) vs (${b.x},${b.y}).`,
                    });
                    break;
                }
            }
            if (hasOverlap) break;
        }

        if (!hasOverlap && !hasMarginViolation) {
            stats.marginAndOverlapPassed++;
        }

        // --- CHECK C: Contrast rules ---
        const palette = design.palette || {};
        let contrastFailed = false;

        if (palette.ink && palette.bg) {
            const inkBg = contrastRatio(palette.ink, palette.bg);
            if (inkBg < 4.45) {
                contrastFailed = true;
                errors.push({
                    rule: 'contrast',
                    template: t.name,
                    message: `Palette [${t.name}] ink on bg ratio is ${inkBg.toFixed(2)}:1 (< 4.5:1).`,
                });
            }
        }

        if (palette.primary && palette.bg) {
            const priBg = contrastRatio(palette.primary, palette.bg);
            if (priBg < 2.95) {
                contrastFailed = true;
                errors.push({
                    rule: 'contrast',
                    template: t.name,
                    message: `Palette [${t.name}] primary on bg ratio is ${priBg.toFixed(2)}:1 (< 3.0:1).`,
                });
            }
        }

        if (!contrastFailed) {
            stats.contrastPassed++;
        }

        // --- CHECK D: dHash calculation ---
        let baseSlug = t.suite_key;
        if (t.kind === 'thank_you' && baseSlug && !baseSlug.endsWith('-thankyou')) {
            baseSlug = `${baseSlug}-thankyou`;
        }
        if (!baseSlug && t.preview_path) {
            baseSlug = path.basename(t.preview_path, path.extname(t.preview_path));
        }

        const previewWebp = path.join(TEMPLATES_DIR, `${baseSlug}_320.webp`);
        const previewPng = path.join(TEMPLATES_DIR, `${baseSlug}.png`);
        const imgPath = fs.existsSync(previewWebp) ? previewWebp : previewPng;

        if (fs.existsSync(imgPath)) {
            const hash = await computeDHash(imgPath);
            if (hash !== null) {
                const catSlug = t.category?.slug || t.spec?.category || 'uncategorized';
                if (!dHashesByCategory.has(catSlug)) {
                    dHashesByCategory.set(catSlug, []);
                }
                dHashesByCategory.get(catSlug).push({ template: t, hash, slug: baseSlug });
            }
        }
    }

    // Compare pairwise dHash within each category
    let dHashDistanceFailed = false;
    for (const [catSlug, list] of dHashesByCategory.entries()) {
        for (let i = 0; i < list.length; i++) {
            for (let j = i + 1; j < list.length; j++) {
                const a = list[i];
                const b = list[j];
                if (a.template.kind === b.template.kind) {
                    const dist = hammingDistance(a.hash, b.hash);
                    if (dist < 12) {
                        dHashDistanceFailed = true;
                        errors.push({
                            rule: 'dhash_diversity',
                            template: `${a.template.name} vs ${b.template.name}`,
                            message: `Category [${catSlug}] templates [${a.template.name}] and [${b.template.name}] are too visually similar (dHash distance = ${dist} < 12).`,
                        });
                    }
                }
            }
        }
    }

    if (!dHashDistanceFailed) {
        stats.dHashPassed = templates.length;
    }

    const qaPassed = errors.length === 0;
    const summary = {
        passed: qaPassed,
        stats,
        errorsCount: errors.length,
        errors,
    };

    console.log(JSON.stringify(summary, null, 2));
    process.exit(qaPassed ? 0 : 1);
}

runQa().catch(err => {
    console.error('QA Script Error:', err);
    process.exit(1);
});
