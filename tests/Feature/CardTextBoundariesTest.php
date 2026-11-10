<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Services\DesignValidator;
use App\Templates\Recipes\RecipeRegistry;
use App\Templates\Recipes\RecipeSpec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardTextBoundariesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Test that all layout recipes generate text layers with AutoFit configuration.
     */
    public function test_all_layout_recipes_generate_text_layers_with_autofit(): void
    {
        foreach (RecipeRegistry::all() as $id => $recipe) {
            $spec = new RecipeSpec(['recipe' => $id, 'category' => 'wedding']);
            $design = $recipe->build($spec);

            $textLayers = array_filter($design['layers'], fn ($l) => ($l['type'] ?? '') === 'text');
            $this->assertNotEmpty($textLayers, "Recipe {$id} should generate text layers.");

            foreach ($textLayers as $tl) {
                $this->assertArrayHasKey('autoFit', $tl, "Text layer '{$tl['id']}' in recipe '{$id}' must have autoFit configured.");
                $this->assertTrue($tl['autoFit']['enabled'] ?? false, "autoFit must be enabled for '{$tl['id']}' in '{$id}'.");
                $this->assertGreaterThan(0, $tl['autoFit']['minSize'] ?? 0, "autoFit minSize must be positive for '{$tl['id']}' in '{$id}'.");
            }
        }
    }

    /**
     * Test that text layers in all seeded templates strictly remain within 64px safe margins.
     */
    public function test_all_seeded_templates_pass_safe_margin_quality_rules(): void
    {
        $templates = Template::where('is_active', true)->get();
        $this->assertGreaterThanOrEqual(100, $templates->count());

        foreach ($templates as $t) {
            $design = $t->design_json;
            $this->assertIsArray($design);
            
            // Should pass strict quality rules including Rule 3 (64px safe margins)
            DesignValidator::validateQualityRules($design);
        }
    }

    /**
     * Test that the top opening line (salutation) in thank-you templates has autoFit enabled.
     */
    public function test_thank_you_templates_have_autofit_salutation_for_guest_name(): void
    {
        $thankYouTemplates = Template::where('kind', 'thank_you')->get();
        $this->assertNotEmpty($thankYouTemplates);

        foreach ($thankYouTemplates as $t) {
            $design = $t->design_json;
            $salutationLayer = null;
            foreach ($design['layers'] as $l) {
                if (($l['type'] ?? '') === 'text' && (str_contains($l['text'] ?? '', '{{guest_name}}') || ($l['id'] ?? '') === 'text-salutation')) {
                    $salutationLayer = $l;
                    break;
                }
            }

            $this->assertNotNull($salutationLayer, "Template {$t->key} should have a layer referencing {{guest_name}}.");
            $this->assertArrayHasKey('autoFit', $salutationLayer);
            $this->assertTrue($salutationLayer['autoFit']['enabled']);
            $this->assertLessThanOrEqual(24, $salutationLayer['autoFit']['minSize']);
        }
    }
}
