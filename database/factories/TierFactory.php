<?php

namespace Database\Factories;

use App\Models\Tier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tier>
 */
class TierFactory extends Factory
{
    protected $model = Tier::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($name) . '-' . fake()->unique()->numberBetween(100, 9999),
            'name_en' => ucfirst($name),
            'name_am' => 'ፕላን ' . fake()->unique()->numerify('##'),
            'description_en' => fake()->sentence(),
            'description_am' => 'የጥቅሉ ማብራሪያ',
            'price_amount' => fake()->randomElement([0, 500, 1200, 2500]),
            'currency' => 'ETB',
            'card_quota' => fake()->randomElement([50, 150, 500, 1000]),
            'validity_days' => fake()->randomElement([null, 30, 90, 365]),
            'max_events' => fake()->randomElement([null, 1, 3, 10]),
            'max_guests_per_event' => 500,
            'max_scanners_per_event' => 3,
            'sms_weight' => 1,
            'email_weight' => 1,
            'thank_you_consumes_quota' => false,
            'features' => [
                'thank_you' => true,
                'custom_messages' => true,
                'custom_links' => true,
                'qr_checkin' => true,
                'reminders' => true,
                'upload_templates' => false,
                'co_hosts' => true,
                'remove_branding' => false,
                'csv_import' => true,
                'email_channel' => true,
                'sms_channel' => true,
            ],
            'sort' => 0,
            'is_active' => true,
            'is_public' => true,
            'is_default' => false,
        ];
    }
}
