<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Template>
 */
class TemplateFactory extends Factory
{
    protected $model = Template::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'kind' => fake()->randomElement(['invitation', 'thank_you']),
            'name' => fake()->words(2, true) . ' Template',
            'preview_path' => 'templates/previews/sample.png',
            'design_json' => [
                'background' => '#ffffff',
                'elements' => [
                    ['type' => 'text', 'content' => 'You are cordially invited', 'x' => 100, 'y' => 150],
                ],
            ],
            'width' => 1080,
            'height' => 1920,
            'is_system' => true,
            'user_id' => null,
            'is_active' => true,
            'sort' => 0,
        ];
    }
}
