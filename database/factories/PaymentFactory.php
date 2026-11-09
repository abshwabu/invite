<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Tier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tier_id' => Tier::factory(),
            'amount' => fake()->randomFloat(2, 100, 5000),
            'currency' => 'ETB',
            'provider' => fake()->randomElement(['chapa', 'bank_transfer']),
            'provider_ref' => 'PAY-' . Str::upper(Str::random(12)),
            'status' => 'paid',
            'payload' => ['txn_id' => Str::random(16)],
            'paid_at' => now(),
        ];
    }
}
