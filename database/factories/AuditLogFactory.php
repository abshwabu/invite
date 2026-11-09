<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'actor_type' => User::class,
            'actor_id' => User::factory(),
            'action' => fake()->randomElement(['created_event', 'sent_campaign', 'updated_profile', 'login']),
            'subject_type' => null,
            'subject_id' => null,
            'meta' => ['browser' => 'Chrome', 'context' => 'test'],
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => now(),
        ];
    }
}
