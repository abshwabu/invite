<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'slug' => Str::slug($name) . '-' . fake()->unique()->numberBetween(100, 9999),
            'name_en' => ucfirst($name),
            'name_am' => 'ምድብ ' . fake()->unique()->numerify('##'),
            'sort' => fake()->numberBetween(1, 50),
        ];
    }
}
