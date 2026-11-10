const fs = require('fs');
const path = require('path');
const { Resvg } = require('@resvg/resvg-js');

const ROOT_DIR = path.resolve(__dirname, '..');

const testTiles = [
    { file: 'resources/ornaments/wedding/lace-edge-band.svg', w: 120, h: 40 },
    { file: 'resources/ornaments/religious-islamic/star-8-tile-pattern.svg', w: 120, h: 120 },
    { file: 'resources/ornaments/religious-ethiopian/tilet-band-straight.svg', w: 120, h: 40 },
    { file: 'resources/ornaments/religious-ethiopian/lalibela-cross-border.svg', w: 120, h: 40 }
];

let allPassed = true;

for (const t of testTiles) {
    const filePath = path.join(ROOT_DIR, t.file);
    if (!fs.existsSync(filePath)) {
        console.error(`File not found: ${filePath}`);
        allPassed = false;
        continue;
    }

    const raw = fs.readFileSync(filePath, 'utf-8');
    const inner = raw
        .replace(/<\/?svg[^>]*>/g, '')
        .replace(/\{\{c1\}\}/g, '#1F2937')
        .replace(/\{\{c2\}\}/g, '#D4AF37')
        .replace(/\{\{c3\}\}/g, '#800020');

    const totalW = t.w * 3;
    const tiled = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${totalW} ${t.h}" width="${totalW}" height="${t.h}">
      <g transform="translate(0, 0)">${inner}</g>
      <g transform="translate(${t.w}, 0)">${inner}</g>
      <g transform="translate(${t.w * 2}, 0)">${inner}</g>
    </svg>`;

    const resvg = new Resvg(tiled, { fitTo: { mode: 'width', value: totalW } });
    const rendered = resvg.render();
    const pixels = rendered.pixels;

    let seamDiff = 0;
    for (let y = 0; y < t.h; y++) {
        for (let c = 0; c < 4; c++) {
            const p1 = pixels[(y * totalW + t.w) * 4 + c];
            const p2 = pixels[(y * totalW + t.w * 2) * 4 + c];
            if (p1 !== p2) {
                seamDiff++;
            }
        }
    }

    if (seamDiff === 0) {
        console.log(`[PASS] ${t.file} (3-tile seamless edge disparity: 0 px)`);
    } else {
        console.error(`[FAIL] ${t.file} (seam disparity: ${seamDiff} pixel channels)`);
        allPassed = false;
    }
}

if (!allPassed) {
    process.exit(1);
} else {
    console.log('All tileable ornaments passed seamless tiling verification.');
    process.exit(0);
}
