<?php

namespace Database\Factories;

use App\Models\CheckinLog;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Scanner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CheckinLog>
 */
class CheckinLogFactory extends Factory
{
    protected $model = CheckinLog::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'guest_id' => Guest::factory(),
            'scanner_id' => Scanner::factory(),
            'result' => fake()->randomElement(['success', 'already_used', 'invalid', 'wrong_event', 'revoked']),
            'scanned_at' => now(),
        ];
    }
}
