const fs = require('fs');
const path = require('path');

const ORNAMENTS_DIR = path.resolve(__dirname, '../resources/ornaments');
const MANIFEST_PATH = path.join(ORNAMENTS_DIR, 'manifest.json');

// Read existing manifest
let existingManifest = [];
if (fs.existsSync(MANIFEST_PATH)) {
    existingManifest = JSON.parse(fs.readFileSync(MANIFEST_PATH, 'utf-8'));
}

// Map of existing items by ID
const itemsMap = new Map();
for (const item of existingManifest) {
    itemsMap.set(item.id, item);
}

// 56 new entries with full bilingual names, tags, anchors, viewBox, aspect, tintSlots
const newEntries = [
    // --- WEDDING (14) ---
    {
        id: 'floral-corner-garden',
        set: 'wedding',
        name_en: 'Garden Floral Corner',
        name_am: 'የአትክልት አበባ ማዕዘን',
        tags: ['wedding', 'floral', 'corner', 'rose', 'garden'],
        anchor: 'corner',
        viewBox: '0 0 240 240',
        defaultAspect: 1.0,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'floral-corner-peony',
        set: 'wedding',
        name_en: 'Peony Blossom Corner',
        name_am: 'የፒዮኒ አበባ ማዕዘን',
        tags: ['wedding', 'floral', 'peony', 'corner'],
        anchor: 'corner',
        viewBox: '0 0 240 240',
        defaultAspect: 1.0,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'floral-arch-wreath',
        set: 'wedding',
        name_en: 'Floral Header Arch',
        name_am: 'የአበባ ቅስት',
        tags: ['wedding', 'arch', 'wreath', 'floral', 'header'],
        anchor: 'top',
        viewBox: '0 0 400 200',
        defaultAspect: 2.0,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'wreath-round-eucalyptus',
        set: 'wedding',
        name_en: 'Eucalyptus Round Wreath',
        name_am: 'ክብ የባህር ዛፍ የአበባ ጉንጉን',
        tags: ['wedding', 'wreath', 'eucalyptus', 'circle', 'frame'],
        anchor: 'frame',
        viewBox: '0 0 240 240',
        defaultAspect: 1.0,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'sprig-olive',
        set: 'wedding',
        name_en: 'Olive Leaf Sprig',
        name_am: 'የወይራ ቅጠል ቅርንጫፍ',
        tags: ['wedding', 'botanical', 'olive', 'sprig'],
        anchor: 'center',
        viewBox: '0 0 160 200',
        defaultAspect: 0.8,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'sprig-wildflower',
        set: 'wedding',
        name_en: 'Wildflower Botanical Sprig',
        name_am: 'የዱር አበባ ቅርንጫፍ',
        tags: ['wedding', 'botanical', 'wildflower', 'sprig'],
        anchor: 'center',
        viewBox: '0 0 140 200',
        defaultAspect: 0.7,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'bouquet-bottom-spray',
        set: 'wedding',
        name_en: 'Bottom Floral Bouquet Spray',
        name_am: 'የስር አበባ ጉንጉን',
        tags: ['wedding', 'bouquet', 'spray', 'bottom', 'floral'],
        anchor: 'bottom',
        viewBox: '0 0 360 160',
        defaultAspect: 2.25,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'rings-interlocked',
        set: 'wedding',
        name_en: 'Interlocked Wedding Rings',
        name_am: 'የጋብቻ ቀለበቶች',
        tags: ['wedding', 'rings', 'gold', 'symbol', 'love'],
        anchor: 'center',
        viewBox: '0 0 200 160',
        defaultAspect: 1.25,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'flourish-divider-ornate',
        set: 'wedding',
        name_en: 'Ornate Calligraphic Divider',
        name_am: 'የተዋበ የካሊግራፊ መለያያ',
        tags: ['wedding', 'flourish', 'divider', 'ornate', 'horizontal'],
        anchor: 'divider',
        viewBox: '0 0 500 50',
        defaultAspect: 10.0,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'monogram-laurel-frame',
        set: 'wedding',
        name_en: 'Monogram Laurel Wreath Frame',
        name_am: 'የሞኖግራም የሎረል ፍሬም',
        tags: ['wedding', 'monogram', 'frame', 'laurel', 'crest'],
        anchor: 'frame',
        viewBox: '0 0 260 260',
        defaultAspect: 1.0,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'dove-pair',
        set: 'wedding',
        name_en: 'Pair of Wedding Doves',
        name_am: 'የፍቅር እርግቦች ጥንድ',
        tags: ['wedding', 'dove', 'peace', 'love', 'pair'],
        anchor: 'center',
        viewBox: '0 0 260 180',
        defaultAspect: 1.444,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'botanical-side-vine',
        set: 'wedding',
        name_en: 'Botanical Side Vine Border',
        name_am: 'የጎን ቅጠላቅጠል ሐረግ',
        tags: ['wedding', 'botanical', 'vine', 'side', 'border'],
        anchor: 'side',
        viewBox: '0 0 80 400',
        defaultAspect: 0.2,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'gold-leaf-scatter',
        set: 'wedding',
        name_en: 'Golden Leaf & Star Scatter',
        name_am: 'የወርቅ ቅጠልና ኮከብ ብናኝ',
        tags: ['wedding', 'gold', 'leaves', 'scatter', 'stars'],
        anchor: 'pattern',
        viewBox: '0 0 200 200',
        defaultAspect: 1.0,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'lace-edge-band',
        set: 'wedding',
        name_en: 'Scalloped Lace Edge Band',
        name_am: 'የዳንቴል ጠርዝ ሪባን',
        tags: ['wedding', 'lace', 'band', 'border', 'tileable'],
        anchor: 'divider',
        viewBox: '0 0 120 40',
        defaultAspect: 3.0,
        tintSlots: ['c1', 'c2', 'c3'],
        tileable: true
    },

    // --- ANNIVERSARY (8) ---
    {
        id: 'laurel-wreath-pair',
        set: 'anniversary',
        name_en: 'Pair of Laurel Branches',
        name_am: 'የክብር የሎረል ቅርንጫፎች ጥንድ',
        tags: ['anniversary', 'laurel', 'wreath', 'celebration', 'frame'],
        anchor: 'frame',
        viewBox: '0 0 300 240',
        defaultAspect: 1.25,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'heart-line-art',
        set: 'anniversary',
        name_en: 'Interwoven Heart Line Art',
        name_am: 'የተሳሰረ የልብ መስመር ቅርጽ',
        tags: ['anniversary', 'heart', 'line-art', 'love', 'romance'],
        anchor: 'center',
        viewBox: '0 0 200 180',
        defaultAspect: 1.111,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'numeral-medallion-frame',
        set: 'anniversary',
        name_en: 'Milestone Numeral Medallion',
        name_am: 'የቁጥር መታሰቢያ ሜዳሊያ ፍሬም',
        tags: ['anniversary', 'medallion', 'numeral', 'frame', 'vintage'],
        anchor: 'frame',
        viewBox: '0 0 240 240',
        defaultAspect: 1.0,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'champagne-clink',
        set: 'anniversary',
        name_en: 'Champagne Flutes Toast',
        name_am: 'የሻምፓኝ ብርጭቆዎች ቶስት',
        tags: ['anniversary', 'champagne', 'celebration', 'toast', 'party'],
        anchor: 'center',
        viewBox: '0 0 220 220',
        defaultAspect: 1.0,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'ribbon-banner',
        set: 'anniversary',
        name_en: 'Vintage Ribbon Banner',
        name_am: 'የጥንታዊ ሪባን ባነር',
        tags: ['anniversary', 'ribbon', 'banner', 'vintage', 'text-holder'],
        anchor: 'center',
        viewBox: '0 0 360 100',
        defaultAspect: 3.6,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'rose-single',
        set: 'anniversary',
        name_en: 'Long-Stemmed Blooming Rose',
        name_am: 'ረጅም ግንድ ያለው ጽጌረዳ አበባ',
        tags: ['anniversary', 'rose', 'floral', 'single', 'romance'],
        anchor: 'center',
        viewBox: '0 0 160 300',
        defaultAspect: 0.533,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'sparkle-cluster',
        set: 'anniversary',
        name_en: 'Diamond Sparkle Cluster',
        name_am: 'የአልማዝ ብልጭታ ስብስብ',
        tags: ['anniversary', 'sparkle', 'stars', 'celebration', 'glamour'],
        anchor: 'pattern',
        viewBox: '0 0 200 200',
        defaultAspect: 1.0,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'art-deco-fan',
        set: 'anniversary',
        name_en: 'Art Deco Palmette Fan',
        name_am: 'አርት ዲኮ የፓልሜት ማራገቢያ',
        tags: ['anniversary', 'art-deco', 'fan', 'vintage', '1920s'],
        anchor: 'center',
        viewBox: '0 0 240 160',
        defaultAspect: 1.5,
        tintSlots: ['c1', 'c2', 'c3']
    },

    // --- FUNERAL (10) ---
    {
        id: 'olive-branch-pair',
        set: 'funeral',
        name_en: 'Solemn Crossed Olive Branches',
        name_am: 'የወይራ ቅርንጫፎች ጥንድ',
        tags: ['funeral', 'olive', 'branch', 'peace', 'memorial'],
        anchor: 'center',
        viewBox: '0 0 260 160',
        defaultAspect: 1.625,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'lily-single',
        set: 'funeral',
        name_en: 'Single White Peace Lily',
        name_am: 'ነጭ የሰላም ሊሊ አበባ',
        tags: ['funeral', 'lily', 'flower', 'memorial', 'peace'],
        anchor: 'center',
        viewBox: '0 0 160 260',
        defaultAspect: 0.615,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'lily-spray',
        set: 'funeral',
        name_en: 'Memorial Lily Floral Spray',
        name_am: 'የመታሰቢያ ሊሊ አበባ ጉንጉን',
        tags: ['funeral', 'lily', 'spray', 'floral', 'memorial'],
        anchor: 'center',
        viewBox: '0 0 240 240',
        defaultAspect: 1.0,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'dove-ascending',
        set: 'funeral',
        name_en: 'Ascending Memorial Dove',
        name_am: 'ወደ ሰማይ የምትበር የሰላም እርግብ',
        tags: ['funeral', 'dove', 'ascending', 'peace', 'memorial', 'soul'],
        anchor: 'center',
        viewBox: '0 0 220 220',
        defaultAspect: 1.0,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'candle-flame',
        set: 'funeral',
        name_en: 'Memorial Candle Flame',
        name_am: 'የመታሰቢያ ሻማ ነበልባል',
        tags: ['funeral', 'candle', 'flame', 'memorial', 'remembrance'],
        anchor: 'center',
        viewBox: '0 0 140 240',
        defaultAspect: 0.583,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'soft-rays',
        set: 'funeral',
        name_en: 'Heavenly Soft Light Rays',
        name_am: 'ሰማያዊ የብርሃን ጨረሮች',
        tags: ['funeral', 'rays', 'light', 'heavenly', 'peace'],
        anchor: 'top',
        viewBox: '0 0 300 150',
        defaultAspect: 2.0,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'double-rule-frame-corner',
        set: 'funeral',
        name_en: 'Dignified Double-Rule Corner',
        name_am: 'ድርብ መስመር ክብረ-ነክ ማዕዘን',
        tags: ['funeral', 'frame', 'corner', 'double-rule', 'dignified'],
        anchor: 'corner',
        viewBox: '0 0 160 160',
        defaultAspect: 1.0,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'willow-branch',
        set: 'funeral',
        name_en: 'Weeping Willow Branch',
        name_am: 'የዊሎው ዛፍ ቅርንጫፍ',
        tags: ['funeral', 'willow', 'branch', 'peace', 'memorial'],
        anchor: 'top',
        viewBox: '0 0 200 240',
        defaultAspect: 0.833,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'wing-pair',
        set: 'funeral',
        name_en: 'Seraphic Angel Wings',
        name_am: 'የመልአክ ክንፎች ጥንድ',
        tags: ['funeral', 'wings', 'angel', 'protection', 'peace'],
        anchor: 'center',
        viewBox: '0 0 320 180',
        defaultAspect: 1.778,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'quiet-corner-flourish',
        set: 'funeral',
        name_en: 'Quiet Reverent Corner Flourish',
        name_am: 'የተረጋጋ የክብር ማዕዘን',
        tags: ['funeral', 'flourish', 'corner', 'subtle', 'respectful'],
        anchor: 'corner',
        viewBox: '0 0 180 180',
        defaultAspect: 1.0,
        tintSlots: ['c1', 'c2', 'c3']
    },

    // --- FAITH-SYMBOLS (7) ---
    {
        id: 'ethiopian-orthodox-cross-lalibela',
        set: 'faith-symbols',
        name_en: 'Lalibela Ethiopian Orthodox Cross',
        name_am: 'የላሊበላ የኢትዮጵያ ኦርቶዶክስ መስቀል',
        tags: ['faith-symbols', 'ethiopian', 'cross', 'lalibela', 'orthodox', 'religious'],
        anchor: 'center',
        viewBox: '0 0 220 300',
        defaultAspect: 0.733,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'ethiopian-orthodox-cross-axum',
        set: 'faith-symbols',
        name_en: 'Axumite Ethiopian Orthodox Cross',
        name_am: 'የአክሱም የኢትዮጵያ ኦርቶዶክስ መስቀል',
        tags: ['faith-symbols', 'ethiopian', 'cross', 'axum', 'orthodox', 'religious'],
        anchor: 'center',
        viewBox: '0 0 220 300',
        defaultAspect: 0.733,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'ethiopian-orthodox-cross-gondar',
        set: 'faith-symbols',
        name_en: 'Gondarine Ethiopian Orthodox Cross',
        name_am: 'የጎንደር የኢትዮጵያ ኦርቶዶክስ መስቀል',
        tags: ['faith-symbols', 'ethiopian', 'cross', 'gondar', 'orthodox', 'religious'],
        anchor: 'center',
        viewBox: '0 0 220 300',
        defaultAspect: 0.733,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'latin-cross-simple',
        set: 'faith-symbols',
        name_en: 'Classical Faceted Latin Cross',
        name_am: 'የላቲን ክርስቲያን መስቀል',
        tags: ['faith-symbols', 'christian', 'cross', 'latin', 'religious'],
        anchor: 'center',
        viewBox: '0 0 180 260',
        defaultAspect: 0.692,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'crescent-star-simple',
        set: 'faith-symbols',
        name_en: 'Islamic Crescent & 8-Point Star',
        name_am: 'የእስልምና ጨረቃና ባለ 8 ጫፍ ኮከብ',
        tags: ['faith-symbols', 'islamic', 'crescent', 'star', 'symbol', 'religious'],
        anchor: 'center',
        viewBox: '0 0 200 200',
        defaultAspect: 1.0,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'praying-hands-line',
        set: 'faith-symbols',
        name_en: 'Praying Hands Reverent Line Art',
        name_am: 'የጸሎት እጆች ቅርጽ',
        tags: ['faith-symbols', 'prayer', 'hands', 'faith', 'reverence'],
        anchor: 'center',
        viewBox: '0 0 180 240',
        defaultAspect: 0.75,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'dove-olive',
        set: 'faith-symbols',
        name_en: 'Holy Spirit Dove with Olive Branch',
        name_am: 'መንፈስ ቅዱስ እርግብ ከወይራ ቅጠል ጋር',
        tags: ['faith-symbols', 'dove', 'olive', 'peace', 'holy-spirit', 'faith'],
        anchor: 'center',
        viewBox: '0 0 240 200',
        defaultAspect: 1.2,
        tintSlots: ['c1', 'c2', 'c3']
    },

    // --- RELIGIOUS-ISLAMIC (8) ---
    {
        id: 'star-8-tile-pattern',
        set: 'religious-islamic',
        name_en: 'Islamic 8-Point Khatam Tile Pattern',
        name_am: 'የእስልምና ባለ 8 ጫፍ ካታም የሰሌዳ ጥለት',
        tags: ['religious-islamic', 'star-8', 'khatam', 'tileable', 'pattern', 'geometric'],
        anchor: 'pattern',
        viewBox: '0 0 120 120',
        defaultAspect: 1.0,
        tintSlots: ['c1', 'c2', 'c3'],
        tileable: true
    },
    {
        id: 'star-12-rosette',
        set: 'religious-islamic',
        name_en: '12-Point Islamic Geometric Rosette',
        name_am: 'ባለ 12 ጫፍ የእስልምና ጂኦሜትሪክ ሮዜት',
        tags: ['religious-islamic', 'rosette', 'star-12', 'girih', 'medallion', 'geometric'],
        anchor: 'center',
        viewBox: '0 0 260 260',
        defaultAspect: 1.0,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'arabesque-corner',
        set: 'religious-islamic',
        name_en: 'Classical Islimi Arabesque Corner',
        name_am: 'የእስልምና ኢስሊሚ አረቤስክ ማዕዘን',
        tags: ['religious-islamic', 'arabesque', 'islimi', 'corner', 'rumi', 'biomorphic'],
        anchor: 'corner',
        viewBox: '0 0 200 200',
        defaultAspect: 1.0,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'mihrab-arch-frame',
        set: 'religious-islamic',
        name_en: 'Mihrab Prayer Arch Border Frame',
        name_am: 'የሚህራብ የጸሎት ቅስት ፍሬም',
        tags: ['religious-islamic', 'mihrab', 'arch', 'frame', 'mosque', 'prayer'],
        anchor: 'frame',
        viewBox: '0 0 300 400',
        defaultAspect: 0.75,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'crescent-lantern',
        set: 'religious-islamic',
        name_en: 'Crescent Moon & Fanous Lantern',
        name_am: 'ጨረቃ እና የፋኑስ ፋኖስ',
        tags: ['religious-islamic', 'crescent', 'lantern', 'fanous', 'ramadan', 'eid'],
        anchor: 'center',
        viewBox: '0 0 200 280',
        defaultAspect: 0.714,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'hanging-lantern-pair',
        set: 'religious-islamic',
        name_en: 'Pair of Hanging Ramadan Lanterns',
        name_am: 'የረመዳን ተንጠልጣይ ፋኖሶች ጥንድ',
        tags: ['religious-islamic', 'lantern', 'hanging', 'ramadan', 'eid', 'celebration'],
        anchor: 'top',
        viewBox: '0 0 220 280',
        defaultAspect: 0.786,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'mosque-skyline',
        set: 'religious-islamic',
        name_en: 'Mosque Domes & Minarets Skyline',
        name_am: 'የመስጊድ ጉልላቶችና ሚናሬቶች ገጽታ',
        tags: ['religious-islamic', 'mosque', 'skyline', 'minaret', 'dome', 'architecture'],
        anchor: 'bottom',
        viewBox: '0 0 400 160',
        defaultAspect: 2.5,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'dome-minaret-silhouette',
        set: 'religious-islamic',
        name_en: 'Ottoman Ribbed Dome & Minaret',
        name_am: 'የኦቶማን ጉልላትና ረጅም ሚናሬት',
        tags: ['religious-islamic', 'dome', 'minaret', 'architecture', 'islamic'],
        anchor: 'center',
        viewBox: '0 0 240 320',
        defaultAspect: 0.75,
        tintSlots: ['c1', 'c2', 'c3']
    },

    // --- RELIGIOUS-ETHIOPIAN (9) ---
    {
        id: 'meskel-cross-ornate',
        set: 'religious-ethiopian',
        name_en: 'Ornate Ethiopian Meskel Cross',
        name_am: 'የተዋበ የመስቀል በዓል መስቀል',
        tags: ['religious-ethiopian', 'meskel', 'cross', 'ornate', 'orthodox', 'heritage'],
        anchor: 'center',
        viewBox: '0 0 240 300',
        defaultAspect: 0.8,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'adey-abeba-daisy-sprig',
        set: 'religious-ethiopian',
        name_en: 'Adey Abeba New Year Flower',
        name_am: 'የእንቁጣጣሽ አደይ አበባ ቅርንጫፍ',
        tags: ['religious-ethiopian', 'adey-abeba', 'flower', 'enkutatash', 'meskel', 'botanical'],
        anchor: 'center',
        viewBox: '0 0 200 240',
        defaultAspect: 0.833,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'tilet-band-straight',
        set: 'religious-ethiopian',
        name_en: 'Tibeb Embroidered Straight Band',
        name_am: 'ቀጥተኛ የጥበብ ጥልፍ ሪባን',
        tags: ['religious-ethiopian', 'tilet', 'tibeb', 'band', 'border', 'tileable'],
        anchor: 'divider',
        viewBox: '0 0 120 40',
        defaultAspect: 3.0,
        tintSlots: ['c1', 'c2', 'c3'],
        tileable: true
    },
    {
        id: 'tilet-band-corner',
        set: 'religious-ethiopian',
        name_en: 'Tibeb Embroidered Corner Piece',
        name_am: 'የጥበብ ጥልፍ ማዕዘን',
        tags: ['religious-ethiopian', 'tilet', 'tibeb', 'corner', 'traditional'],
        anchor: 'corner',
        viewBox: '0 0 120 120',
        defaultAspect: 1.0,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'tilet-medallion',
        set: 'religious-ethiopian',
        name_en: 'Tibeb Diamond Medallion Emblem',
        name_am: 'የጥበብ ዳይመንድ ሜዳሊያ',
        tags: ['religious-ethiopian', 'tilet', 'tibeb', 'medallion', 'diamond', 'center'],
        anchor: 'center',
        viewBox: '0 0 240 240',
        defaultAspect: 1.0,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'mesob-line',
        set: 'religious-ethiopian',
        name_en: 'Traditional Woven Mesob Basket',
        name_am: 'የባህል መሶብ',
        tags: ['religious-ethiopian', 'mesob', 'culture', 'heritage', 'basket', 'dining'],
        anchor: 'center',
        viewBox: '0 0 220 260',
        defaultAspect: 0.846,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'jebena-cup-set',
        set: 'religious-ethiopian',
        name_en: 'Jebena Coffee Pot & Cini Cups Set',
        name_am: 'የሸክላ ጀበና እና ሲኒዎች ስብስብ',
        tags: ['religious-ethiopian', 'jebena', 'buna', 'coffee', 'heritage', 'cini'],
        anchor: 'center',
        viewBox: '0 0 240 220',
        defaultAspect: 1.091,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'lalibela-cross-border',
        set: 'religious-ethiopian',
        name_en: 'Lalibela Cross Linked Frieze Border',
        name_am: 'የላሊበላ መስቀሎች የተሳሰረ ድንበር',
        tags: ['religious-ethiopian', 'lalibela', 'cross', 'border', 'tileable', 'frieze'],
        anchor: 'divider',
        viewBox: '0 0 120 40',
        defaultAspect: 3.0,
        tintSlots: ['c1', 'c2', 'c3'],
        tileable: true
    },
    {
        id: 'genna-star',
        set: 'religious-ethiopian',
        name_en: 'Genna Bethlehem Radiant Star',
        name_am: 'የገና የቤተልሔም ኮከብ',
        tags: ['religious-ethiopian', 'genna', 'star', 'christmas', 'radiant', 'epiphany'],
        anchor: 'center',
        viewBox: '0 0 220 220',
        defaultAspect: 1.0,
        tintSlots: ['c1', 'c2', 'c3']
    }
];

// Add/update all entries
for (const entry of newEntries) {
    itemsMap.set(entry.id, entry);
}

// Convert map back to array
const finalManifest = Array.from(itemsMap.values());

fs.writeFileSync(MANIFEST_PATH, JSON.stringify(finalManifest, null, 2) + '\n', 'utf-8');
console.log(`Manifest updated with ${finalManifest.length} entries.`);
