const fs = require('fs');
const path = require('path');

const MANIFEST_PATH = path.resolve(__dirname, '../resources/ornaments/manifest.json');

let manifest = [];
if (fs.existsSync(MANIFEST_PATH)) {
    manifest = JSON.parse(fs.readFileSync(MANIFEST_PATH, 'utf-8'));
}

const itemsMap = new Map();
for (const item of manifest) {
    itemsMap.set(item.id, item);
}

const part2Entries = [
    // --- BIRTHDAY (12) ---
    {
        id: 'balloon-cluster',
        set: 'birthday',
        name_en: 'Festive Balloon Cluster',
        name_am: 'የበዓል ፊኛዎች ስብስብ',
        tags: ['birthday', 'balloons', 'celebration', 'party', 'festive'],
        anchor: 'corner',
        viewBox: '0 0 240 320',
        defaultAspect: 0.75,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'balloon-arch',
        set: 'birthday',
        name_en: 'Balloon Garland Arch',
        name_am: 'የፊኛ ቅስት ጉንጉን',
        tags: ['birthday', 'balloons', 'arch', 'garland', 'header'],
        anchor: 'top',
        viewBox: '0 0 380 220',
        defaultAspect: 1.727,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'confetti-scatter-a',
        set: 'birthday',
        name_en: 'Confetti Burst Scatter A',
        name_am: 'የኮንፌቲ ብተና ሀ',
        tags: ['birthday', 'confetti', 'scatter', 'party', 'burst'],
        anchor: 'center',
        viewBox: '0 0 300 240',
        defaultAspect: 1.25,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'confetti-scatter-b',
        set: 'birthday',
        name_en: 'Confetti Burst Scatter B',
        name_am: 'የኮንፌቲ ብተና ለ',
        tags: ['birthday', 'confetti', 'scatter', 'party', 'stars'],
        anchor: 'center',
        viewBox: '0 0 300 240',
        defaultAspect: 1.25,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'streamer-top',
        set: 'birthday',
        name_en: 'Curled Streamers Header',
        name_am: 'የተጠማዘዙ ሪባኖች ራስጌ',
        tags: ['birthday', 'streamers', 'ribbons', 'header', 'top'],
        anchor: 'top',
        viewBox: '0 0 400 160',
        defaultAspect: 2.5,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'bunting-flags',
        set: 'birthday',
        name_en: 'Party Pennant Bunting',
        name_am: 'የፓርቲ ሰንደቅ ባንዲራዎች',
        tags: ['birthday', 'bunting', 'flags', 'pennant', 'garland'],
        anchor: 'top',
        viewBox: '0 0 400 120',
        defaultAspect: 3.333,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'party-hat',
        set: 'birthday',
        name_en: 'Celebration Party Hat',
        name_am: 'የልደት በዓል ቆብ',
        tags: ['birthday', 'party-hat', 'hat', 'pom-pom', 'celebration'],
        anchor: 'center',
        viewBox: '0 0 200 280',
        defaultAspect: 0.714,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'gift-box',
        set: 'birthday',
        name_en: 'Gift Present with Satin Bow',
        name_am: 'የስጦታ ሳጥን ከሪባን ጋር',
        tags: ['birthday', 'gift', 'present', 'box', 'bow'],
        anchor: 'center',
        viewBox: '0 0 240 260',
        defaultAspect: 0.923,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'cake-candles',
        set: 'birthday',
        name_en: 'Birthday Cake with Candles',
        name_am: 'የልደት ኬክ ከሻማዎች ጋር',
        tags: ['birthday', 'cake', 'candles', 'celebration', 'dessert'],
        anchor: 'center',
        viewBox: '0 0 280 320',
        defaultAspect: 0.875,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'starburst',
        set: 'birthday',
        name_en: 'Festive Radiant Starburst',
        name_am: 'አንጸባራቂ የኮከብ ፍንጣቂ',
        tags: ['birthday', 'starburst', 'sparks', 'fireworks', 'radiant'],
        anchor: 'center',
        viewBox: '0 0 240 240',
        defaultAspect: 1.0,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'sparkle-trio',
        set: 'birthday',
        name_en: 'Sparkle Cluster Trio',
        name_am: 'የሦስት ብልጭታዎች ስብስብ',
        tags: ['birthday', 'sparkle', 'stars', 'magic', 'twinkle'],
        anchor: 'center',
        viewBox: '0 0 220 200',
        defaultAspect: 1.1,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'number-frame-circle',
        set: 'birthday',
        name_en: 'Circular Birthday Number Medallion',
        name_am: 'ክብ የቁጥር መለያ ማዕቀፍ',
        tags: ['birthday', 'frame', 'number', 'circle', 'medallion', 'badge'],
        anchor: 'frame',
        viewBox: '0 0 260 260',
        defaultAspect: 1.0,
        tintSlots: ['c1', 'c2', 'c3']
    },

    // --- BABY SHOWER (10) ---
    {
        id: 'cloud-trio',
        set: 'baby-shower',
        name_en: 'Dreamy Cloud Trio with Rain Stars',
        name_am: 'የደመናዎች እና ኮከቦች ስብስብ',
        tags: ['baby-shower', 'clouds', 'stars', 'nursery', 'dreamy'],
        anchor: 'top',
        viewBox: '0 0 320 180',
        defaultAspect: 1.778,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'moon-star-sleepy',
        set: 'baby-shower',
        name_en: 'Sleepy Crescent Moon & Sleeping Cap',
        name_am: 'የተኛ ጨረቃ እና ኮከብ',
        tags: ['baby-shower', 'moon', 'crescent', 'sleepy', 'night', 'nursery'],
        anchor: 'center',
        viewBox: '0 0 240 260',
        defaultAspect: 0.923,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'bunting-pastel',
        set: 'baby-shower',
        name_en: 'Pastel Nursery Flag Bunting',
        name_am: 'የሕፃናት ማቆያ ሰንደቅ ባንዲራዎች',
        tags: ['baby-shower', 'bunting', 'flags', 'pastel', 'garland', 'nursery'],
        anchor: 'top',
        viewBox: '0 0 400 120',
        defaultAspect: 3.333,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'balloon-pair-soft',
        set: 'baby-shower',
        name_en: 'Soft Pastel Balloon Pair with Bows',
        name_am: 'ለስላሳ ጥንድ ፊኛዎች ከሪባን ጋር',
        tags: ['baby-shower', 'balloons', 'soft', 'ribbon', 'pastel'],
        anchor: 'corner',
        viewBox: '0 0 240 320',
        defaultAspect: 0.75,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'rainbow-arc',
        set: 'baby-shower',
        name_en: 'Whimsical Nursery Rainbow Arc',
        name_am: 'የቀስተ ደመና ቅስት ከደመና ጋር',
        tags: ['baby-shower', 'rainbow', 'arc', 'clouds', 'nursery', 'whimsical'],
        anchor: 'center',
        viewBox: '0 0 320 220',
        defaultAspect: 1.455,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'bundle-stork-line',
        set: 'baby-shower',
        name_en: 'Newborn Swaddle Bundle on Branch',
        name_am: 'የሕፃን ማንጠልጠያ ጥቅል ቅርንጫፍ ላይ',
        tags: ['baby-shower', 'stork', 'bundle', 'swaddle', 'newborn', 'branch'],
        anchor: 'center',
        viewBox: '0 0 280 300',
        defaultAspect: 0.933,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'teddy-line',
        set: 'baby-shower',
        name_en: 'Sweet Plush Nursery Teddy Bear',
        name_am: 'ተወዳጅ የቴዲ ድብ አሻንጉሊት',
        tags: ['baby-shower', 'teddy', 'bear', 'plush', 'toy', 'nursery'],
        anchor: 'center',
        viewBox: '0 0 240 280',
        defaultAspect: 0.857,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'pram-line',
        set: 'baby-shower',
        name_en: 'Vintage Baby Pram Carriage',
        name_am: 'የሕፃናት ጋሪ',
        tags: ['baby-shower', 'pram', 'carriage', 'stroller', 'vintage'],
        anchor: 'center',
        viewBox: '0 0 300 280',
        defaultAspect: 1.071,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'footprints',
        set: 'baby-shower',
        name_en: 'Tiny Baby Footprints Pair',
        name_am: 'ጥንድ የሕፃን የእግር አሻራዎች',
        tags: ['baby-shower', 'footprints', 'feet', 'baby', 'sweet'],
        anchor: 'center',
        viewBox: '0 0 240 220',
        defaultAspect: 1.091,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'leaf-garland',
        set: 'baby-shower',
        name_en: 'Sweet Botanical Leaf Garland',
        name_am: 'የእጽዋት ቅጠል ጉንጉን',
        tags: ['baby-shower', 'garland', 'botanical', 'leaves', 'eucalyptus', 'divider'],
        anchor: 'divider',
        viewBox: '0 0 400 120',
        defaultAspect: 3.333,
        tintSlots: ['c1', 'c2', 'c3']
    },

    // --- GRADUATION (8) ---
    {
        id: 'mortarboard',
        set: 'graduation',
        name_en: 'Academic Graduation Mortarboard',
        name_am: 'የምረቃ ቆብ',
        tags: ['graduation', 'mortarboard', 'cap', 'tassel', 'degree', 'academic'],
        anchor: 'center',
        viewBox: '0 0 320 260',
        defaultAspect: 1.231,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'scroll-ribbon',
        set: 'graduation',
        name_en: 'Diploma Scroll Tied with Satin Ribbon',
        name_am: 'የተጠቀለለ ዲፕሎማ ከሪባን ጋር',
        tags: ['graduation', 'diploma', 'scroll', 'ribbon', 'seal', 'certificate'],
        anchor: 'center',
        viewBox: '0 0 300 240',
        defaultAspect: 1.25,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'laurel-open',
        set: 'graduation',
        name_en: 'Academic Laurel Wreath of Excellence',
        name_am: 'የክብር የሎረል የአበባ ጉንጉን',
        tags: ['graduation', 'laurel', 'wreath', 'honor', 'academic', 'excellence'],
        anchor: 'frame',
        viewBox: '0 0 320 280',
        defaultAspect: 1.143,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'diploma-roll',
        set: 'graduation',
        name_en: 'Angled Diploma Document Roll',
        name_am: 'የምረቃ ሰርተፊኬት ጥቅልል',
        tags: ['graduation', 'diploma', 'roll', 'document', 'award'],
        anchor: 'center',
        viewBox: '0 0 280 240',
        defaultAspect: 1.167,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'book-stack',
        set: 'graduation',
        name_en: 'Scholarly Hardback Book Stack',
        name_am: 'የመጻሕፍት ክምር',
        tags: ['graduation', 'books', 'stack', 'library', 'education', 'wisdom'],
        anchor: 'center',
        viewBox: '0 0 280 260',
        defaultAspect: 1.077,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'crest-rays',
        set: 'graduation',
        name_en: 'Achievement Crest Shield with Sunburst Rays',
        name_am: 'የስኬት ዓርማ ከብርሃን ጨረሮች ጋር',
        tags: ['graduation', 'crest', 'rays', 'shield', 'sunburst', 'heraldic'],
        anchor: 'center',
        viewBox: '0 0 320 320',
        defaultAspect: 1.0,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'tassel',
        set: 'graduation',
        name_en: 'Classic Silk Cap Tassel',
        name_am: 'የምረቃ ቆብ ዘለላ',
        tags: ['graduation', 'tassel', 'silk', 'fringe', 'cord'],
        anchor: 'center',
        viewBox: '0 0 200 320',
        defaultAspect: 0.625,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'ribbon-seal',
        set: 'graduation',
        name_en: 'Honor Rosette Seal with Streamers',
        name_am: 'የክብር ማህተም ሪባን',
        tags: ['graduation', 'seal', 'rosette', 'ribbon', 'medal', 'award'],
        anchor: 'center',
        viewBox: '0 0 260 300',
        defaultAspect: 0.867,
        tintSlots: ['c1', 'c2', 'c3']
    },

    // --- CORPORATE (8) ---
    {
        id: 'geo-triangles-corner',
        set: 'corporate',
        name_en: 'Architectural Geometric Triangle Cluster',
        name_am: 'የጂኦሜትሪ ሶስት ማዕዘኖች ማዕዘን',
        tags: ['corporate', 'geometric', 'triangles', 'corner', 'modern', 'architectural'],
        anchor: 'corner',
        viewBox: '0 0 280 280',
        defaultAspect: 1.0,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'line-grid-field',
        set: 'corporate',
        name_en: 'Perspective Line Grid Field',
        name_am: 'የመስመሮች ፍርግርግ መስክ',
        tags: ['corporate', 'grid', 'perspective', 'tech', 'data', 'wireframe'],
        anchor: 'center',
        viewBox: '0 0 320 220',
        defaultAspect: 1.455,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'diagonal-bands',
        set: 'corporate',
        name_en: 'Dynamic Corporate Diagonal Accent Bands',
        name_am: 'ሰያፍ የድርጅት ሰንደቆች',
        tags: ['corporate', 'diagonal', 'bands', 'stripes', 'modern', 'pinstripe'],
        anchor: 'corner',
        viewBox: '0 0 300 240',
        defaultAspect: 1.25,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'circle-rings',
        set: 'corporate',
        name_en: 'Precision Technical Telemetry Rings',
        name_am: 'የቴክኒክ ክብ ቀለበቶች',
        tags: ['corporate', 'rings', 'circle', 'tech', 'radar', 'telemetry'],
        anchor: 'center',
        viewBox: '0 0 280 280',
        defaultAspect: 1.0,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'ticket-notch-frame',
        set: 'corporate',
        name_en: 'Corporate Event Pass Ticket Frame',
        name_am: 'የክስተት መግቢያ ባጅ ማዕቀፍ',
        tags: ['corporate', 'frame', 'ticket', 'notch', 'badge', 'pass'],
        anchor: 'frame',
        viewBox: '0 0 320 420',
        defaultAspect: 0.762,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'dot-matrix',
        set: 'corporate',
        name_en: 'Digital Halftone Dot Matrix Field',
        name_am: 'የነጥቦች ማትሪክስ ገጽታ',
        tags: ['corporate', 'dots', 'matrix', 'halftone', 'tech', 'digital'],
        anchor: 'center',
        viewBox: '0 0 280 200',
        defaultAspect: 1.4,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'wave-lines',
        set: 'corporate',
        name_en: 'Algorithmic Financial Wave Curves',
        name_am: 'የማዕበል መስመሮች ገበታ',
        tags: ['corporate', 'wave', 'lines', 'financial', 'data', 'analytics'],
        anchor: 'divider',
        viewBox: '0 0 360 160',
        defaultAspect: 2.25,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'badge-seal',
        set: 'corporate',
        name_en: 'Professional Certification Badge Seal',
        name_am: 'የሙያዊ ምስክር ወረቀት ማህተም',
        tags: ['corporate', 'badge', 'seal', 'certification', 'guarantee', 'official'],
        anchor: 'center',
        viewBox: '0 0 260 260',
        defaultAspect: 1.0,
        tintSlots: ['c1', 'c2', 'c3']
    },

    // --- HOUSEWARMING (8) ---
    {
        id: 'house-line-roof',
        set: 'housewarming',
        name_en: 'Cozy Gable House & Porch Roof',
        name_am: 'ሞቅ ያለ የቤት ጣሪያ እና ደጅ',
        tags: ['housewarming', 'house', 'roof', 'home', 'gable', 'door'],
        anchor: 'center',
        viewBox: '0 0 300 260',
        defaultAspect: 1.154,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'key-ring',
        set: 'housewarming',
        name_en: 'Vintage Brass House Key & Fob',
        name_am: 'የቤት ቁልፍ እና ቀለበት',
        tags: ['housewarming', 'key', 'ring', 'fob', 'brass', 'new-home'],
        anchor: 'center',
        viewBox: '0 0 240 280',
        defaultAspect: 0.857,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'door-wreath',
        set: 'housewarming',
        name_en: 'Welcoming Front Door Botanical Wreath',
        name_am: 'የደጅ በር የአበባ ጉንጉን',
        tags: ['housewarming', 'wreath', 'door', 'botanical', 'welcome', 'leaves'],
        anchor: 'frame',
        viewBox: '0 0 280 300',
        defaultAspect: 0.933,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'plant-pot-trio',
        set: 'housewarming',
        name_en: 'Indoor Potted Houseplants Trio',
        name_am: 'የቤት ውስጥ ተክሎች በገንዳ',
        tags: ['housewarming', 'plants', 'succulent', 'monstera', 'pots', 'botanical'],
        anchor: 'center',
        viewBox: '0 0 340 220',
        defaultAspect: 1.545,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'jebena-coffee-set-warm',
        set: 'housewarming',
        name_en: 'Traditional Housewarming Coffee Gathering',
        name_am: 'ባህላዊ የቤት ምረቃ የቡና ስነ-ስርዓት',
        tags: ['housewarming', 'jebena', 'buna', 'coffee', 'ethiopian', 'hospitality'],
        anchor: 'center',
        viewBox: '0 0 320 260',
        defaultAspect: 1.231,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'window-light-frame',
        set: 'housewarming',
        name_en: 'Arched Window Frame with Planter Sill',
        name_am: 'መስኮት ከአበባ ማሰሮ ጋር',
        tags: ['housewarming', 'window', 'frame', 'arch', 'curtains', 'planter'],
        anchor: 'frame',
        viewBox: '0 0 280 340',
        defaultAspect: 0.824,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'welcome-band',
        set: 'housewarming',
        name_en: 'Welcome Home Ribbon Banner',
        name_am: 'እንኳን ደህና መጡ ሪባን',
        tags: ['housewarming', 'welcome', 'banner', 'ribbon', 'crest', 'header'],
        anchor: 'divider',
        viewBox: '0 0 380 120',
        defaultAspect: 3.167,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'ivy-vine',
        set: 'housewarming',
        name_en: 'Climbing English Ivy Botanical Vine',
        name_am: 'ተንጠላጣይ የአይቪ ቅጠል ሐረግ',
        tags: ['housewarming', 'ivy', 'vine', 'leaves', 'botanical', 'corner'],
        anchor: 'corner',
        viewBox: '0 0 200 360',
        defaultAspect: 0.556,
        tintSlots: ['c1', 'c2', 'c3']
    },

    // --- SHARED (16) ---
    // 8 Dividers
    {
        id: 'dotted-diamond',
        set: 'shared',
        name_en: 'Dotted Diamond Rule Divider',
        name_am: 'የነጥብ እና አልማዝ መስመር መለያያ',
        tags: ['shared', 'divider', 'diamond', 'dots', 'minimal', 'rule'],
        anchor: 'divider',
        viewBox: '0 0 400 40',
        defaultAspect: 10.0,
        tintSlots: ['c1', 'c2', 'c3'],
        tileable: false
    },
    {
        id: 'wave',
        set: 'shared',
        name_en: 'Rhythmic Sinusoidal Wave Divider',
        name_am: 'የማዕበል ቅርጽ መለያያ',
        tags: ['shared', 'divider', 'wave', 'rhythm', 'flowing', 'tileable'],
        anchor: 'divider',
        viewBox: '0 0 400 40',
        defaultAspect: 10.0,
        tintSlots: ['c1', 'c2', 'c3'],
        tileable: true
    },
    {
        id: 'leaf-chain',
        set: 'shared',
        name_en: 'Laurel Botanical Leaf Chain Divider',
        name_am: 'የቅጠሎች ሰንሰለት መለያያ',
        tags: ['shared', 'divider', 'leaves', 'laurel', 'botanical', 'chain', 'tileable'],
        anchor: 'divider',
        viewBox: '0 0 400 40',
        defaultAspect: 10.0,
        tintSlots: ['c1', 'c2', 'c3'],
        tileable: true
    },
    {
        id: 'double-rule-ornament',
        set: 'shared',
        name_en: 'Formal Double Rule Flourish Divider',
        name_am: 'ድርብ መስመር ከመካከለኛ ጌጥ ጋር',
        tags: ['shared', 'divider', 'double-rule', 'flourish', 'formal', 'classical'],
        anchor: 'divider',
        viewBox: '0 0 420 50',
        defaultAspect: 8.4,
        tintSlots: ['c1', 'c2', 'c3'],
        tileable: false
    },
    {
        id: 'star-rule',
        set: 'shared',
        name_en: 'Celestial Starlight Rule Divider',
        name_am: 'የኮከብ መስመር መለያያ',
        tags: ['shared', 'divider', 'star', 'celestial', 'rule', 'starlight'],
        anchor: 'divider',
        viewBox: '0 0 400 40',
        defaultAspect: 10.0,
        tintSlots: ['c1', 'c2', 'c3'],
        tileable: false
    },
    {
        id: 'simple-flourish',
        set: 'shared',
        name_en: 'Calligraphic Pen Flourish Divider',
        name_am: 'ቀላል የካሊግራፊ ጌጥ መለያያ',
        tags: ['shared', 'divider', 'flourish', 'calligraphy', 'scroll', 'pen'],
        anchor: 'divider',
        viewBox: '0 0 380 44',
        defaultAspect: 8.636,
        tintSlots: ['c1', 'c2', 'c3'],
        tileable: false
    },
    {
        id: 'dots-three',
        set: 'shared',
        name_en: 'Minimal Trio Pearl Dots Divider',
        name_am: 'የሦስት ነጥቦች መለያያ',
        tags: ['shared', 'divider', 'dots', 'three-dots', 'pearls', 'minimal'],
        anchor: 'divider',
        viewBox: '0 0 360 30',
        defaultAspect: 12.0,
        tintSlots: ['c1', 'c2', 'c3'],
        tileable: false
    },
    {
        id: 'thin-line-gap',
        set: 'shared',
        name_en: 'Hairline Divider with Geometric Center Gap',
        name_am: 'ቀጭን መስመር ከመካከለኛ ክፍተት ጋር',
        tags: ['shared', 'divider', 'hairline', 'gap', 'minimal', 'modern'],
        anchor: 'divider',
        viewBox: '0 0 380 30',
        defaultAspect: 12.667,
        tintSlots: ['c1', 'c2', 'c3'],
        tileable: false
    },

    // 6 Frames
    {
        id: 'thin-double-frame',
        set: 'shared',
        name_en: 'Clean Double Rule Card Frame',
        name_am: 'ቀጭን ድርብ ማዕቀፍ',
        tags: ['shared', 'frame', 'double-rule', 'border', 'clean', 'modern'],
        anchor: 'frame',
        viewBox: '0 0 320 440',
        defaultAspect: 0.727,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'notch-frame',
        set: 'shared',
        name_en: 'Notched Corner Invitation Frame',
        name_am: 'ማዕዘኑ የተቆረጠ ማዕቀፍ',
        tags: ['shared', 'frame', 'notch', 'corners', 'elegant', 'card'],
        anchor: 'frame',
        viewBox: '0 0 320 440',
        defaultAspect: 0.727,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'scalloped-frame',
        set: 'shared',
        name_en: 'Lace Scalloped Edge Card Frame',
        name_am: 'የዳንቴል ቅርጽ ማዕቀፍ',
        tags: ['shared', 'frame', 'scalloped', 'lace', 'vintage', 'romantic'],
        anchor: 'frame',
        viewBox: '0 0 320 440',
        defaultAspect: 0.727,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'arch-frame',
        set: 'shared',
        name_en: 'Romanesque Arched Portal Frame',
        name_am: 'የቅስት በር ማዕቀፍ',
        tags: ['shared', 'frame', 'arch', 'portal', 'keystone', 'classical'],
        anchor: 'frame',
        viewBox: '0 0 320 440',
        defaultAspect: 0.727,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'circle-frame',
        set: 'shared',
        name_en: 'Circular Medallion Laurel Frame',
        name_am: 'ክብ የሜዳሊያ ማዕቀፍ',
        tags: ['shared', 'frame', 'circle', 'medallion', 'round', 'wreath'],
        anchor: 'frame',
        viewBox: '0 0 340 340',
        defaultAspect: 1.0,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'ticket-frame',
        set: 'shared',
        name_en: 'Vintage Admission Ticket Frame',
        name_am: 'የቲኬት መግቢያ ማዕቀፍ',
        tags: ['shared', 'frame', 'ticket', 'stub', 'vintage', 'admission'],
        anchor: 'frame',
        viewBox: '0 0 320 440',
        defaultAspect: 0.727,
        tintSlots: ['c1', 'c2', 'c3']
    },

    // 2 Special
    {
        id: 'paper-tear-edge',
        set: 'shared',
        name_en: 'Deckled Torn Paper Edge Overlay',
        name_am: 'የተቀደደ ወረቀት ጫፍ ገጽታ',
        tags: ['shared', 'special', 'paper-tear', 'deckle', 'texture', 'edge'],
        anchor: 'divider',
        viewBox: '0 0 400 60',
        defaultAspect: 6.667,
        tintSlots: ['c1', 'c2', 'c3']
    },
    {
        id: 'corner-ribbon',
        set: 'shared',
        name_en: '3D Satin Corner Ribbon Wrap',
        name_am: 'ባለ 3ዲ የማዕዘን ሪባን',
        tags: ['shared', 'special', 'corner-ribbon', 'ribbon', 'satin', 'corner', 'wrap'],
        anchor: 'corner',
        viewBox: '0 0 200 200',
        defaultAspect: 1.0,
        tintSlots: ['c1', 'c2', 'c3']
    }
];

// Add entries
for (const entry of part2Entries) {
    itemsMap.set(entry.id, entry);
}

const finalManifest = Array.from(itemsMap.values());
fs.writeFileSync(MANIFEST_PATH, JSON.stringify(finalManifest, null, 2) + '\n', 'utf-8');
console.log(`Manifest updated: total ${finalManifest.length} entries.`);
