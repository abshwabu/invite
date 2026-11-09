<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Template;
use Illuminate\Database\Seeder;

class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'category_slug' => 'wedding',
                'name' => 'Royal Ethiopian Wedding',
                'kind' => 'invitation',
                'preview_path' => '/images/templates/wedding-royal.png',
                'width' => 1080,
                'height' => 1920,
                'design_json' => [
                    'theme' => 'gold',
                    'primaryColor' => '#D4AF37',
                    'backgroundColor' => '#1A1A1A',
                    'textColor' => '#F8F9FA',
                    'fontFamily' => 'Noto Sans Ethiopic',
                    'heading' => 'የጋብቻ ጥሪ',
                    'subheading' => 'Honored Wedding Invitation',
                    'hosts' => 'Abebe & Chaltu',
                    'venue' => 'Sheraton Addis, Lalibela Ballroom',
                    'date' => 'Sunday, January 10, 2027 at 2:00 PM',
                ],
                'is_system' => true,
                'is_active' => true,
                'sort' => 1,
            ],
            [
                'category_slug' => 'wedding',
                'name' => 'Traditional Habesha Wedding',
                'kind' => 'invitation',
                'preview_path' => '/images/templates/wedding-habesha.png',
                'width' => 1080,
                'height' => 1920,
                'design_json' => [
                    'theme' => 'tibeb',
                    'primaryColor' => '#8B0000',
                    'backgroundColor' => '#FFFDF9',
                    'textColor' => '#212529',
                    'fontFamily' => 'Noto Sans Ethiopic',
                    'heading' => 'የሠርግ ግብዣ',
                    'subheading' => 'Together with their families',
                    'hosts' => 'Dawit & Meron',
                    'venue' => 'Haile Grand Addis Ababa',
                    'date' => 'Saturday, March 14, 2027 at 3:00 PM',
                ],
                'is_system' => true,
                'is_active' => true,
                'sort' => 2,
            ],
            [
                'category_slug' => 'birthday',
                'name' => 'Celebration Birthday Bash',
                'kind' => 'invitation',
                'preview_path' => '/images/templates/birthday-celebration.png',
                'width' => 1080,
                'height' => 1920,
                'design_json' => [
                    'theme' => 'party',
                    'primaryColor' => '#FF6B6B',
                    'backgroundColor' => '#0F172A',
                    'textColor' => '#FFFFFF',
                    'fontFamily' => 'sans-serif',
                    'heading' => 'Happy Birthday!',
                    'subheading' => 'Come Celebrate With Us',
                    'hosts' => 'Natnael',
                    'venue' => 'Bole Medhanialem, Sky Lounge',
                    'date' => 'Friday, December 18, 2026 at 6:00 PM',
                ],
                'is_system' => true,
                'is_active' => true,
                'sort' => 1,
            ],
            [
                'category_slug' => 'graduation',
                'name' => 'Distinguished Graduation',
                'kind' => 'invitation',
                'preview_path' => '/images/templates/graduation.png',
                'width' => 1080,
                'height' => 1920,
                'design_json' => [
                    'theme' => 'academic',
                    'primaryColor' => '#2563EB',
                    'backgroundColor' => '#F8FAFC',
                    'textColor' => '#1E293B',
                    'fontFamily' => 'sans-serif',
                    'heading' => 'የምረቃ በዓል ጥሪ',
                    'subheading' => 'Class of 2027 Graduation Party',
                    'hosts' => 'Dr. Biruk',
                    'venue' => 'Skylight Hotel Addis Ababa',
                    'date' => 'Sunday, July 5, 2027 at 1:00 PM',
                ],
                'is_system' => true,
                'is_active' => true,
                'sort' => 1,
            ],
            [
                'category_slug' => 'religious',
                'name' => 'Eid Mubarak Celebration',
                'kind' => 'invitation',
                'preview_path' => '/images/templates/eid-mubarak.png',
                'width' => 1080,
                'height' => 1920,
                'design_json' => [
                    'theme' => 'emerald',
                    'primaryColor' => '#059669',
                    'backgroundColor' => '#064E3B',
                    'textColor' => '#ECFDF5',
                    'fontFamily' => 'sans-serif',
                    'heading' => 'ዒድ ሙባረክ!',
                    'subheading' => 'Eid Celebration Gathering',
                    'hosts' => 'Mustafa & Fatuma Family',
                    'venue' => 'Addis Banquet Hall',
                    'date' => 'Eid Day at 11:30 AM',
                ],
                'is_system' => true,
                'is_active' => true,
                'sort' => 1,
            ],
        ];

        foreach ($templates as $tmpl) {
            $category = Category::where('slug', $tmpl['category_slug'])->first();
            if ($category) {
                Template::firstOrCreate(
                    [
                        'name' => $tmpl['name'],
                        'category_id' => $category->id,
                    ],
                    [
                        'kind' => $tmpl['kind'],
                        'preview_path' => $tmpl['preview_path'],
                        'width' => $tmpl['width'],
                        'height' => $tmpl['height'],
                        'design_json' => $tmpl['design_json'],
                        'is_system' => $tmpl['is_system'],
                        'is_active' => $tmpl['is_active'],
                        'sort' => $tmpl['sort'],
                    ]
                );
            }
        }
    }
}
