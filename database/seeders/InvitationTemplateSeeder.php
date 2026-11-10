<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Template;
use App\Services\DesignValidator;
use App\Services\OrnamentRegistry;
use App\Templates\Recipes\RecipeRegistry;
use App\Templates\Recipes\RecipeSpec;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class InvitationTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $specsDir = database_path('templates/specs');
        if (!File::isDirectory($specsDir)) {
            $this->command?->error("Specs directory not found at {$specsDir}");
            return;
        }

        if (Category::count() === 0) {
            $this->call(CategorySeeder::class);
        }

        $categories = Category::all()->keyBy('slug');
        $files = File::glob("{$specsDir}/*.json");

        $totalSeeded = 0;
        $sortIndex = 1;

        foreach ($files as $file) {
            $categorySlug = basename($file, '.json');
            $category = $categories[$categorySlug] ?? null;

            if (!$category) {
                $this->command?->warn("Category slug '{$categorySlug}' not found in database. Skipping {$file}.");
                continue;
            }

            $specs = json_decode(File::get($file), true);
            if (!is_array($specs)) {
                $this->command?->warn("Malformed JSON in {$file}.");
                continue;
            }

            $catCount = 0;
            foreach ($specs as $spec) {
                // 1. Build design JSON via recipe engine
                $specObj = new RecipeSpec($spec);
                $design = RecipeRegistry::build($specObj->recipeId, $specObj);

                // 2. Validate with DesignValidator (Quality checks enabled)
                $validatedDesign = DesignValidator::validate($design, false, true);

                // 3. Extract ornament sets used in design
                $ornamentSets = [];
                foreach ($validatedDesign['layers'] as $layer) {
                    if (in_array($layer['type'] ?? '', ['ornament', 'pattern'], true) && !empty($layer['assetId'])) {
                        $item = OrnamentRegistry::find($layer['assetId']);
                        if ($item && !empty($item['set'])) {
                            $ornamentSets[$item['set']] = true;
                        }
                    }
                }

                // 4. Idempotently update or create template
                $suiteKey = $spec['suite_key'] ?? $spec['key'];

                Template::updateOrCreate(
                    [
                        'suite_key' => $suiteKey,
                        'kind' => 'invitation',
                    ],
                    [
                        'category_id' => $category->id,
                        'name' => $spec['name_en'] ?? $spec['name'] ?? 'Invitation Template',
                        'preview_path' => "/storage/templates/{$suiteKey}.png",
                        'design_json' => $validatedDesign,
                        'palette' => $validatedDesign['palette'] ?? null,
                        'tags' => $spec['tags'] ?? [],
                        'recipe' => $spec['recipe'] ?? null,
                        'ornament_sets' => array_values(array_keys($ornamentSets)),
                        'spec' => $spec,
                        'width' => 1080,
                        'height' => 1528,
                        'is_system' => true,
                        'is_active' => true,
                        'sort' => $sortIndex++,
                    ]
                );

                $catCount++;
                $totalSeeded++;
            }

            $this->command?->info("Seeded {$catCount} templates for category [{$categorySlug}].");
        }

        $this->command?->info("Successfully seeded {$totalSeeded} invitation templates.");
    }
}
