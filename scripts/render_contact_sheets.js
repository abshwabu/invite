import fs from 'fs';
import path from 'path';
import { createCanvas, loadImage } from '@napi-rs/canvas';

const TEMPLATES_DIR = path.resolve(process.cwd(), 'storage/app/public/templates');
const OUTPUT_DIR = path.resolve(process.cwd(), 'storage/app/template-qa');

if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
}

const inputFile = process.argv[2];
if (!inputFile || !fs.existsSync(inputFile)) {
    console.error('Usage: node render_contact_sheets.js <templates.json>');
    process.exit(1);
}

const templates = JSON.parse(fs.readFileSync(inputFile, 'utf8'));

// Group by category
const categoriesMap = new Map();

for (const t of templates) {
    const catSlug = t.category?.slug || t.spec?.category || 'uncategorized';
    if (!categoriesMap.has(catSlug)) {
        categoriesMap.set(catSlug, {
            slug: catSlug,
            nameEn: t.category?.name_en || catSlug.toUpperCase(),
            nameAm: t.category?.name_am || '',
            invitations: [],
            thankYous: [],
        });
    }

    if (t.kind === 'thank_you') {
        categoriesMap.get(catSlug).thankYous.push(t);
    } else {
        categoriesMap.get(catSlug).invitations.push(t);
    }
}

function getCardImagePath(t) {
    let baseSlug = t.suite_key;
    if (t.kind === 'thank_you' && baseSlug && !baseSlug.endsWith('-thankyou')) {
        baseSlug = `${baseSlug}-thankyou`;
    }
    if (!baseSlug && t.preview_path) {
        baseSlug = path.basename(t.preview_path, path.extname(t.preview_path));
    }
    const webp320 = path.join(TEMPLATES_DIR, `${baseSlug}_320.webp`);
    const png = path.join(TEMPLATES_DIR, `${baseSlug}.png`);
    if (fs.existsSync(webp320)) return webp320;
    if (fs.existsSync(png)) return png;
    return null;
}

// 1. Render contact sheet for each category
const CARD_W = 270;
const CARD_H = 382;
const LABEL_H = 54;
const CELL_W = CARD_W;
const CELL_H = CARD_H + LABEL_H;
const COLS = 5;
const PAD = 36;
const GAP = 20;

async function generateSheets() {
    for (const [slug, catData] of categoriesMap.entries()) {
        const allCards = [...catData.invitations, ...catData.thankYous];
        const rows = Math.ceil(allCards.length / COLS);

        const sheetW = PAD * 2 + COLS * CELL_W + (COLS - 1) * GAP;
        const headerH = 90;
        const sheetH = headerH + PAD * 2 + rows * CELL_H + (rows - 1) * GAP;

        const canvas = createCanvas(sheetW, sheetH);
        const ctx = canvas.getContext('2d');

        // Background
        ctx.fillStyle = '#0f172a'; // dark slate
        ctx.fillRect(0, 0, sheetW, sheetH);

        // Header
        ctx.fillStyle = '#f8fafc';
        ctx.font = 'bold 32px sans-serif';
        ctx.fillText(`${catData.nameEn} Category QA Sheet`, PAD, 52);

        ctx.fillStyle = '#94a3b8';
        ctx.font = '16px sans-serif';
        ctx.fillText(
            `${catData.invitations.length} Invitations • ${catData.thankYous.length} Thank-You Suites • Total: ${allCards.length} Templates`,
            PAD,
            92
        );

        // Cards
        for (let idx = 0; idx < allCards.length; idx++) {
            const t = allCards[idx];
            const col = idx % COLS;
            const row = Math.floor(idx / COLS);
            const x = PAD + col * (CELL_W + GAP);
            const y = headerH + PAD + row * (CELL_H + GAP);

            // Card frame
            ctx.fillStyle = '#1e293b';
            ctx.fillRect(x, y, CELL_W, CELL_H);

            const imgPath = getCardImagePath(t);
            if (imgPath) {
                try {
                    const img = await loadImage(imgPath);
                    ctx.drawImage(img, x, y, CARD_W, CARD_H);
                } catch (err) {
                    ctx.fillStyle = '#334155';
                    ctx.fillRect(x, y, CARD_W, CARD_H);
                }
            } else {
                ctx.fillStyle = '#334155';
                ctx.fillRect(x, y, CARD_W, CARD_H);
            }

            // Border around thumbnail
            ctx.strokeStyle = t.kind === 'thank_you' ? '#10b981' : '#38bdf8';
            ctx.lineWidth = 1;
            ctx.strokeRect(x, y, CARD_W, CARD_H);

            // Label
            const labelY = y + CARD_H + 18;
            ctx.fillStyle = '#f1f5f9';
            ctx.font = 'bold 13px sans-serif';
            const name = (t.name || 'Template').length > 30 ? (t.name || '').substring(0, 27) + '...' : (t.name || '');
            ctx.fillText(name, x + 8, labelY);

            // Kind badge
            ctx.fillStyle = t.kind === 'thank_you' ? '#34d399' : '#38bdf8';
            ctx.font = '11px sans-serif';
            const badge = t.kind === 'thank_you' ? 'THANK-YOU' : 'INVITATION';
            const suite = t.suite_key ? ` • ${t.suite_key}` : '';
            ctx.fillText(`${badge}${suite}`, x + 8, labelY + 18);
        }

        const outPath = path.join(OUTPUT_DIR, `${slug}.png`);
        fs.writeFileSync(outPath, canvas.toBuffer('image/png'));
        console.log(`Rendered contact sheet: ${slug}.png (${sheetW}x${sheetH})`);
    }

    // 2. Render all-categories.png (4 templates per category)
    const CARDS_PER_CAT = 4;
    const CAT_ROWS = categoriesMap.size;
    const allSheetW = PAD * 2 + 180 + CARDS_PER_CAT * (CARD_W + GAP);
    const allHeaderH = 90;
    const rowHeight = CELL_H + GAP;
    const allSheetH = allHeaderH + PAD * 2 + CAT_ROWS * rowHeight;

    const allCanvas = createCanvas(allSheetW, allSheetH);
    const allCtx = allCanvas.getContext('2d');

    allCtx.fillStyle = '#090d16';
    allCtx.fillRect(0, 0, allSheetW, allSheetH);

    allCtx.fillStyle = '#ffffff';
    allCtx.font = 'bold 34px sans-serif';
    allCtx.fillText('All Categories Overview QA Contact Sheet', PAD, 52);

    allCtx.fillStyle = '#94a3b8';
    allCtx.font = '16px sans-serif';
    allCtx.fillText('4 Representative Templates per Category (3 Invitations + 1 Thank-You Suite)', PAD, 82);

    let rowIdx = 0;
    for (const [slug, catData] of categoriesMap.entries()) {
        const y = allHeaderH + PAD + rowIdx * rowHeight;

        // Category Label Sidebar
        allCtx.fillStyle = '#f8fafc';
        allCtx.font = 'bold 18px sans-serif';
        allCtx.fillText(catData.nameEn, PAD, y + 40);

        allCtx.fillStyle = '#94a3b8';
        allCtx.font = '13px sans-serif';
        allCtx.fillText(catData.slug, PAD, y + 64);
        allCtx.fillText(`${catData.invitations.length} inv / ${catData.thankYous.length} ty`, PAD, y + 84);

        // Pick 3 invitations + 1 thank you
        const sampleCards = [
            ...catData.invitations.slice(0, 3),
            ...(catData.thankYous.slice(0, 1) || catData.invitations.slice(3, 4)),
        ].slice(0, CARDS_PER_CAT);

        for (let cIdx = 0; cIdx < sampleCards.length; cIdx++) {
            const t = sampleCards[cIdx];
            const x = PAD + 180 + cIdx * (CARD_W + GAP);

            const imgPath = getCardImagePath(t);
            if (imgPath) {
                try {
                    const img = await loadImage(imgPath);
                    allCtx.drawImage(img, x, y, CARD_W, CARD_H);
                } catch {
                    allCtx.fillStyle = '#1e293b';
                    allCtx.fillRect(x, y, CARD_W, CARD_H);
                }
            }

            allCtx.strokeStyle = t.kind === 'thank_you' ? '#10b981' : '#38bdf8';
            allCtx.lineWidth = 1;
            allCtx.strokeRect(x, y, CARD_W, CARD_H);

            allCtx.fillStyle = '#ffffff';
            allCtx.font = 'bold 12px sans-serif';
            const name = (t.name || '').length > 28 ? (t.name || '').substring(0, 25) + '...' : (t.name || '');
            allCtx.fillText(name, x + 6, y + CARD_H + 18);

            allCtx.fillStyle = t.kind === 'thank_you' ? '#34d399' : '#38bdf8';
            allCtx.font = '11px sans-serif';
            allCtx.fillText(t.kind === 'thank_you' ? 'THANK-YOU' : 'INVITATION', x + 6, y + CARD_H + 34);
        }

        rowIdx++;
    }

    const allOutPath = path.join(OUTPUT_DIR, 'all-categories.png');
    fs.writeFileSync(allOutPath, allCanvas.toBuffer('image/png'));
    console.log(`Rendered all-categories contact sheet: all-categories.png (${allSheetW}x${allSheetH})`);
}

generateSheets().catch(err => {
    console.error('Contact sheets render failed:', err);
    process.exit(1);
});
