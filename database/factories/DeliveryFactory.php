<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Delivery>
 */
class DeliveryFactory extends Factory
{
    protected $model = Delivery::class;

    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'guest_id' => Guest::factory(),
            'channel' => fake()->randomElement(['sms', 'email']),
            'status' => 'delivered',
            'provider_message_id' => 'MSG-' . Str::upper(Str::random(10)),
            'error' => null,
            'attempts' => 1,
            'sent_at' => now(),
            'quota_charged' => 1,
        ];
    }
}
