<?php

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Setting>
 */
class SettingFactory extends Factory
{
    protected $model = Setting::class;

    public function definition(): array
    {
        return [
            'key' => 'setting_' . fake()->unique()->word() . '_' . fake()->unique()->numberBetween(100, 9999),
            'value' => [
                'enabled' => true,
                'configured_at' => now()->toIso8601String(),
            ],
        ];
    }
}
