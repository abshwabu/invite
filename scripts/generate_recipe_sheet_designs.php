<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Templates\Recipes\RecipeRegistry;
use App\Templates\Recipes\RecipeSpec;

$specs = [
    [
        'recipeId' => 'framed-center',
        'category' => 'wedding',
        'paletteId' => 'garden-rose',
        'pairingId' => 'classic-ceremonial',
        'locale' => 'en',
        'label' => 'R1 Framed Center (Wedding - Garden Rose)',
    ],
    [
        'recipeId' => 'arch-window',
        'category' => 'religious-ethiopian',
        'paletteId' => 'habesha-white-gold-green-red',
        'pairingId' => 'regal-heritage',
        'locale' => 'am',
        'title' => 'የሰርግ እና የተክሊል በዓል',
        'label' => 'R2 Arch Window (Orthodox Teklil - Habesha)',
    ],
    [
        'recipeId' => 'split-band',
        'category' => 'corporate',
        'paletteId' => 'corporate-slate',
        'pairingId' => 'bold-contemporary',
        'locale' => 'en',
        'label' => 'R3 Split Band (Corporate - Slate)',
    ],
    [
        'recipeId' => 'corner-florals',
        'category' => 'wedding',
        'paletteId' => 'dusty-blue-slate',
        'pairingId' => 'romantic-editorial',
        'locale' => 'en',
        'label' => 'R4 Corner Florals (Wedding - Dusty Blue)',
    ],
    [
        'recipeId' => 'poster-stack',
        'category' => 'graduation',
        'paletteId' => 'midnight-gold-classic',
        'pairingId' => 'architectural-modern',
        'locale' => 'en',
        'label' => 'R5 Poster Stack (Graduation - Midnight Gold)',
    ],
    [
        'recipeId' => 'medallion',
        'category' => 'anniversary',
        'paletteId' => 'burgundy-velvet',
        'pairingId' => 'classic-ceremonial',
        'locale' => 'am',
        'title' => 'የ25 ዓመት የብር ኢዮቤልዩ',
        'label' => 'R6 Medallion (Anniversary - Burgundy)',
    ],
    [
        'recipeId' => 'ticket',
        'category' => 'corporate',
        'paletteId' => 'pop-neon-party',
        'pairingId' => 'creative-tech',
        'locale' => 'en',
        'label' => 'R7 Ticket (Tech Gala - Neon)',
    ],
    [
        'recipeId' => 'wash',
        'category' => 'funeral',
        'paletteId' => 'sober-charcoal',
        'pairingId' => 'solemn-memorial',
        'locale' => 'am',
        'title' => 'የአቶ በቀለ መታሰቢያ ጸሎት',
        'label' => 'R8 Wash (Memorial - Charcoal)',
    ],
    [
        'recipeId' => 'horizon',
        'category' => 'baby-shower',
        'paletteId' => 'pastel-candy-sweet',
        'pairingId' => 'sweet-storybook',
        'locale' => 'en',
        'label' => 'R9 Horizon (Baby Shower - Pastel)',
    ],
    [
        'recipeId' => 'collage',
        'category' => 'birthday',
        'paletteId' => 'pop-fiesta-sun',
        'pairingId' => 'playful-celebration',
        'locale' => 'en',
        'label' => 'R10 Collage (Birthday - Fiesta)',
    ],
    [
        'recipeId' => 'band',
        'category' => 'religious-ethiopian',
        'paletteId' => 'meskel-yellow-green',
        'pairingId' => 'sacred-tradition',
        'locale' => 'am',
        'title' => 'የመስቀል ደመራ በዓል',
        'label' => 'R11 Band (Meskel - Yellow Green)',
    ],
    [
        'recipeId' => 'tall-scroll',
        'category' => 'housewarming',
        'paletteId' => 'warm-stone-taupe',
        'pairingId' => 'cozy-hearth',
        'locale' => 'en',
        'label' => 'R12 Tall Scroll (Housewarming - Stone)',
    ],
];

$results = [];
foreach ($specs as $s) {
    $specObj = new RecipeSpec($s);
    $design = RecipeRegistry::build($s['recipeId'], $specObj);
    $results[] = [
        'recipeId' => $s['recipeId'],
        'label' => $s['label'],
        'locale' => $s['locale'],
        'design' => $design,
    ];
}

$outDir = __DIR__ . '/../storage/app/recipe-sheets';
if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}
$outFile = $outDir . '/designs.json';
file_put_contents($outFile, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Successfully generated designs.json with " . count($results) . " items at: {$outFile}\n";
