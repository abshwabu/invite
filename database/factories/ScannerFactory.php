<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Scanner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Scanner>
 */
class ScannerFactory extends Factory
{
    protected $model = Scanner::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => 'Door Scanner ' . fake()->numberBetween(1, 10),
            'pin_hash' => Hash::make('1234'),
            'token' => Str::random(40),
            'revoked_at' => null,
            'failed_attempts' => 0,
            'locked_until' => null,
            'last_used_at' => null,
        ];
    }
}
