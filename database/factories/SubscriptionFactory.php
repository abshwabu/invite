<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\Tier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tier_id' => Tier::factory(),
            'cards_total' => 100,
            'cards_used' => 0,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
            'source' => 'purchase',
            'payment_id' => null,
            'note' => fake()->sentence(),
        ];
    }
}
