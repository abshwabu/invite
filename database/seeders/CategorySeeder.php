<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'slug' => 'wedding',
                'name_en' => 'Wedding',
                'name_am' => 'ሠርግ',
                'sort' => 1,
            ],
            [
                'slug' => 'funeral',
                'name_en' => 'Funeral',
                'name_am' => 'ተዝካር / የቀብር ሥነ-ሥርዓት',
                'sort' => 2,
            ],
            [
                'slug' => 'birthday',
                'name_en' => 'Birthday',
                'name_am' => 'ልደት',
                'sort' => 3,
            ],
            [
                'slug' => 'graduation',
                'name_en' => 'Graduation',
                'name_am' => 'ምረቃ',
                'sort' => 4,
            ],
            [
                'slug' => 'baby-shower',
                'name_en' => 'Baby Shower',
                'name_am' => 'የሕፃን መወለድ (ቤቢ ሻወር)',
                'sort' => 5,
            ],
            [
                'slug' => 'religious',
                'name_en' => 'Religious (Eid/Mawlid/etc.)',
                'name_am' => 'ሃይማኖታዊ በዓላት (ዒድ/መውሊድ/ወዘተ)',
                'sort' => 6,
            ],
            [
                'slug' => 'corporate',
                'name_en' => 'Corporate',
                'name_am' => 'የድርጅት / የንግድ',
                'sort' => 7,
            ],
            [
                'slug' => 'anniversary',
                'name_en' => 'Anniversary',
                'name_am' => 'የዓመት በዓል / መታሰቢያ',
                'sort' => 8,
            ],
            [
                'slug' => 'housewarming',
                'name_en' => 'Housewarming',
                'name_am' => 'የቤት ምርቃት',
                'sort' => 9,
            ],
            [
                'slug' => 'other',
                'name_en' => 'Other',
                'name_am' => 'ሌላ',
                'sort' => 10,
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
