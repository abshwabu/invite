<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventMember>
 */
class EventMemberFactory extends Factory
{
    protected $model = EventMember::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'user_id' => User::factory(),
            'role' => fake()->randomElement(['editor', 'viewer']),
        ];
    }
}
