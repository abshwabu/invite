<?php

namespace App\Templates\Copy;

class ThankYouCopyLibrary
{
    /**
     * Curated bilingual thank-you copy library per category.
     * Each entry uses placeholders: {{guest_name}}, {{event_title}}, {{host_names}}.
     * All Amharic text includes ['needs_native_review' => true].
     */
    protected static array $copy = [
        'wedding' => [
            'title' => [
                'en' => 'Thank You',
                'am' => 'ምስጋና',
                'needs_native_review' => true,
            ],
            'salutation' => [
                'en' => 'Dear {{guest_name}},',
                'am' => 'ክቡር/ት {{guest_name}}፣',
                'needs_native_review' => true,
            ],
            'message' => [
                [
                    'en' => "Thank you for celebrating with us at {{event_title}} and making our wedding day truly unforgettable. Your love, joyous laughter, and blessings mean everything to us as we begin this new chapter.",
                    'am' => "በ{{event_title}} በዓላችን ላይ ከጎናችን በመሆን ደስታችንን ስለተጋሩ እና የሠርጋችንን ቀን የማይረሳ ስላደረጉልን ከልብ እናመሰግናለን። አዲሱን የሕይወት ምዕራፍ ስንጀምር ፍቅርዎ እና በረከትዎ ለእኛ ትልቅ ዋጋ አለው።",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "Our wedding at {{event_title}} would not have been the same without you. Thank you for your warm wishes, wonderful presence, and cherished blessings on our special day.",
                    'am' => "የ{{event_title}} የሠርግ በዓላችን ያለእርስዎ ሙሉ አይሆንም ነበር። በልዩ ቀናችን አብረውን ስለነበሩ፣ ስለ መልካም ምኞትዎ እና በረከትዎ ከልብ የመነጨ ምስጋናችንን እናቀርባለን።",
                    'needs_native_review' => true,
                ],
            ],
            'closing' => [
                'en' => 'With all our love & gratitude,',
                'am' => 'በሙሉ ፍቅር እና ምስጋና፣',
                'needs_native_review' => true,
            ],
        ],

        'funeral' => [
            'title' => [
                'en' => 'In Grateful Remembrance',
                'am' => 'በልባዊ ምስጋና',
                'needs_native_review' => true,
            ],
            'salutation' => [
                'en' => 'Dear {{guest_name}},',
                'am' => 'ክቡር/ት {{guest_name}}፣',
                'needs_native_review' => true,
            ],
            'message' => [
                [
                    'en' => "We are deeply grateful for your comforting presence, heartfelt condolences, and steadfast prayers during {{event_title}}. Your kindness has been a pillar of strength to our entire family in this season of sorrow.",
                    'am' => "በ{{event_title}} የመታሰቢያ ስነ-ስርዓት ወቅት ከጎናችን በመሆን ላሳዩን ፍቅር፣ ማፅናናት እና ልባዊ ጸሎት ከልብ የመነጨ ምስጋናችን ይድረስዎት። ደግነትዎ በዚህ የኀዘን ጊዜ ለቤተሰባችን ትልቅ ብርታት ሆኖናል።",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "During this time of profound loss, your sympathy, kind words, and attendance at {{event_title}} brought comfort and peace to our heavy hearts. Thank you for standing with us.",
                    'am' => "በዚህ ከባድ የኀዘን ወቅት በ{{event_title}} በመገኘት ላሳዩን አጋርነት፣ የሀዘን መጋራት እና ልባዊ ጸሎት ምስጋናችን ወደር የለውም። እግዚአብሔር ያክብርልን።",
                    'needs_native_review' => true,
                ],
            ],
            'closing' => [
                'en' => 'With deep appreciation & prayers,',
                'am' => 'ከልብ አክብሮት እና ጸሎት ጋር፣',
                'needs_native_review' => true,
            ],
        ],

        'birthday' => [
            'title' => [
                'en' => 'Huge Thanks!',
                'am' => 'ከልብ አመሰግናለሁ!',
                'needs_native_review' => true,
            ],
            'salutation' => [
                'en' => 'Hey {{guest_name}},',
                'am' => 'ውድ {{guest_name}}፣',
                'needs_native_review' => true,
            ],
            'message' => [
                [
                    'en' => "Thank you so much for bringing your vibrant energy and laughter to {{event_title}}! Having you there to celebrate my milestone birthday made the entire day absolutely unforgettable.",
                    'am' => "በ{{event_title}} የልደት ድግሴ ላይ በመገኘት ደስታዬን ስላደመቁልኝ እና የማይረሳ ድንቅ ትውስታ ስላሳለፍን ከልብ አመሰግናለሁ!",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "Thank you for the wonderful memories, laughter, and thoughtful wishes at {{event_title}}. Celebrating another year surrounded by wonderful friends like you is the best gift of all!",
                    'am' => "በ{{event_title}} የልደት በዓሌ ላይ አብረውን ስላከበሩ፣ ስለ ጣፋጭ ጨዋታው እና ድንቅ ስጦታዎ ምስጋናዬ ይድረስዎት። ከእርስዎ ጋር ማክበር ትልቅ ደስታ ነው!",
                    'needs_native_review' => true,
                ],
            ],
            'closing' => [
                'en' => 'Warmly & cheerily,',
                'am' => 'በፍቅር እና በደስታ፣',
                'needs_native_review' => true,
            ],
        ],

        'graduation' => [
            'title' => [
                'en' => 'With Sincere Gratitude',
                'am' => 'የከበረ ምስጋና',
                'needs_native_review' => true,
            ],
            'salutation' => [
                'en' => 'Dear {{guest_name}},',
                'am' => 'ክቡር/ት {{guest_name}}፣',
                'needs_native_review' => true,
            ],
            'message' => [
                [
                    'en' => "Thank you for joining our celebration of {{event_title}}! Your wisdom, guidance, and continuous support throughout this educational journey made this milestone possible and deeply meaningful.",
                    'am' => "በ{{event_title}} የምረቃ በዓሌ ላይ አብረውን ስለነበሩ እና በትምህርት ጉዞዬ ሁሉ ላደረጉልኝ ያልተቆጠበ ድጋፍ፣ ምክር እና ማበረታቻ ከልብ አመሰግናለሁ።",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "I am truly blessed to celebrate this commencement milestone with you at {{event_title}}. Thank you for believing in me and cheering me on every step of the way.",
                    'am' => "ይህን የትምህርት ምዕራፍ በ{{event_title}} አብረውን ስላከበሩ ክብር ተሰምቶኛል። ምንጊዜም ከጎኔ በመሆን ለሰጡኝ ብርታት እና ድጋፍ ምስጋናዬ ከፍ ያለ ነው።",
                    'needs_native_review' => true,
                ],
            ],
            'closing' => [
                'en' => 'With gratitude and high hopes,',
                'am' => 'በከበረ ምስጋና እና አክብሮት፣',
                'needs_native_review' => true,
            ],
        ],

        'baby-shower' => [
            'title' => [
                'en' => 'Sweet Thanks!',
                'am' => 'ልባዊ ምስጋና!',
                'needs_native_review' => true,
            ],
            'salutation' => [
                'en' => 'Dear {{guest_name}},',
                'am' => 'ውድ {{guest_name}}፣',
                'needs_native_review' => true,
            ],
            'message' => [
                [
                    'en' => "Thank you for showering our growing family with so much love, warmth, and generous gifts at {{event_title}}. Our little bundle of joy is already blessed with the kindest community.",
                    'am' => "በ{{event_title}} የሕፃን መወለድ (ቤቢ ሻወር) ዝግጅታችን ላይ ተገኝተው ለተወለደው ህፃን ላሳዩት ፍቅር፣ በረከት እና ያማረ ስጦታ ከልብ እናመሰግናለን።",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "Your presence and delightful blessings at {{event_title}} made this gathering so sweet. We cannot wait for you to meet our precious newcomer!",
                    'am' => "በ{{event_title}} ዝግጅታችን ላይ ከጎናችን በመሆን ደስታችንን ስላደመቁልን እና ያማረ በረከት ስላካፈሉን ከልብ እናመሰግናለን።",
                    'needs_native_review' => true,
                ],
            ],
            'closing' => [
                'en' => 'With love and cuddles,',
                'am' => 'በፍቅር እና በደስታ፣',
                'needs_native_review' => true,
            ],
        ],

        'religious' => [
            'title' => [
                'en' => 'Heartfelt Thanks & Blessings',
                'am' => 'የበረከት ምስጋና',
                'needs_native_review' => true,
            ],
            'salutation' => [
                'en' => 'Dear {{guest_name}},',
                'am' => 'ክቡር/ት {{guest_name}}፣',
                'needs_native_review' => true,
            ],
            'message' => [
                [
                    'en' => "May the Almighty richly bless you and your family for gathering with us in sacred fellowship at {{event_title}}. Your spiritual communion and warm wishes brought immense grace to our celebration.",
                    'am' => "በ{{event_title}} የተቀደሰ የመንፈሳዊ እና የበረከት በዓላችን ላይ አብረውን ስላሳለፉ ሁሉን ቻይ ፈጣሪ እርስዎንም ሆነ ቤተሰብዎን አብዝቶ ይባርክ።",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "Thank you for partaking in the joyful celebration of {{event_title}}. May peace, abundant blessings, and divine protection continually abide with you and your loved ones.",
                    'am' => "በ{{event_title}} የክብር በዓላችን ላይ በመገኘት የደስታችን እና የበረከታችን ተካፋይ ስለሆኑ እግዚአብሔር / አላህ መልካሙን ሁሉ ያድልዎ።",
                    'needs_native_review' => true,
                ],
            ],
            'closing' => [
                'en' => 'With prayers and peace,',
                'am' => 'በጸሎት እና በሰላም፣',
                'needs_native_review' => true,
            ],
        ],

        'corporate' => [
            'title' => [
                'en' => 'Thank You for Joining Us',
                'am' => 'ስለተገኙ እናመሰግናለን',
                'needs_native_review' => true,
            ],
            'salutation' => [
                'en' => 'Dear {{guest_name}},',
                'am' => 'ክቡር/ት {{guest_name}}፣',
                'needs_native_review' => true,
            ],
            'message' => [
                [
                    'en' => "Thank you for joining us at {{event_title}}. We deeply value your thought leadership, engaging presence, and continued partnership as we work together toward innovative future milestones.",
                    'am' => "በ{{event_title}} ጉባኤያችን ላይ ስለተገኙ፣ ላበረከቱት ጠቃሚ ሃሳብ እና ላሳዩት ቀጣይነት ያለው አጋርነት በድርጅታችን ስም የከበረ ምስጋናችንን እናቀርባለን።",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "We sincerely appreciate your participation and insightful contributions at {{event_title}}. It was an honor hosting you, and we look forward to achieving new heights of collaboration.",
                    'am' => "በ{{event_title}} ላይ ንቁ ተሳትፎ ስላደረጉ ከልብ እናመሰግናለን። አብረን ለመስራት እና አዳዲስ ስኬቶችን ለማስመዝገብ ምንጊዜም ዝግጁ ነን።",
                    'needs_native_review' => true,
                ],
            ],
            'closing' => [
                'en' => 'With sincere regards & appreciation,',
                'am' => 'በከበረ አክብሮት እና ሰላምታ፣',
                'needs_native_review' => true,
            ],
        ],

        'anniversary' => [
            'title' => [
                'en' => 'With Deepest Gratitude',
                'am' => 'ከልብ የመነጨ ምስጋና',
                'needs_native_review' => true,
            ],
            'salutation' => [
                'en' => 'Dear {{guest_name}},',
                'am' => 'ክቡር/ት {{guest_name}}፣',
                'needs_native_review' => true,
            ],
            'message' => [
                [
                    'en' => "Thank you for being part of our special celebration at {{event_title}}! Sharing this milestone of devotion, memories, and love with cherished friends and family made it truly unforgettable.",
                    'am' => "የጋብቻ ዓመታችንን መታሰቢያ በ{{event_title}} አብረውን ስላከበሩ እና ያማረውን ጉዟችንን ስላደመቁልን ከልብ እናመሰግናለን። ፍቅርዎ እና በረከትዎ ለእኛ ትልቅ ስጦታ ነው።",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "Celebrating our journey at {{event_title}} surrounded by those who have supported us along the way was pure joy. Thank you for your heartfelt blessings, laughter, and enduring friendship.",
                    'am' => "በ{{event_title}} በዓላችን ላይ በመገኘት ላሳዩን ፍቅር፣ ክብር እና መልካም ምኞት ምስጋናችን የላቀ ነው።",
                    'needs_native_review' => true,
                ],
            ],
            'closing' => [
                'en' => 'With love and heartfelt memories,',
                'am' => 'በፍቅር እና በደስታ፣',
                'needs_native_review' => true,
            ],
        ],

        'housewarming' => [
            'title' => [
                'en' => 'Warmest Thanks!',
                'am' => 'የቤት ምርቃት ምስጋና',
                'needs_native_review' => true,
            ],
            'salutation' => [
                'en' => 'Dear {{guest_name}},',
                'am' => 'ውድ {{guest_name}}፣',
                'needs_native_review' => true,
            ],
            'message' => [
                [
                    'en' => "Thank you for blessing our new home with your presence and warm wishes at {{event_title}}! Your laughter, wonderful company, and gifts made our housewarming feel truly complete.",
                    'am' => "በ{{event_title}} የቤት ምርቃት ድግሳችን ላይ ተገኝተው አዲሱን ቤታችንን በበረከት፣ በፍቅር እና በደስታ ስለሞሉልን ከልብ እናመሰግናለን። ቤታችን ምንጊዜም ለእርስዎ ክፍት ነው!",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "A house is just bricks until filled with cherished friends like you. Thank you for celebrating {{event_title}} with us and making our new beginning so warm and unforgettable.",
                    'am' => "አዲሱን መኖሪያ ቤታችንን በ{{event_title}} አብረውን ስላከበሩ እና መልካም በረከት ስለተመኙልን ልባዊ ምስጋናችን ይድረስዎት።",
                    'needs_native_review' => true,
                ],
            ],
            'closing' => [
                'en' => 'With warm hospitality & friendship,',
                'am' => 'በሞቀ መስተንግዶ እና ፍቅር፣',
                'needs_native_review' => true,
            ],
        ],

        'other' => [
            'title' => [
                'en' => 'Thank You',
                'am' => 'ከልብ እናመሰግናለን',
                'needs_native_review' => true,
            ],
            'salutation' => [
                'en' => 'Dear {{guest_name}},',
                'am' => 'ክቡር/ት {{guest_name}}፣',
                'needs_native_review' => true,
            ],
            'message' => [
                [
                    'en' => "Thank you for honoring us with your presence at {{event_title}}. Your enthusiastic support, gracious companionship, and attendance made this occasion a wonderful success.",
                    'am' => "በ{{event_title}} ዝግጅታችን ላይ ተገኝተው ለነበረዎት ንቁ ተሳትፎ እና ላሳዩን አጋርነት በሙሉ ልብ እናመሰግናለን።",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "We extend our sincerest gratitude to you for taking part in {{event_title}}. Your warm wishes and encouragement made this gathering truly memorable.",
                    'am' => "በ{{event_title}} ላይ አብረውን ስለነበሩ እና ስላበረታቱን ከልብ የመነጨ ምስጋናችንን እናቀርባለን።",
                    'needs_native_review' => true,
                ],
            ],
            'closing' => [
                'en' => 'With warm appreciation,',
                'am' => 'በአክብሮት እና በምስጋና፣',
                'needs_native_review' => true,
            ],
        ],
    ];

    /**
     * Get thank-you copy for a category and role.
     */
    public static function get(string $category, string $role, int $variant = 0, string $locale = 'en'): string
    {
        $cat = self::$copy[$category] ?? self::$copy['other'];

        if ($role === 'message') {
            $messages = $cat['message'] ?? self::$copy['other']['message'];
            $entry = $messages[$variant % count($messages)] ?? $messages[0];
            return $entry[$locale] ?? $entry['en'] ?? '';
        }

        $entry = $cat[$role] ?? (self::$copy['other'][$role] ?? []);
        return $entry[$locale] ?? $entry['en'] ?? '';
    }
}
