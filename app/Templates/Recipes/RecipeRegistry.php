<?php

namespace App\Templates\Recipes;

use InvalidArgumentException;

class RecipeRegistry
{
    /**
     * @var array<string, AbstractRecipe>|null
     */
    protected static ?array $recipes = null;

    /**
     * Initialize all 12 layout recipes.
     */
    protected static function init(): void
    {
        if (static::$recipes !== null) {
            return;
        }

        $instances = [
            new FramedCenterRecipe(),
            new ArchWindowRecipe(),
            new SplitBandRecipe(),
            new CornerFloralsRecipe(),
            new PosterStackRecipe(),
            new MedallionRecipe(),
            new TicketRecipe(),
            new WashRecipe(),
            new HorizonRecipe(),
            new CollageRecipe(),
            new BandRecipe(),
            new TallScrollRecipe(),
        ];

        static::$recipes = [];
        foreach ($instances as $inst) {
            static::$recipes[$inst->getId()] = $inst;
        }
    }

    /**
     * Return all 12 registered recipes.
     *
     * @return array<string, AbstractRecipe>
     */
    public static function all(): array
    {
        static::init();

        return static::$recipes;
    }

    /**
     * Return a specific recipe by ID or code (e.g. 'framed-center' or 'R1').
     */
    public static function get(string $id): ?AbstractRecipe
    {
        static::init();

        $resolved = RecipeSpec::RECIPE_CODE_MAP[strtoupper($id)] ?? $id;

        return static::$recipes[$resolved] ?? null;
    }

    /**
     * Check if a recipe exists.
     */
    public static function has(string $id): bool
    {
        static::init();

        $resolved = RecipeSpec::RECIPE_CODE_MAP[strtoupper($id)] ?? $id;

        return isset(static::$recipes[$resolved]);
    }

    /**
     * Build a design JSON v2 using the specified recipe and spec.
     */
    public static function build(string $recipeId, RecipeSpec $spec): array
    {
        $recipe = static::get($recipeId);
        if (!$recipe) {
            throw new InvalidArgumentException("Layout recipe '{$recipeId}' not found in registry.");
        }

        return $recipe->build($spec);
    }

    /**
     * Build directly from a RecipeSpec instance.
     */
    public static function buildFromSpec(RecipeSpec $spec): array
    {
        return static::build($spec->recipeId, $spec);
    }

    /**
     * Build from a raw spec array.
     */
    public static function buildFromSpecArray(array $specArray, string $locale = 'en'): array
    {
        $specArray['locale'] = $locale;
        $spec = new RecipeSpec($specArray);

        return static::buildFromSpec($spec);
    }
}
