<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Guest>
 */
class GuestFactory extends Factory
{
    protected $model = Guest::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->name(),
            'phone' => '+2519' . fake()->unique()->numerify('########'),
            'email' => fake()->unique()->safeEmail(),
            'token' => Str::random(32),
            'preferred_channel' => fake()->randomElement(['sms', 'email', 'both']),
            'max_party_size' => 1,
            'rsvp_status' => 'pending',
            'rsvp_party_size' => null,
            'rsvp_note' => null,
            'opted_out' => false,
            'viewed_at' => null,
            'checked_in_at' => null,
            'checked_in_by' => null,
        ];
    }
}
