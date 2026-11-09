<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['invitation', 'reminder', 'thank_you', 'custom']),
            'channel' => fake()->randomElement(['sms', 'email', 'both']),
            'subject' => fake()->sentence(4),
            'body' => 'Dear {{name}}, you are invited to our celebration. Please RSVP here: {{link}}',
            'link_mode' => 'guest_invitation',
            'custom_url' => null,
            'audience' => ['status' => 'all'],
            'audience_count' => fake()->numberBetween(10, 150),
            'scheduled_at' => null,
            'status' => 'draft',
            'estimated_cards' => fake()->numberBetween(10, 150),
        ];
    }
}
