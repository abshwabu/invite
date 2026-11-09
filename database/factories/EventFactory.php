<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'starts_at' => now()->addDays(7)->setTime(14, 0),
            'ends_at' => now()->addDays(7)->setTime(20, 0),
            'timezone' => 'Africa/Addis_Ababa',
            'venue_name' => 'Sheraton Addis',
            'venue_address' => 'Taitu Street, Addis Ababa',
            'map_url' => 'https://maps.google.com/?q=Sheraton+Addis',
            'host_names' => 'Abebe & Chaltu',
            'is_invite_only' => true,
            'rsvp_enabled' => true,
            'plus_ones_allowed' => false,
            'design_json' => ['theme' => 'gold', 'font' => 'Noto Sans Ethiopic'],
            'template_id' => null,
            'thankyou_design_json' => null,
            'thankyou_template_id' => null,
            'status' => 'draft',
        ];
    }
}
