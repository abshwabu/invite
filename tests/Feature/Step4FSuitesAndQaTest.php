<?php

use App\Models\Category;
use App\Models\Template;
use App\Services\DesignValidator;
use Database\Seeders\InvitationTemplateSeeder;
use Database\Seeders\ThankYouTemplateSeeder;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(InvitationTemplateSeeder::class);
    $this->seed(ThankYouTemplateSeeder::class);
});

test('exactly 50 thank-you templates exist with 5 per category matching invitation suites', function () {
    $totalThankYous = Template::where('kind', 'thank_you')->where('is_system', true)->count();
    expect($totalThankYous)->toBe(50);

    $categories = Category::all();
    expect($categories->count())->toBe(10);

    foreach ($categories as $category) {
        $count = Template::where('kind', 'thank_you')
            ->where('category_id', $category->id)
            ->where('is_system', true)
            ->count();

        expect($count)->toBe(5, "Category [{$category->slug}] must have exactly 5 thank-you suite templates, got {$count}");
    }
});

test('every thank-you template matches its invitation suite_key, palette, and fonts', function () {
    $thankYous = Template::where('kind', 'thank_you')->where('is_system', true)->get();

    foreach ($thankYous as $ty) {
        $suiteKey = $ty->suite_key;
        expect($suiteKey)->not->toBeEmpty();

        $invitation = Template::where('kind', 'invitation')
            ->where('suite_key', $suiteKey)
            ->first();

        expect($invitation)->not->toBeNull("Invitation for suite [{$suiteKey}] must exist");

        // Verify category match
        expect($ty->category_id)->toBe($invitation->category_id);

        // Verify palette match
        $tySpec = $ty->spec;
        $invSpec = $invitation->spec;
        expect($tySpec['palette'])->toBe($invSpec['palette']);

        // Verify fonts match
        expect($tySpec['fonts'])->toBe($invSpec['fonts']);

        // Verify needs_native_review flag
        expect($tySpec['needs_native_review'] ?? false)->toBeTrue();

        // Verify calm recipe assignment
        expect($tySpec['recipe'])->not->toBeEmpty();
    }
});

test('thank-you templates strictly validate against DesignValidator v2', function () {
    $thankYous = Template::where('kind', 'thank_you')->where('is_system', true)->get();

    foreach ($thankYous as $ty) {
        $design = $ty->design_json;
        expect($design)->toBeArray();
        expect($design['version'])->toBe(2);

        // Must validate with strict quality rules
        $validated = DesignValidator::validate($design, false, true);
        expect($validated)->toBeArray();
    }
});

test('template gallery endpoint /templates supports kind filter, locale, and category counts', function () {
    $response = $this->get(route('templates.index', ['kind' => 'thank_you', 'locale' => 'en']));

    $response->assertStatus(200);

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Templates/Index')
        ->has('templates', 50)
        ->has('categories')
        ->where('filters.kind', 'thank_you')
    );
});

test('all active system templates have preview_path and preview_path_am populated', function () {
    $templates = Template::where('is_system', true)->where('is_active', true)->get();
    expect($templates->count())->toBe(134);

    foreach ($templates as $t) {
        expect($t->preview_path)->not->toBeNull();
        expect($t->preview_path_am)->not->toBeNull();
    }
});

test('templates:qa artisan command passes with exit code 0', function () {
    $this->artisan('templates:qa')
        ->assertExitCode(0);
});

test('preview files exist on disk for all 134 active templates in png, 640 webp, and 320 webp', function () {
    $templates = Template::where('is_system', true)->where('is_active', true)->get();
    expect($templates->count())->toBe(134);

    $baseDir = storage_path('app/public/templates');

    foreach ($templates as $t) {
        $slug = $t->suite_key;
        if ($t->kind === 'thank_you' && !str_ends_with($slug, '-thankyou')) {
            $slug .= '-thankyou';
        }

        expect(File::exists("{$baseDir}/{$slug}.png"))->toBeTrue("Missing {$slug}.png");
        expect(File::exists("{$baseDir}/{$slug}_640.webp"))->toBeTrue("Missing {$slug}_640.webp");
        expect(File::exists("{$baseDir}/{$slug}_320.webp"))->toBeTrue("Missing {$slug}_320.webp");
        expect(File::exists("{$baseDir}/{$slug}_am.png"))->toBeTrue("Missing {$slug}_am.png");
        expect(File::exists("{$baseDir}/{$slug}_am_640.webp"))->toBeTrue("Missing {$slug}_am_640.webp");
        expect(File::exists("{$baseDir}/{$slug}_am_320.webp"))->toBeTrue("Missing {$slug}_am_320.webp");
    }
});
