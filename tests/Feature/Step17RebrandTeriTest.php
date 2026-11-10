<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class Step17RebrandTeriTest extends TestCase
{
    public function test_brand_configurations_are_properly_set(): void
    {
        $this->assertEquals('Teri', config('app.name'));
        $this->assertEquals('ጠሪ', config('invite.brand.name_am', env('APP_NAME_AM')));
        $this->assertEquals('ጠሪ አክባሪ', config('invite.brand.slogan_am', env('APP_SLOGAN_AM')));
        $this->assertEquals('Invite with honor', config('invite.brand.slogan_en', env('APP_SLOGAN_EN')));
        $this->assertEquals('Teri', config('invite.sms.sender_id'));
    }

    public function test_all_required_brand_assets_exist_and_are_valid(): void
    {
        $assets = [
            'public/brand/teri-mark.svg',
            'public/brand/teri-mark-reversed.svg',
            'public/brand/teri-mark-mono-ink.svg',
            'public/brand/teri-mark-mono-white.svg',
            'public/brand/teri-lockup-horizontal.svg',
            'public/brand/teri-lockup-horizontal-reversed.svg',
            'public/brand/teri-lockup-stacked.svg',
            'public/brand/teri-lockup-with-slogan.svg',
            'resources/brand/teri-mark.svg',
            'public/favicon.ico',
            'public/favicon.svg',
            'public/apple-touch-icon.png',
            'public/icon-192.png',
            'public/icon-512.png',
            'public/icon-192-maskable.png',
            'public/icon-512-maskable.png',
            'public/og-image.png',
            'public/avatar.png',
            'public/manifest.json',
            'docs/design/brand-sheet.png',
            'docs/design/brand-notes.md',
        ];

        foreach ($assets as $asset) {
            $path = base_path($asset);
            $this->assertFileExists($path, "Expected brand asset [{$asset}] to exist.");
            $this->assertGreaterThan(0, filesize($path), "Expected brand asset [{$asset}] to have non-zero size.");

            if (str_ends_with($asset, '.svg')) {
                $content = file_get_contents($path);
                $this->assertStringContainsString('<svg', $content);
                $this->assertStringContainsString('viewBox=', $content);
                $this->assertStringContainsString('</svg>', $content);
                // Ensure no unoutlined <text> elements
                $this->assertStringNotContainsString('<text', $content, "Asset [{$asset}] should contain pure vector paths without <text> tags.");
            }
        }
    }

    public function test_brand_logo_vue_component_exists_and_supports_required_variants(): void
    {
        $path = resource_path('js/Components/BrandLogo.vue');
        $this->assertFileExists($path);

        $content = file_get_contents($path);
        $this->assertStringContainsString('variant', $content);
        $this->assertStringContainsString('tone', $content);
        $this->assertStringContainsString('mark', $content);
        $this->assertStringContainsString('horizontal', $content);
        $this->assertStringContainsString('stacked', $content);
        $this->assertStringContainsString('slogan', $content);
    }

    public function test_brand_notes_and_copy_review_documents_are_updated(): void
    {
        $notesPath = base_path('docs/design/brand-notes.md');
        $this->assertFileExists($notesPath);
        $notesContent = file_get_contents($notesPath);
        $this->assertStringContainsString('ጠሪ', $notesContent);
        $this->assertStringContainsString('ጠሪ አክባሪ', $notesContent);
        $this->assertStringContainsString('#1C2140', $notesContent);
        $this->assertStringContainsString('#F4B400', $notesContent);

        $reviewPath = storage_path('app/copy-review.md');
        $this->assertFileExists($reviewPath);
        $reviewContent = file_get_contents($reviewPath);
        $this->assertStringContainsString('needs_native_review: true', $reviewContent);
        $this->assertStringContainsString('ጠሪ አክባሪ', $reviewContent);
    }

    public function test_welcome_page_renders_teri_brand_identity_and_slogan(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        // Verify Inertia props share brand identity
        $response->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->has('brand')
            ->where('brand.name', 'Teri')
            ->where('brand.name_am', 'ጠሪ')
            ->where('brand.slogan_am', 'ጠሪ አክባሪ')
            ->where('brand.slogan_en', 'Invite with honor')
        );

        $html = $response->getContent();
        $this->assertStringContainsString('Teri', $html);
        $this->assertStringContainsString('favicon.svg', $html);
        $this->assertStringContainsString('manifest.json', $html);
    }

    public function test_brand_check_artisan_command_passes_with_strict_mode(): void
    {
        $this->artisan('brand:check', ['--strict' => true])
            ->expectsOutputToContain('Brand Check: ALL PASS')
            ->assertExitCode(0);
    }
}
