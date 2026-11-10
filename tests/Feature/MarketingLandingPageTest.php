<?php

use App\Models\Category;
use App\Models\Template;
use App\Models\Tier;
use Database\Seeders\CategorySeeder;
use Database\Seeders\InvitationTemplateSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TierSeeder::class);
    $this->seed(CategorySeeder::class);
    $this->seed(InvitationTemplateSeeder::class);
});

test('landing renders for guests with required props and design tokens', function () {
    $response = $this->get('/');

    $response->assertStatus(200);

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Welcome')
        ->has('appName')
        ->has('categories')
        ->has('heroTemplates')
        ->has('shelfTemplates')
        ->has('tiers')
    );
});

test('hero templates cover all required occasion chips', function () {
    $response = $this->get('/');

    $response->assertStatus(200);

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Welcome')
        ->has('heroTemplates.wedding')
        ->has('heroTemplates.funeral')
        ->has('heroTemplates.birthday')
        ->has('heroTemplates.eid')
        ->has('heroTemplates.meskel')
        ->has('heroTemplates.graduation')
        ->where('heroTemplates.wedding.category_id', function ($catId) {
            $cat = Category::find($catId);
            return $cat && $cat->slug === 'wedding';
        })
    );
});

test('pricing reflects active public database tiers with gold tier highlighted', function () {
    $response = $this->get('/');

    $response->assertStatus(200);

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Welcome')
        ->has('tiers', 5)
        ->where('tiers', function ($tiers) {
            $slugs = collect($tiers)->pluck('slug')->all();
            expect($slugs)->toContain('free', 'bronze', 'silver', 'gold', 'platinum');

            $gold = collect($tiers)->firstWhere('slug', 'gold');
            expect($gold)->not->toBeNull();
            expect((int) $gold['card_quota'])->toBe(40000);
            expect((float) $gold['price_amount'])->toBe(6999.0);

            $free = collect($tiers)->firstWhere('slug', 'free');
            expect($free)->not->toBeNull();
            expect((int) $free['card_quota'])->toBe(20);
            expect((float) $free['price_amount'])->toBe(0.0);

            return true;
        })
    );
});

test('guest editor link carries the chosen template', function () {
    $weddingTemplate = Template::where('kind', 'invitation')
        ->whereHas('category', fn ($q) => $q->where('slug', 'wedding'))
        ->first();

    expect($weddingTemplate)->not->toBeNull();

    // Guest accessing editor with template id
    $response = $this->get(route('editor.show', ['template' => $weddingTemplate->id]));

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Editor/Show')
        ->where('template.id', $weddingTemplate->id)
    );
});

test('sitemap xml generates correctly and contains zero admin or secret paths', function () {
    $response = $this->get('/sitemap.xml');

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/xml');

    $content = $response->getContent();

    expect($content)->toContain('<loc>' . url('/') . '</loc>');
    expect($content)->toContain('<loc>' . url('/templates') . '</loc>');
    expect($content)->toContain('<loc>' . url('/editor') . '</loc>');
    expect($content)->toContain('hreflang="en"');
    expect($content)->toContain('hreflang="am"');

    // Security check: no admin / superadmin routes
    expect($content)->not->toContain('admin');
    expect($content)->not->toContain('superadmin');
    expect($content)->not->toContain('dashboard');
});

test('locale update route switches language between english and amharic', function () {
    $response = $this->post(route('locale.update'), ['locale' => 'am']);

    $response->assertSessionHas('locale', 'am');

    $responseEn = $this->post(route('locale.update'), ['locale' => 'en']);
    $responseEn->assertSessionHas('locale', 'en');
});
