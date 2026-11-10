const fs = require('fs');
const path = require('path');
const { createCanvas } = require('@napi-rs/canvas');
const { renderDesignToNodeCanvas } = require('./node_card_renderer.js');

const jsonPath = path.resolve(process.cwd(), 'storage/app/recipe-sheets/designs.json');
if (!fs.existsSync(jsonPath)) {
    console.error(`designs.json not found at: ${jsonPath}`);
    process.exit(1);
}

const cards = JSON.parse(fs.readFileSync(jsonPath, 'utf8'));
console.log(`Loaded ${cards.length} designs. Rendering 12-card sheet...`);

const cols = 4;
const rows = 3;
const cardScale = 0.35;
const cardW = Math.round(1080 * cardScale); // ~378px
const cardH = Math.round(1528 * cardScale); // ~535px

const gapX = 32;
const gapY = 48;
const labelH = 32;
const headerH = 100;
const padding = 40;

const totalW = padding * 2 + cols * cardW + (cols - 1) * gapX;
const totalH = padding * 2 + headerH + rows * (cardH + labelH) + (rows - 1) * gapY;

const sheetCanvas = createCanvas(totalW, totalH);
const sheetCtx = sheetCanvas.getContext('2d');

// Background
sheetCtx.fillStyle = '#111827'; // Dark slate
sheetCtx.fillRect(0, 0, totalW, totalH);

// Sheet Header
sheetCtx.fillStyle = '#F9FAFB';
sheetCtx.font = 'bold 36px sans-serif';
sheetCtx.textAlign = 'center';
sheetCtx.fillText('Invite - Step 4D: 12 Layout Recipes Showcase', totalW / 2, padding + 40);

sheetCtx.fillStyle = '#9CA3AF';
sheetCtx.font = '20px sans-serif';
sheetCtx.fillText('12 Layout Recipes × 40 Contrast-Verified Palettes × 18 Font Pairings × 129 Hand-Authored Ornaments', totalW / 2, padding + 76);

// Render each card
cards.forEach((card, index) => {
    const col = index % cols;
    const row = Math.floor(index / cols);

    const x = padding + col * (cardW + gapX);
    const y = padding + headerH + row * (cardH + labelH + gapY);

    console.log(`Rendering [${index + 1}/12] ${card.label}...`);
    const cardCanvas = renderDesignToNodeCanvas(card.design, {
        scale: cardScale,
        lang: card.locale,
    });

    // Shadow & Card frame
    sheetCtx.save();
    sheetCtx.shadowColor = 'rgba(0, 0, 0, 0.4)';
    sheetCtx.shadowBlur = 16;
    sheetCtx.shadowOffsetX = 0;
    sheetCtx.shadowOffsetY = 8;
    sheetCtx.drawImage(cardCanvas, x, y, cardW, cardH);
    sheetCtx.restore();

    // Card border
    sheetCtx.strokeStyle = 'rgba(255, 255, 255, 0.15)';
    sheetCtx.lineWidth = 1;
    sheetCtx.strokeRect(x, y, cardW, cardH);

    // Label below card
    sheetCtx.fillStyle = '#E5E7EB';
    sheetCtx.font = 'bold 15px sans-serif';
    sheetCtx.textAlign = 'center';
    sheetCtx.fillText(card.label, x + cardW / 2, y + cardH + 22);
});

// Save to storage/app/recipe-sheets/recipes-12.png
const outDir = path.resolve(process.cwd(), 'storage/app/recipe-sheets');
if (!fs.existsSync(outDir)) {
    fs.mkdirSync(outDir, { recursive: true });
}
const outPath = path.join(outDir, 'recipes-12.png');
const buffer = sheetCanvas.toBuffer('image/png');
fs.writeFileSync(outPath, buffer);

console.log(`Contact sheet saved to: ${outPath} (${Math.round(buffer.length / 1024)} KB, ${totalW}x${totalH}px)`);
