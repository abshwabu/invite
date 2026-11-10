<?php

namespace App\Templates\Copy;

class CopyLibrary
{
    /**
     * Categories supported by the template system.
     */
    public const CATEGORIES = [
        'wedding',
        'anniversary',
        'birthday',
        'baby-shower',
        'graduation',
        'corporate',
        'housewarming',
        'funeral',
        'faith-symbols',
        'religious-islamic',
        'religious-ethiopian',
    ];

    /**
     * The 5 copy roles required for every invitation template.
     */
    public const ROLES = [
        'opening_line',
        'invitation_line',
        'details_label_lines',
        'closing_line',
        'rsvp_line',
    ];

    /**
     * Bilingual curated copy database.
     * All Amharic strings carry ['needs_native_review' => true].
     */
    protected static array $copy = [
        // ==========================================
        // 1. WEDDING
        // ==========================================
        'wedding' => [
            'opening_line' => [
                [
                    'en' => 'Together with their families',
                    'am' => 'ከቤተሰቦቻችን ጋር በመሆን',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Two lives, two hearts, joined together in friendship and love',
                    'am' => 'የፍቅር ጉዞ ጅምር እና የሁለት ልቦች አንድነት',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'With joyous hearts and grateful spirits',
                    'am' => 'በደስታ በተሞላ ልብ እና በታላቅ አክብሮት',
                    'needs_native_review' => true,
                ],
            ],
            'invitation_line' => [
                [
                    'en' => 'request the honour of your presence at their wedding celebration',
                    'am' => 'በጋብቻችን ቀን አብረውን እንዲሆኑ በአክብሮት ተጋብዘዋል',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'invite you to celebrate their union and the beginning of their new journey',
                    'am' => 'የደስታችን ተካፋይ ይሁኑ ዘንድ በታላቅ አክብሮት እንጋብዝዎታለን',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'cordially invite you to share in the joy of their holy matrimony',
                    'am' => 'በዚህ ልዩ ቀን የደስታችን እና የፍቅራችን ተካፋይ እንዲሆኑ ጥሪያችንን እናቀርባለን',
                    'needs_native_review' => true,
                ],
            ],
            'details_label_lines' => [
                [
                    'en' => "Saturday, November 29, 2026\nAt 2:00 in the afternoon\nSheraton Addis Hotel, Addis Ababa",
                    'am' => "ቅዳሜ ህዳር 20 ቀን 2019 ዓ.ም\nከቀኑ 8:00 ሰዓት ጀምሮ\nሸራተን አዲስ ሆቴል፣ አዲስ አበባ",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "Sunday, December 21, 2026\nReception at 4:00 PM\nHilton Grand Ballroom, Addis Ababa",
                    'am' => "እሁድ ታህሳስ 12 ቀን 2019 ዓ.ም\nየምሽት ድግስ ከቀኑ 10:00 ሰዓት\nሒልተን ሆቴል አዳራሽ፣ አዲስ አበባ",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "Saturday, January 24, 2027\nCeremony at 3:30 PM\nEthiopian Skylight Hotel, Addis Ababa",
                    'am' => "ቅዳሜ ጥር 16 ቀን 2019 ዓ.ም\nስነ-ስርዓት ከቀኑ 9:30 ጀምሮ\nስካይላይት ሆቴል፣ አዲስ አበባ",
                    'needs_native_review' => true,
                ],
            ],
            'closing_line' => [
                [
                    'en' => 'Love never fails.',
                    'am' => 'ፍቅር ለዘላለም ጸንቶ ይኖራል',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Your presence is our most cherished gift.',
                    'am' => 'መምጣትዎ ደስታችንን ያጎላዋል',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'We eagerly look forward to celebrating together.',
                    'am' => 'በደስታ እና በፍቅር እንጠብቅዎታለን',
                    'needs_native_review' => true,
                ],
            ],
            'rsvp_line' => [
                [
                    'en' => 'Kindly respond by November 10th',
                    'am' => 'እባክዎን እስከ ህዳር 1 ቀን ድረስ ያሳውቁን',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'The favour of a reply is requested',
                    'am' => 'እባክዎን መገኘትዎን አስቀድመው ያረጋግጡ',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Please RSVP by visiting our link or calling below',
                    'am' => 'መገኘትዎን በደስታ እናስተናግዳለን፡ እባክዎን በስልክ ያሳውቁን',
                    'needs_native_review' => true,
                ],
            ],
        ],

        // ==========================================
        // 2. ANNIVERSARY
        // ==========================================
        'anniversary' => [
            'opening_line' => [
                [
                    'en' => 'Celebrating 25 years of love, laughter, and devotion',
                    'am' => 'የ25 ዓመት የፍቅር እና የአንድነት ውብ ጉዞ',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'A milestone celebration of enduring love and partnership',
                    'am' => 'የዓመታት ፍቅር እና ጽናት ልዩ መታሰቢያ',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Honoring a lifetime of beautiful shared memories',
                    'am' => 'አብሮ የመኖር ውብ በረከት እና የምስጋና ምሽት',
                    'needs_native_review' => true,
                ],
            ],
            'invitation_line' => [
                [
                    'en' => 'cordially invite you to celebrate our Silver Wedding Anniversary',
                    'am' => 'የብር ኢዮቤልዩ የጋብቻ በዓላችንን አብረውን እንዲያከብሩ እንጋብዝዎታለን',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'invite you to join them in honoring this golden milestone',
                    'am' => 'ለተጓዝነው የፍቅር መንገድ ምስጋና ለማቅረብ አብረውን ይሁኑ',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'request the pleasure of your company as we renew our vows and joy',
                    'am' => 'በጋብቻ በዓላችን ቀን የደስታችን እና የበረከታችን ተካፋይ ይሁኑ',
                    'needs_native_review' => true,
                ],
            ],
            'details_label_lines' => [
                [
                    'en' => "Friday, October 16, 2026\nDinner at 6:30 PM\nHaile Grand Hotel, Addis Ababa",
                    'am' => "ዓርብ ጥቅምት 6 ቀን 2019 ዓ.ም\nየእራት ግብዣ ከምሽቱ 12:30 ጀምሮ\nኃይሌ ግራንድ ሆቴል፣ አዲስ አበባ",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "Saturday, December 5, 2026\nEvening Celebration at 5:00 PM\nRadisson Blu Hotel, Addis Ababa",
                    'am' => "ቅዳሜ ህዳር 26 ቀን 2019 ዓ.ም\nየምሽት በዓል ከቀኑ 11:00 ጀምሮ\nራዲሰን ብሉ ሆቴል፣ አዲስ አበባ",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "Sunday, February 14, 2027\nReception at 4:00 PM\nElilly International Hotel, Addis Ababa",
                    'am' => "እሁድ የካቲት 7 ቀን 2019 ዓ.ም\nዝግጅት ከቀኑ 10:00 ሰዓት\nኤሊሊ ኢንተርናሽናል ሆቴል፣ አዲስ አበባ",
                    'needs_native_review' => true,
                ],
            ],
            'closing_line' => [
                [
                    'en' => 'Still writing our story, one beautiful day at a time.',
                    'am' => 'ፍቅራችን በእያንዳንዱ ቀን አዲስ ሆኖ ይቀጥላል',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Surrounded by family, friends, and everlasting gratitude.',
                    'am' => 'በወዳጅ እና በቤተሰብ መከበብ ታላቅ በረከት ነው',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Thank you for being an essential part of our journey.',
                    'am' => 'የህይወት ጉዟችን አካል ስለሆናችሁ እናመሰግናለን',
                    'needs_native_review' => true,
                ],
            ],
            'rsvp_line' => [
                [
                    'en' => 'Please confirm your attendance by October 1st',
                    'am' => 'እባክዎን እስከ መስከረም 20 ድረስ ያሳውቁን',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'RSVP to the family host committee',
                    'am' => 'መገኘትዎን ለአዘጋጆቹ አስቀድመው ያረጋግጡ',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Your reply is requested at your earliest convenience',
                    'am' => 'ምላሽዎን በተቻለ ፍጥነት እንዲያሳውቁን በአክብሮት እንጠይቃለን',
                    'needs_native_review' => true,
                ],
            ],
        ],

        // ==========================================
        // 3. BIRTHDAY
        // ==========================================
        'birthday' => [
            'opening_line' => [
                [
                    'en' => 'Another year bolder, brighter, and full of joy!',
                    'am' => 'አንድ ተጨማሪ የደስታ እና የዕድገት ዓመት!',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Join us as we celebrate a very special birthday!',
                    'am' => 'የልደት ቀን ልዩ ድግስ እና የደስታ ምሽት',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Making memories and blowing out candles together',
                    'am' => 'በደስታ የተሞላ የልደት በዓልን በጋራ እናክብር',
                    'needs_native_review' => true,
                ],
            ],
            'invitation_line' => [
                [
                    'en' => 'invites you to join in the birthday festivities and dancing',
                    'am' => 'የልደት ቀኔን አብረውን እንዲያከብሩ በደስታ እጋብዝዎታለሁ',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'invite you to celebrate our child\'s milestone birthday',
                    'am' => 'የልጃችንን ልደት ለማክበር ከእኛ ጋር እንዲሆኑ ተጋብዘዋል',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'warmly welcomes you to an evening of food, drinks, and laughter',
                    'am' => 'በዚህ አስደሳች ቀን አብረን እንድንደሰት ጥሪያችንን እናቀርባለን',
                    'needs_native_review' => true,
                ],
            ],
            'details_label_lines' => [
                [
                    'en' => "Saturday, August 15, 2026\nParty kicks off at 3:00 PM\nFriendship Park Lounge, Addis Ababa",
                    'am' => "ቅዳሜ ነሐሴ 9 ቀን 2018 ዓ.ም\nድግሱ ከቀኑ 9:00 ሰዓት ይጀምራል\nወዳጅነት ፓርክ አዳራሽ፣ አዲስ አበባ",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "Sunday, September 20, 2026\nCelebration at 4:30 PM\nBole Medhanialem Area, Addis Ababa",
                    'am' => "እሁድ መስከረም 10 ቀን 2019 ዓ.ም\nከቀኑ 10:30 ጀምሮ\nቦሌ መድኃኔዓለም አካባቢ፣ አዲስ አበባ",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "Friday, November 27, 2026\nDinner & Music from 7:00 PM\nKuriftu Resort, Bishoftu",
                    'am' => "ዓርብ ህዳር 18 ቀን 2019 ዓ.ም\nእራት እና ሙዚቃ ከምሽቱ 1:00 ጀምሮ\nኩሪፍቱ ሪዞርት፣ ቢሾፍቱ",
                    'needs_native_review' => true,
                ],
            ],
            'closing_line' => [
                [
                    'en' => 'The greatest gift is having you celebrate with us!',
                    'am' => 'የእርስዎ መገኘት ለእኛ ትልቅ ስጦታ ነው!',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Get ready for cake, music, and great memories.',
                    'am' => 'ኬክ፣ ሙዚቃ እና አስደሳች ትውስታ አብረን እናሳልፍ',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Cheers to another year of life and adventure!',
                    'am' => 'ለተጨማሪ የደስታ እና የበረከት ዓመታት!',
                    'needs_native_review' => true,
                ],
            ],
            'rsvp_line' => [
                [
                    'en' => 'Let us know if you can make it by August 5th',
                    'am' => 'እባክዎን እስከ ሐምሌ 29 ድረስ መምጣትዎን ያሳውቁን',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'RSVP via text or call to save your slice of cake!',
                    'am' => 'እባክዎን መገኘትዎን አስቀድመው ያረጋግጡልን',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Please confirm headcount by sending an RSVP',
                    'am' => 'የእንግዶች ቁጥር እንድናስተካክል እባክዎን ቀድመው ያሳውቁን',
                    'needs_native_review' => true,
                ],
            ],
        ],

        // ==========================================
        // 4. BABY SHOWER
        // ==========================================
        'baby-shower' => [
            'opening_line' => [
                [
                    'en' => 'A sweet little miracle is on the way!',
                    'am' => 'አዲስ ህይወት ወደ ዓለማችን ሊቀላቀል ነው!',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Showering the mother-to-be with love, blessings, and tiny gifts',
                    'am' => 'ትንሹን ልዑካችንን ለመቀበል የፍቅር እና የበረከት ዝግጅት',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Ten little fingers, ten little toes, and endless love',
                    'am' => 'የአዲስ ቤተሰብ አባል መምጣት አስደሳች ዜና',
                    'needs_native_review' => true,
                ],
            ],
            'invitation_line' => [
                [
                    'en' => 'cordially invite you to a Baby Shower in honor of the parents-to-be',
                    'am' => 'የህፃኑን መምጣት ምክንያት በማድረግ በተዘጋጀው ዝግጅት ላይ እንዲገኙ ተጋብዘዋል',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'invite you to join us as we shower the growing family with blessings',
                    'am' => 'የቤተሰባችንን አዲስ በረከት በጋራ እንቀበል ዘንድ ተጋብዘዋል',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'welcome you to celebrate the joyful arrival of our little one',
                    'am' => 'የህፃን ምርቃት እና የደስታ ምሽት አብረውን ያክብሩ',
                    'needs_native_review' => true,
                ],
            ],
            'details_label_lines' => [
                [
                    'en' => "Sunday, October 25, 2026\nAfternoon Tea at 2:30 PM\nJupiter International Hotel, Addis Ababa",
                    'am' => "እሁድ ጥቅምት 15 ቀን 2019 ዓ.ም\nየከሰዓት ሻይ ዝግጅት ከቀኑ 8:30 ጀምሮ\nጁፒተር ሆቴል፣ አዲስ አበባ",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "Saturday, November 14, 2026\nGathering at 3:00 PM\nGolden Tulip Hotel, Addis Ababa",
                    'am' => "ቅዳሜ ህዳር 5 ቀን 2019 ዓ.ም\nከቀኑ 9:00 ሰዓት\nጎልደን ቱሊፕ ሆቴል፣ አዲስ አበባ",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "Saturday, December 12, 2026\nCelebration from 2:00 PM\nFamily Residence, Old Airport, Addis Ababa",
                    'am' => "ቅዳሜ ታህሳስ 3 ቀን 2019 ዓ.ም\nከቀኑ 8:00 ጀምሮ\nበቤተሰብ መኖሪያ ቤት፣ ኦልድ ኤርፖርት",
                    'needs_native_review' => true,
                ],
            ],
            'closing_line' => [
                [
                    'en' => 'Tiny hands, big dreams, and a world waiting to greet you.',
                    'am' => 'በፍቅር እና በበረከት የተሞላ የልጅነት ዘመን ይሁንለት',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Thank you for showering our growing family with love.',
                    'am' => 'ለፍቅራችሁ እና ለጸሎታችሁ ሁሉ ከልብ እናመሰግናለን',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Blessed with love, surrounded by grace.',
                    'am' => 'እግዚአብሔር በሰላም እንዲያገናኘን ጸሎታችን ነው',
                    'needs_native_review' => true,
                ],
            ],
            'rsvp_line' => [
                [
                    'en' => 'Please RSVP by October 10th to assist with hosting',
                    'am' => 'እባክዎን እስከ ጥቅምት 1 ድረስ መምጣትዎን ያረጋግጡ',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Kindly respond to the host committee',
                    'am' => 'እባክዎን ለአዘጋጆቹ አስቀድመው ያሳውቁ',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'RSVP requested by November 1st',
                    'am' => 'መገኘትዎን በደስታ እንድናስተናግድ ቀድመው ያሳውቁን',
                    'needs_native_review' => true,
                ],
            ],
        ],

        // ==========================================
        // 5. GRADUATION
        // ==========================================
        'graduation' => [
            'opening_line' => [
                [
                    'en' => 'The tassel was worth the hassle!',
                    'am' => 'የድካም ውጤት እና የስኬት አዲስ ምዕራፍ!',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Celebrating dedication, perseverance, and academic triumph',
                    'am' => 'የእውቀት ጉዞ ፍሬ እና የታላቅ ድል ቀን',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Honoring our graduate as a new chapter begins',
                    'am' => 'አዲስ የስራ እና የህይወት አድማስ መክፈቻ',
                    'needs_native_review' => true,
                ],
            ],
            'invitation_line' => [
                [
                    'en' => 'invite you to join us in celebrating this proud graduation milestone',
                    'am' => 'በምረቃዬ ቀን የደስታዬ ተካፋይ እንዲሆኑ በክብር ተጋብዘዋል',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'request the honour of your presence at our graduation dinner',
                    'am' => 'የዩኒቨርሲቲ ምረቃ በዓላችንን በጋራ እናክብር ዘንድ ተጋብዘዋል',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'cordially invite you to honor our graduate\'s hard work and success',
                    'am' => 'ለዚህ ታላቅ ስኬት አብረውን እንዲደሰቱ ጥሪያችንን እናቀርባለን',
                    'needs_native_review' => true,
                ],
            ],
            'details_label_lines' => [
                [
                    'en' => "Saturday, July 18, 2026\nDinner & Toast at 6:00 PM\nInter Luxury Hotel, Addis Ababa",
                    'am' => "ቅዳሜ ሐምሌ 11 ቀን 2018 ዓ.ም\nየእራት ግብዣ ከምሽቱ 12:00 ጀምሮ\nኢንተር ላግዠሪ ሆቴል፣ አዲስ አበባ",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "Sunday, August 2, 2026\nReception at 4:30 PM\nAddis Ababa Millennium Hall Lounge",
                    'am' => "እሁድ ሐምሌ 26 ቀን 2018 ዓ.ም\nዝግጅት ከቀኑ 10:30 ሰዓት\nሚሌኒየም አዳራሽ፣ አዲስ አበባ",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "Friday, July 24, 2026\nCelebration starts at 5:00 PM\nCapital Hotel and Spa, Addis Ababa",
                    'am' => "ዓርብ ሐምሌ 17 ቀን 2018 ዓ.ም\nከቀኑ 11:00 ጀምሮ\nካፒታል ሆቴል፣ አዲስ አበባ",
                    'needs_native_review' => true,
                ],
            ],
            'closing_line' => [
                [
                    'en' => 'The future belongs to those who believe in their dreams.',
                    'am' => 'የነገው ብሩህ ተስፋ ለታታሪዎች ክፍት ነው',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'With sincere thanks to all who supported this journey.',
                    'am' => 'ለሁሉ አጋዥ ወዳጅ እና ቤተሰብ ምስጋናዬ የላቀ ነው',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'On to the next great adventure!',
                    'am' => 'ወደ አዲሱ የስራ እና የህይወት ምዕራፍ በድል!',
                    'needs_native_review' => true,
                ],
            ],
            'rsvp_line' => [
                [
                    'en' => 'Please RSVP by July 5th',
                    'am' => 'እባክዎን እስከ ሰኔ 28 ድረስ መምጣትዎን ያሳውቁን',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Confirm your attendance to assist with dinner arrangements',
                    'am' => 'የእራት ዝግጅት ለማስተካከል መገኘትዎን አስቀድመው ያረጋግጡ',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Kindly reply by calling or clicking below',
                    'am' => 'እባክዎን በስልክ ወይም በሊንኩ በኩል ያረጋግጡልን',
                    'needs_native_review' => true,
                ],
            ],
        ],

        // ==========================================
        // 6. CORPORATE
        // ==========================================
        'corporate' => [
            'opening_line' => [
                [
                    'en' => 'Partnering for progress, celebrating shared vision',
                    'am' => 'ዓመታዊ የድርጅቱ ጉባኤ እና የክብር ምሽት',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Celebrating an extraordinary year of innovation and growth',
                    'am' => 'የፈጠራ፣ የስራ ትጋት እና የስኬት አጋርነት',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'An exclusive evening of executive networking and excellence',
                    'am' => 'የአዲስ ራዕይ እና የቢዝነስ አጋሮች ልዩ ስብሰባ',
                    'needs_native_review' => true,
                ],
            ],
            'invitation_line' => [
                [
                    'en' => 'requests the pleasure of your company at the Annual Gala Dinner',
                    'am' => 'በድርጅታችን ዓመታዊ የክብር ግብዣ ላይ በክብር ተጋብዘዋል',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'cordially invites you to the Official Product Launch and Showcase',
                    'am' => 'በአዲሱ ምርት እና አገልግሎት ይፋ ማድረጊያ መድረክ ላይ ይሳተፉ',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'welcomes esteemed partners and clients to an evening of gratitude',
                    'am' => 'ውድ የንግድ አጋሮቻችን በተገኙበት ልዩ ምሽት እንድትገኙ እንጋብዛለን',
                    'needs_native_review' => true,
                ],
            ],
            'details_label_lines' => [
                [
                    'en' => "Thursday, November 19, 2026\nCocktails at 6:30 PM | Keynote at 7:30 PM\nUNECA Conference Centre, Addis Ababa",
                    'am' => "ሐሙስ ህዳር 10 ቀን 2019 ዓ.ም\nመስተንግዶ ከምሽቱ 12:30 | መርሐ-ግብር ከምሽቱ 1:30\nኢካ አዳራሽ (UNECA)፣ አዲስ አበባ",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "Wednesday, December 9, 2026\nDoors open at 5:30 PM\nSheraton Lalibela Grand Ballroom",
                    'am' => "ረቡዕ ህዳር 30 ቀን 2019 ዓ.ም\nከምሽቱ 11:30 ጀምሮ\nሸራተን ሆቴል ላሊበላ አዳራሽ",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "Friday, October 23, 2026\nExecutive Briefing at 4:00 PM\nHyatt Regency Hotel, Addis Ababa",
                    'am' => "ዓርብ ጥቅምት 13 ቀን 2019 ዓ.ም\nየአመራር መድረክ ከቀኑ 10:00 ሰዓት\nሀያት ሬጀንሲ ሆቴል፣ አዲስ አበባ",
                    'needs_native_review' => true,
                ],
            ],
            'closing_line' => [
                [
                    'en' => 'Building sustainable futures together.',
                    'am' => 'የነገውን ስኬት በጋራ አጋርነት እንገነባለን',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Business formal attire is kindly requested.',
                    'am' => 'መደበኛ የቢሮ የክብር አለባበስ (Business Formal) ይመረጣል',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'We value your continued trust and leadership.',
                    'am' => 'ለአጋርነታችሁ እና ለታማኝነታችሁ የላቀ ምስጋና እናቀርባለን',
                    'needs_native_review' => true,
                ],
            ],
            'rsvp_line' => [
                [
                    'en' => 'Please confirm registration by November 5th',
                    'am' => 'እባክዎን እስከ ጥቅምት 26 ድረስ ምዝገባዎን ያጠናቅቁ',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'RSVP strictly required for security credentials',
                    'am' => 'ለደህንነት እና ባጅ ዝግጅት መገኘትዎን አስቀድመው ያረጋግጡ',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Please send delegate names to the executive liaison',
                    'am' => 'የተወካዮችን ስም አስቀድመው እንዲልኩልን በአክብሮት እንጠይቃለን',
                    'needs_native_review' => true,
                ],
            ],
        ],

        // ==========================================
        // 7. HOUSEWARMING
        // ==========================================
        'housewarming' => [
            'opening_line' => [
                [
                    'en' => 'New home, new memories, same warm friends',
                    'am' => 'አዲሱ ቤታችን በእናንተ ፍቅር እና ሳቅ ይሞላ',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'We have unpacked the boxes and settled in!',
                    'am' => 'የአዲስ ቤት ምርቃት እና ሞቅ ያለ ምስጋና',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Opening our doors and hearts to welcome you',
                    'am' => 'ሞቅ ወዳለው አዲሱ ማረፊያችን እንኳን ደህና መጡ',
                    'needs_native_review' => true,
                ],
            ],
            'invitation_line' => [
                [
                    'en' => 'warmly invite you to celebrate our new housewarming',
                    'am' => 'የአዲሱ ቤታችንን ምርቃት አብረውን እንዲያከብሩ ተጋብዘዋል',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'invite you over for food, music, and an open house tour',
                    'am' => 'በአዲሱ ቤታችን በረከት እንድትካፈሉ በደስታ እንጠብቃችኋለን',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'request your company to christen our new hearth with joy',
                    'am' => 'አብረን ቡና ጠጥተን ደስታችንን እንድንጋራ ጥሪያችንን እናቀርባለን',
                    'needs_native_review' => true,
                ],
            ],
            'details_label_lines' => [
                [
                    'en' => "Sunday, November 8, 2026\nOpen house from 1:00 PM to 6:00 PM\nCMC Michael Area, Villa 42, Addis Ababa",
                    'am' => "እሁድ ጥቅምት 29 ቀን 2019 ዓ.ም\nከቀኑ 7:00 እስከ 12:00 ሰዓት\nሲኤምሲ ሚካኤል አካባቢ፣ ቪላ 42፣ አዲስ አበባ",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "Saturday, October 17, 2026\nDinner from 4:00 PM\nAyat Real Estate Zone 3, Addis Ababa",
                    'am' => "ቅዳሜ ጥቅምት 7 ቀን 2019 ዓ.ም\nከቀኑ 10:00 ሰዓት ጀምሮ\nአያት ዞን 3፣ አዲስ አበባ",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "Sunday, December 20, 2026\nAfternoon gathering at 2:00 PM\nLebu Mebrat Hail, Addis Ababa",
                    'am' => "እሁድ ታህሳስ 11 ቀን 2019 ዓ.ም\nከቀኑ 8:00 ጀምሮ\nለቡ መብራት ኃይል፣ አዲስ አበባ",
                    'needs_native_review' => true,
                ],
            ],
            'closing_line' => [
                [
                    'en' => 'May love and laughter fill every room.',
                    'am' => 'ቤታችን በፍቅር፣ በሰላም እና በበረከት ይሞላ',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'No gifts needed, your good energy is enough!',
                    'am' => 'መምጣትዎ ብቻ ለቤታችን ትልቅ ምርቃት ነው',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Can’t wait to show you around!',
                    'am' => 'በአዲሱ ቤታችን በደስታ እንቀበላችኋለን!',
                    'needs_native_review' => true,
                ],
            ],
            'rsvp_line' => [
                [
                    'en' => 'Let us know if you can drop by by November 1st',
                    'am' => 'እባክዎን እስከ ጥቅምት 22 ድረስ መምጣትዎን ያሳውቁን',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'RSVP to help us plan food and refreshments',
                    'am' => 'መስተንግዶ ለማዘጋጀት እንዲረዳን አስቀድመው ያረጋግጡ',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Please message or call ahead',
                    'am' => 'እባክዎን በስልክ መልዕክት ወይም በመደወል ያሳውቁን',
                    'needs_native_review' => true,
                ],
            ],
        ],

        // ==========================================
        // 8. FUNERAL & MEMORIAL
        // ==========================================
        'funeral' => [
            'opening_line' => [
                [
                    'en' => 'In loving and cherished memory of our beloved',
                    'am' => 'የተወደዱትን ውድ ወላጃችንን/ቤተሰባችንን በክብር እናስባለን',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'A life beautifully lived, a soul forever remembered',
                    'am' => 'የህይወት ጉዞ ማጠቃለያ እና የዘላለም እረፍት መታሰቢያ',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Gathering in prayer, love, and thanksgiving for a noble life',
                    'am' => 'የነፍስ ማረፊያ እና የጸሎት መታሰቢያ ጉባኤ',
                    'needs_native_review' => true,
                ],
            ],
            'invitation_line' => [
                [
                    'en' => 'invite you to join us in commemorating the life and legacy',
                    'am' => 'በተወዳጁ ወላጃችን የመታሰቢያ ጸሎት ላይ እንዲገኙ በአክብሮት ተጠርተዋል',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'request your presence at the memorial service and reception',
                    'am' => 'የመጨረሻውን የክብር ስንብት አብረውን እንዲያደርጉ እንጋብዛለን',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'ask you to stand with our family in prayer and fellowship',
                    'am' => 'ለተሰናበቱን ውድ ቤተሰባችን ጸሎት ለማድረስ አብረውን ይሁኑ',
                    'needs_native_review' => true,
                ],
            ],
            'details_label_lines' => [
                [
                    'en' => "Saturday, October 10, 2026\nService at 11:00 AM | Reception at 1:00 PM\nSt. Joseph Church & Hall, Addis Ababa",
                    'am' => "ቅዳሜ መስከረም 30 ቀን 2019 ዓ.ም\nጸሎት ከረፋዱ 5:00 | መስተንግዶ ከቀኑ 7:00\nቅዱስ ዮሴፍ ቤተክርስቲያን አዳራሽ፣ አዲስ አበባ",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "Sunday, November 15, 2026\nMemorial at 10:30 AM\nHoly Trinity Cathedral, Addis Ababa",
                    'am' => "እሁድ ህዳር 6 ቀን 2019 ዓ.ም\nየመታሰቢያ ስነ-ስርዓት ከረፋዱ 4:30\nመንበረ ጸባዖት ቅድስት ሥላሴ ካቴድራል",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "Saturday, December 19, 2026\nRemembrance Service at 1:30 PM\nFamily Hall, Sarbet, Addis Ababa",
                    'am' => "ቅዳሜ ታህሳስ 10 ቀን 2019 ዓ.ም\nየጸሎት ስነ-ስርዓት ከቀኑ 7:30 ጀምሮ\nበቤተሰብ አዳራሽ፣ ሳርቤት፣ አዲስ አበባ",
                    'needs_native_review' => true,
                ],
            ],
            'closing_line' => [
                [
                    'en' => 'May they rest in eternal peace and perpetual light.',
                    'am' => 'እግዚአብሔር ነፍሳቸውን በአጸደ ገነት ያሳርፍልን',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Forever engraved in our hearts and prayers.',
                    'am' => 'ትውስታቸው ሁልጊዜ በልባችን እና በጸሎታችን ይኖራል',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Blessed are those who mourn, for they shall be comforted.',
                    'am' => 'እግዚአብሔር ለቤተሰቡ እና ለወዳጅ ዘመድ መጽናናትን ይስጥ',
                    'needs_native_review' => true,
                ],
            ],
            'rsvp_line' => [
                [
                    'en' => 'Kindly notify the family coordinators if attending the reception',
                    'am' => 'በመስተንግዶው ላይ የሚገኙ ከሆነ እባክዎን ለቤተሰቡ ያሳውቁ',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Condolences and inquiries may be directed to the family below',
                    'am' => 'የማጽናኛ መልዕክት እና ጥያቄዎችን ከታች ባለው ስልክ ያድርሱ',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Your presence and prayers are deeply appreciated',
                    'am' => 'መገኘትዎ እና ጸሎትዎ ለቤተሰቡ ትልቅ መጽናኛ ነው',
                    'needs_native_review' => true,
                ],
            ],
        ],

        // ==========================================
        // 9. FAITH SYMBOLS (ECUMENICAL / BLESSING)
        // ==========================================
        'faith-symbols' => [
            'opening_line' => [
                [
                    'en' => 'Blessed is the home that honors love and grace',
                    'am' => 'በእምነት እና በፍቅር የፀና የተቀደሰ ህብረት',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Walking together in the light of eternal fellowship',
                    'am' => 'የጸጋ እና የምስጋና ታላቅ በረከት',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'With thanksgiving in our hearts for God\'s abundant blessings',
                    'am' => 'እግዚአብሔር በመካከላችን አለ፡ ስሙ የተመሰገነ ይሁን',
                    'needs_native_review' => true,
                ],
            ],
            'invitation_line' => [
                [
                    'en' => 'cordially invite you to our Thanksgiving and Dedication service',
                    'am' => 'በዚህ ቅዱስ መንፈሳዊ በዓል ላይ አብረውን እንዲሆኑ ተጋብዘዋል',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'welcome you to celebrate this blessed holy occasion with our family',
                    'am' => 'በቤተሰብ መንፈሳዊ ጉባኤ እና የምስጋና ቀን ላይ ይሳተፉ',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'invite you to join our prayers and fellowship of praise',
                    'am' => 'የምስጋና እና የጸሎት ቀን በጋራ እናክብር ዘንድ ጥሪያችንን እናቀርባለን',
                    'needs_native_review' => true,
                ],
            ],
            'details_label_lines' => [
                [
                    'en' => "Sunday, September 27, 2026\nService at 9:00 AM | Fellowship at 12:00 PM\nGrace Fellowship Hall, Bole, Addis Ababa",
                    'am' => "እሁድ መስከረም 17 ቀን 2019 ዓ.ም\nየጸሎት ጊዜ ከጠዋቱ 3:00 | ማዕድ ከቀኑ 6:00\nቦሌ አዳራሽ፣ አዲስ አበባ",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "Saturday, November 21, 2026\nCeremony at 10:00 AM\nFaith Community Center, Addis Ababa",
                    'am' => "ቅዳሜ ህዳር 12 ቀን 2019 ዓ.ም\nስነ-ስርዓት ከጠዋቱ 4:00 ሰዓት\nመንፈሳዊ ማዕከል፣ አዲስ አበባ",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "Sunday, December 27, 2026\nPraise Gathering from 2:00 PM\nFriendship Center, Addis Ababa",
                    'am' => "እሁድ ታህሳስ 18 ቀን 2019 ዓ.ም\nየምስጋና ጊዜ ከቀኑ 8:00 ጀምሮ\nፍሬንድሺፕ አዳራሽ፣ አዲስ አበባ",
                    'needs_native_review' => true,
                ],
            ],
            'closing_line' => [
                [
                    'en' => 'The Lord bless you and keep you.',
                    'am' => 'እግዚአብሔር ይባርክዎ ይጠብቅዎትም',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Grace, peace, and love be multiplied to you.',
                    'am' => 'ጸጋ እና ሰላም ለሁላችን ይብዛ',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Giving thanks always for all things.',
                    'am' => 'ስለ ሁሉም ነገር ፈጣሪ የተመሰገነ ይሁን',
                    'needs_native_review' => true,
                ],
            ],
            'rsvp_line' => [
                [
                    'en' => 'Please confirm your attendance by September 15th',
                    'am' => 'እባክዎን እስከ መስከረም 5 ድረስ መገኘትዎን ያረጋግጡ',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Kindly RSVP to the church elders',
                    'am' => 'እባክዎን ለአዘጋጆቹ አስቀድመው ያሳውቁ',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Your confirmation helps our fellowship committee',
                    'am' => 'የእርስዎን መገኘት ቀድመው ማሳወቅ ዝግጅታችንን ያቀላጥፋል',
                    'needs_native_review' => true,
                ],
            ],
        ],

        // ==========================================
        // 10. RELIGIOUS ISLAMIC (NIKAH & EID)
        // ==========================================
        'religious-islamic' => [
            'opening_line' => [
                [
                    'en' => 'In the Name of Allah, the Most Gracious, the Most Merciful',
                    'am' => 'ቢስሚላሂር ራሕማኒር ራሒም (በአላህ ስም እጅግ በጣም ሩኅሩህ በጣም አዛኝ በሆነው)',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'And We created you in pairs (Surah An-Naba 78:8)',
                    'am' => 'ጥንዶች አድርገን ፈጠርናችሁ (ሱረቱ አን-ነበእ 78:8)',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'May peace, mercy, and blessings of Allah be upon you',
                    'am' => 'የአላህ ሰላም፣ እዝነት እና በረከት በእናንተ ላይ ይሁን',
                    'needs_native_review' => true,
                ],
            ],
            'invitation_line' => [
                [
                    'en' => 'cordially invite you to celebrate the sacred Nikah and Walima ceremony',
                    'am' => 'በልዩ የኒካህ እና የሰርግ (ወሊማ) ዝግጅታችን ላይ እንዲገኙ በአክብሮት ተጋብዘዋል',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'request the honour of your presence to share in their joyous Walima banquet',
                    'am' => 'በዚህ የተባረከ ቀን የዱዓ እና የደስታ ተካፋይ ይሁኑ ዘንድ ጥሪያችንን እናቀርባለን',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'invite our brothers and sisters to a blessed Eid gathering of gratitude',
                    'am' => 'የዒድ በዓልን በደስታ እና በወንድማማችነት አብረን እናክብር ዘንድ ተጋብዘዋል',
                    'needs_native_review' => true,
                ],
            ],
            'details_label_lines' => [
                [
                    'en' => "Sunday, November 1, 2026\nNikah at 11:30 AM | Walima Luncheon at 1:00 PM\nSheraton Addis Lalibela Ballroom",
                    'am' => "እሁድ ጥቅምት 22 ቀን 2019 ዓ.ም\nየኒካህ ስነ-ስርዓት ከረፋዱ 5:30 | ወሊማ ከቀኑ 7:00\nሸራተን ሆቴል አዳራሽ፣ አዲስ አበባ",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "Saturday, December 26, 2026\nWalima Reception at 2:00 PM\nSkylight Grand Ballroom, Addis Ababa",
                    'am' => "ቅዳሜ ታህሳስ 17 ቀን 2019 ዓ.ም\nየወሊማ ድግስ ከቀኑ 8:00 ጀምሮ\nስካይላይት ግራንድ አዳራሽ፣ አዲስ አበባ",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "Friday, October 9, 2026\nBanquet at 6:30 PM\nRadisson Blu Ballroom, Addis Ababa",
                    'am' => "ዓርብ መስከረም 29 ቀን 2019 ዓ.ም\nየእራት ግብዣ ከምሽቱ 12:30 ጀምሮ\nራዲሰን ብሉ ሆቴል፣ አዲስ አበባ",
                    'needs_native_review' => true,
                ],
            ],
            'closing_line' => [
                [
                    'en' => 'Barakallahu lakuma wa baraka alaikuma (May Allah bless you both).',
                    'am' => 'ባረከላሁ ለኩማ ወባረከ አለይኩማ (አላህ ሁለታችሁንም ይባርካችሁ)',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Your sincere prayers and presence bring supreme joy.',
                    'am' => 'ቅን ዱዓዎ እና መገኘትዎ ደስታችንን የተሟላ ያደርገዋል',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'May Allah bless this union and grant prosperity and peace.',
                    'am' => 'አላህ ጋብቻቸውን የተባረከ እና የሰላም ያድርግላቸው',
                    'needs_native_review' => true,
                ],
            ],
            'rsvp_line' => [
                [
                    'en' => 'Please confirm your attendance by October 18th',
                    'am' => 'እባክዎን እስከ ጥቅምት 8 ድረስ መገኘትዎን ያሳውቁን',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Kindly respond to the family liaisons listed below',
                    'am' => 'እባክዎን ከታች ለተጠቀሱት የቤተሰብ አስተባባሪዎች ያሳውቁ',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'RSVP required for seating arrangements',
                    'am' => 'የቦታ ዝግጅት እንድናስተካክል መገኘትዎን ቀድመው ያረጋግጡ',
                    'needs_native_review' => true,
                ],
            ],
        ],

        // ==========================================
        // 11. RELIGIOUS ETHIOPIAN (TEKLIL & MESKEL)
        // ==========================================
        'religious-ethiopian' => [
            'opening_line' => [
                [
                    'en' => 'In the name of the Father, and of the Son, and of the Holy Spirit, One God, Amen',
                    'am' => 'በስመ አብ ወወልድ ወመንፈስ ቅዱስ አሐዱ አምላክ አሜን',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'What God has joined together, let no man put asunder',
                    'am' => 'እግዚአብሔር ያጣመረውን ሰው አይለየውም (ማቴ 19:6)',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'By God\'s divine grace and holy blessing',
                    'am' => 'በእግዚአብሔር ቸርነት እና በቅዱሳን አማላጅነት',
                    'needs_native_review' => true,
                ],
            ],
            'invitation_line' => [
                [
                    'en' => 'cordially invite you to witness our Holy Matrimony (Teklil) service',
                    'am' => 'በቅዱስ ተክሊል ጋብቻችን ላይ የደስታችን ተካፋይ እንዲሆኑ በክብር ተጋብዘዋል',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'request the honour of your presence at our church blessing and banquet',
                    'am' => 'በመንፈሳዊው የበረከት እና የምስጋና በዓላችን ላይ አብረውን ይሁኑ',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'invite you to join us in prayer as we receive the sacred crown',
                    'am' => 'የቅዱስ ኪዳን ጋብቻችንን በጸሎት እና በምስጋና አብረውን እንዲያከብሩ እንጠራዎታለን',
                    'needs_native_review' => true,
                ],
            ],
            'details_label_lines' => [
                [
                    'en' => "Sunday, November 22, 2026\nTeklil Service at 10:30 AM | Reception at 1:30 PM\nBole Medhanialem Cathedral, Addis Ababa",
                    'am' => "እሁድ ህዳር 13 ቀን 2019 ዓ.ም\nየተክሊል ስነ-ስርዓት ከረፋዱ 4:30 | ድግስ ከቀኑ 7:30\nቦሌ መድኃኔዓለም ካቴድራል አዳራሽ፣ አዲስ አበባ",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "Saturday, January 30, 2027\nChurch Blessing at 11:00 AM\nSt. Urael Church & Banquet Hall, Addis Ababa",
                    'am' => "ቅዳሜ ጥር 22 ቀን 2019 ዓ.ም\nየተክሊል ጸሎት ከረፋዱ 5:00 ጀምሮ\nቅዱስ ዑራኤል ቤተክርስቲያን አዳራሽ፣ አዲስ አበባ",
                    'needs_native_review' => true,
                ],
                [
                    'en' => "Sunday, February 21, 2027\nCeremony at 10:00 AM\nEntoto Kidane Mehret Church, Addis Ababa",
                    'am' => "እሁድ የካቲት 14 ቀን 2019 ዓ.ም\nስነ-ስርዓት ከጠዋቱ 4:00 ሰዓት\nእንጦጦ ኪዳነ ምሕረት አዳራሽ፣ አዲስ አበባ",
                    'needs_native_review' => true,
                ],
            ],
            'closing_line' => [
                [
                    'en' => 'May the peace of Christ reign in our hearts forever.',
                    'am' => 'የክርስቶስ ፍቅር እና ሰላም በቤታችን ለዘላለም ይንገስ',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Your prayers and presence complete our joy.',
                    'am' => 'ጸሎትዎ እና መገኘትዎ ደስታችንን እጥፍ ድርብ ያደርገዋል',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Glory be to God in the highest.',
                    'am' => 'ክብር ለእግዚአብሔር በአርያም ይሁን፡ አሜን',
                    'needs_native_review' => true,
                ],
            ],
            'rsvp_line' => [
                [
                    'en' => 'Please confirm your attendance by November 5th',
                    'am' => 'እባክዎን እስከ ጥቅምት 26 ድረስ መገኘትዎን ያረጋግጡ',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'Kindly RSVP to the church event committee',
                    'am' => 'እባክዎን ለአዘጋጆቹ አስቀድመው ያሳውቁን',
                    'needs_native_review' => true,
                ],
                [
                    'en' => 'The favor of a response is requested',
                    'am' => 'መገኘትዎን በደስታ እንድናስተናግድ ቀድመው ያረጋግጡልን',
                    'needs_native_review' => true,
                ],
            ],
        ],
    ];

    public static function all(): array
    {
        return static::$copy;
    }

    /**
     * Get a specific copy item.
     */
    public static function get(string $category, string $role, int $variant = 0, string $locale = 'en'): ?string
    {
        $resolvedCategory = match ($category) {
            'religious' => 'religious-ethiopian',
            'other' => 'corporate',
            default => $category,
        };

        $variants = static::$copy[$resolvedCategory][$role] ?? static::$copy[$category][$role] ?? null;
        if (!$variants || !isset($variants[$variant])) {
            $variants = static::$copy['wedding'][$role] ?? null;
        }

        if (!$variants || !isset($variants[$variant])) {
            return null;
        }

        return $variants[$variant][$locale] ?? $variants[$variant]['en'] ?? null;
    }

    /**
     * Get full copy bundle for a category and specific variant (0, 1, or 2).
     */
    public static function getBundle(string $category, int $variant = 0, string $locale = 'en'): array
    {
        $result = [];
        foreach (static::ROLES as $role) {
            $result[$role] = static::get($category, $role, $variant, $locale);
        }

        return $result;
    }

    /**
     * Export all Amharic copy marked with needs_native_review: true to a markdown review document.
     */
    public static function exportReviewDocument(?string $destinationPath = null): string
    {
        $destinationPath = $destinationPath ?: (function_exists('storage_path') && app()->has('path.storage')
            ? storage_path('app/copy-review.md')
            : dirname(__DIR__, 3) . '/storage/app/copy-review.md');

        $dir = dirname($destinationPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $md = "# Amharic Copy Library Review Document\n\n";
        $md .= "> **Generated:** " . date('Y-m-d H:i:s') . "\n";
        $md .= "> **Purpose:** Native Ge'ez / Amharic speaker review of all card copy lines across categories.\n";
        $md .= "> All Amharic strings below are marked with `needs_native_review: true`.\n\n";
        $md .= "## Review Instructions\n";
        $md .= "1. Check grammatical correctness and natural Ge'ez / Amharic flow.\n";
        $md .= "2. Verify cultural appropriateness for each occasion (e.g. traditional Orthodox Teklil vs Islamic Walima vs Corporate Gala).\n";
        $md .= "3. Confirm tone matches the formality expected on formal invitation cards.\n\n";
        $md .= "---\n\n";

        foreach (static::$copy as $category => $roles) {
            $categoryTitle = ucwords(str_replace('-', ' ', $category));
            $md .= "## Category: {$categoryTitle} (`{$category}`)\n\n";

            foreach ($roles as $role => $variants) {
                $roleTitle = ucwords(str_replace('_', ' ', $role));
                $md .= "### Role: {$roleTitle} (`{$role}`)\n\n";

                foreach ($variants as $idx => $v) {
                    $vNum = $idx + 1;
                    $md .= "#### Variant {$vNum}\n";
                    $md .= "- **English Reference:** `{$v['en']}`\n";
                    $amSafe = str_replace("\n", " / ", $v['am']);
                    $md .= "- **Amharic Draft:** **{$amSafe}**\n";
                    $md .= "- **Status:** `[ ] Needs Native Review` (Flag: `needs_native_review: true`)\n";
                    $md .= "- **Reviewer Notes:** _________________________________________\n\n";
                }
            }

            $md .= "---\n\n";
        }

        file_put_contents($destinationPath, $md);

        return $destinationPath;
    }
}
