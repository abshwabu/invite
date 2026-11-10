<?php

namespace Database\Seeders;

use App\Models\Template;
use App\Templates\Suites\ThankYouSuiteBuilder;
use Illuminate\Database\Seeder;

class ThankYouTemplateSeeder extends Seeder
{
    /**
     * The 5 most distinct suite keys per category to receive sibling thank-you templates.
     */
    public const SUITE_KEYS = [
        // Wedding (5)
        'wedding-garden-arch',
        'wedding-midnight-gold',
        'wedding-burgundy-velvet',
        'wedding-habesha-tilet',
        'wedding-minimal-monogram',

        // Funeral (5)
        'funeral-christian-cross',
        'funeral-islamic-crescent',
        'funeral-peaceful-dove',
        'funeral-olive-repose',
        'funeral-orthodox-lalibela',

        // Birthday (5)
        'birthday-balloon-arch',
        'birthday-confetti-joy',
        'birthday-neon-night',
        'birthday-jungle-safari',
        'birthday-adult-gold-black',

        // Graduation (5)
        'graduation-classic-mortarboard',
        'graduation-laurel-commencement',
        'graduation-crest-rays',
        'graduation-ticket-admission',
        'graduation-poster-stack',

        // Baby Shower (5)
        'baby-dreamy-clouds',
        'baby-sleepy-moon',
        'baby-safari-teddy',
        'baby-stork-delivery',
        'baby-vintage-pram',

        // Religious (5)
        'religious-eid-emerald-star',
        'religious-ramadan-lantern',
        'religious-meskel-demera',
        'religious-timkat-adey-abeba',
        'religious-hajj-umrah',

        // Corporate (5)
        'corporate-executive-summit',
        'corporate-tech-ticket',
        'corporate-gala-night',
        'corporate-product-launch',
        'corporate-award-night',

        // Anniversary (5)
        'anniversary-silver-laurels',
        'anniversary-golden-medallion',
        'anniversary-ruby-jubilee',
        'anniversary-minimal-heart',
        'anniversary-champagne-toast',

        // Housewarming (5)
        'housewarming-jebena-coffee',
        'housewarming-new-roof',
        'housewarming-door-wreath',
        'housewarming-golden-keys',
        'housewarming-modern-minimal',

        // Other (5)
        'other-grand-opening',
        'other-joyful-reunion',
        'other-community-gathering',
        'other-charity-fundraiser',
        'other-milestone-celebration',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $seeded = 0;
        $sortIndex = 500;

        foreach (self::SUITE_KEYS as $suiteKey) {
            $invitation = Template::where('kind', 'invitation')
                ->where('suite_key', $suiteKey)
                ->first();

            if (!$invitation) {
                $this->command?->warn("Invitation template with suite_key '{$suiteKey}' not found. Skipping.");
                continue;
            }

            $thankYouData = ThankYouSuiteBuilder::buildForInvitation($invitation);

            Template::updateOrCreate(
                [
                    'suite_key' => $suiteKey,
                    'kind' => 'thank_you',
                ],
                [
                    'category_id' => $invitation->category_id,
                    'name' => $thankYouData['spec']['name_en'],
                    'preview_path' => "/storage/templates/{$suiteKey}-thankyou.png",
                    'preview_path_am' => "/storage/templates/{$suiteKey}-thankyou_am.png",
                    'design_json' => $thankYouData['design'],
                    'palette' => $thankYouData['design']['palette'],
                    'tags' => $thankYouData['spec']['tags'],
                    'recipe' => $thankYouData['spec']['recipe'],
                    'ornament_sets' => $thankYouData['ornament_sets'],
                    'spec' => $thankYouData['spec'],
                    'width' => 1080,
                    'height' => 1528,
                    'is_system' => true,
                    'is_active' => true,
                    'sort' => $sortIndex++,
                ]
            );

            $seeded++;
        }

        $this->command?->info("Seeded {$seeded} thank-you templates matching invitation suites.");
    }
}
