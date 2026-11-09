import fs from 'fs';
import path from 'path';
import https from 'https';

const FONTS_DIR = path.resolve(process.cwd(), 'public/fonts');
if (!fs.existsSync(FONTS_DIR)) {
    fs.mkdirSync(FONTS_DIR, { recursive: true });
}

const fontFamilies = [
    'Noto+Sans+Ethiopic:wght@400;700',
    'Noto+Serif+Ethiopic:wght@400;700',
    'Cinzel:wght@400;700',
    'Playfair+Display:ital,wght@0,400;0,700;1,400',
    'Great+Vibes',
    'Montserrat:wght@400;600;700',
    'Oswald:wght@400;600',
    'Caveat:wght@400;700'
];

async function fetchCss(url) {
    return new Promise((resolve, reject) => {
        https.get(url, { headers: { 'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36' } }, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => resolve(data));
        }).on('error', reject);
    });
}

async function downloadFile(url, dest) {
    return new Promise((resolve, reject) => {
        const file = fs.createWriteStream(dest);
        https.get(url, (res) => {
            res.pipe(file);
            file.on('finish', () => file.close(resolve));
        }).on('error', err => {
            fs.unlink(dest, () => reject(err));
        });
    });
}

async function main() {
    console.log('Fetching Google Fonts css...');
    const combinedUrl = `https://fonts.googleapis.com/css2?family=${fontFamilies.join('&family=')}&display=swap`;
    const css = await fetchCss(combinedUrl);

    // Parse font-face rules and extract URLs
    let localCss = '';
    const fontFaceRegex = /@font-face\s*\{([^}]+)\}/g;
    let match;
    let count = 0;

    while ((match = fontFaceRegex.exec(css)) !== null) {
        let block = match[1];
        const srcMatch = block.match(/url\((https:\/\/[^)]+)\)/);
        const familyMatch = block.match(/font-family:\s*['"]?([^'";]+)['"]?/);
        const weightMatch = block.match(/font-weight:\s*([^;]+);/);
        const styleMatch = block.match(/font-style:\s*([^;]+);/);

        if (srcMatch && familyMatch) {
            const fontUrl = srcMatch[1];
            const family = familyMatch[1].trim();
            const weight = weightMatch ? weightMatch[1].trim() : 'normal';
            const style = styleMatch ? styleMatch[1].trim() : 'normal';

            const ext = path.extname(new URL(fontUrl).pathname) || '.woff2';
            const cleanName = family.replace(/\s+/g, '-').toLowerCase();
            const fileName = `${cleanName}-${style}-${weight}-${count}${ext}`;
            const destPath = path.join(FONTS_DIR, fileName);

            console.log(`Downloading ${family} (${weight}) -> ${fileName}`);
            await downloadFile(fontUrl, destPath);

            block = block.replace(fontUrl, `/fonts/${fileName}`);
            localCss += `@font-face {\n${block}\n}\n\n`;
            count++;
        }
    }

    fs.writeFileSync(path.join(FONTS_DIR, 'fonts.css'), localCss, 'utf8');
    console.log(`Done! Downloaded ${count} font files. Saved fonts.css.`);
}

main().catch(err => {
    console.error('Failed to download fonts:', err);
    process.exit(1);
});
