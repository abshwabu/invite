<?php

namespace Database\Factories;

use App\Models\UsageLedger;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UsageLedger>
 */
class UsageLedgerFactory extends Factory
{
    protected $model = UsageLedger::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subscription_id' => null,
            'event_id' => null,
            'guest_id' => null,
            'campaign_id' => null,
            'kind' => fake()->randomElement(['invitation', 'reminder', 'thank_you', 'custom', 'refund', 'adjustment']),
            'channel' => fake()->randomElement(['sms', 'email', 'none']),
            'quantity' => 1,
            'created_at' => now(),
        ];
    }
}
