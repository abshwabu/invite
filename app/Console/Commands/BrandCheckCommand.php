<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BrandCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'brand:check {--strict : Fail with non-zero exit code if warnings or stale references exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify Teri brand configuration, required vector/raster assets, and detect stale brand references';

    /**
     * Required brand asset files that must exist on disk.
     */
    protected array $requiredAssets = [
        'public/brand/teri-mark.svg' => 'SVG Monogram Mark',
        'public/brand/teri-mark-reversed.svg' => 'SVG Monogram Reversed',
        'public/brand/teri-mark-mono-ink.svg' => 'SVG Monogram Mono Ink',
        'public/brand/teri-mark-mono-white.svg' => 'SVG Monogram Mono White',
        'public/brand/teri-lockup-horizontal.svg' => 'Horizontal Lockup',
        'public/brand/teri-lockup-horizontal-reversed.svg' => 'Horizontal Lockup Reversed',
        'public/brand/teri-lockup-stacked.svg' => 'Stacked Lockup',
        'public/brand/teri-lockup-with-slogan.svg' => 'Lockup with Slogan',
        'resources/brand/teri-mark.svg' => 'Resource Brand Mark',
        'resources/js/Components/BrandLogo.vue' => 'BrandLogo Vue Component',
        'public/favicon.ico' => 'Multi-res Favicon ICO (16/32/48)',
        'public/favicon.svg' => 'Vector Favicon SVG',
        'public/apple-touch-icon.png' => 'Apple Touch Icon (180x180)',
        'public/icon-192.png' => 'PWA Icon 192',
        'public/icon-512.png' => 'PWA Icon 512',
        'public/icon-192-maskable.png' => 'PWA Maskable Icon 192',
        'public/icon-512-maskable.png' => 'PWA Maskable Icon 512',
        'public/og-image.png' => 'OpenGraph Social Share Image (1200x630)',
        'public/avatar.png' => 'Social Avatar (1080x1080)',
        'public/manifest.json' => 'Web App Manifest',
        'docs/design/brand-sheet.png' => 'Brand Multi-Scale Visual Sheet',
        'docs/design/brand-notes.md' => 'Brand Critique & Usage Notes',
    ];

    /**
     * Files/directories to scan for stale brand references.
     */
    protected array $scanDirectories = [
        'resources/views',
        'resources/js/Layouts',
        'resources/js/Pages/Welcome.vue',
        'resources/js/Pages/Public',
        'resources/js/Pages/Legal',
        'resources/js/locales',
        'lang',
        'config/app.php',
        'config/invite.php',
        'app/Notifications',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('====================================================');
        $this->info('       Teri (ጠሪ) Brand Configuration & QA Check     ');
        $this->info('====================================================');

        $hasErrors = false;
        $hasWarnings = false;

        // 1. Configuration Verification
        $this->newLine();
        $this->info('1. Brand Environment & Configuration');
        $this->line('----------------------------------------------------');

        $appName = config('app.name');
        $appNameAm = config('invite.brand.name_am', env('APP_NAME_AM', 'ጠሪ'));
        $sloganAm = config('invite.brand.slogan_am', env('APP_SLOGAN_AM', 'ጠሪ አክባሪ'));
        $sloganEn = config('invite.brand.slogan_en', env('APP_SLOGAN_EN', 'Invite with honor'));
        $smsSender = config('invite.sms.sender_id');

        $this->line("• Application Name (Latin):     <fg=cyan>{$appName}</>");
        $this->line("• Application Name (Amharic):   <fg=cyan>{$appNameAm}</>");
        $this->line("• Brand Slogan (Amharic):       <fg=yellow>{$sloganAm}</>");
        $this->line("• Brand Slogan (English):       <fg=yellow>{$sloganEn}</>");
        $this->line("• SMS Sender ID:                <fg=cyan>{$smsSender}</>");

        if ($appName !== 'Teri') {
            $this->error("  ✖ APP_NAME must be 'Teri' (found: '{$appName}')");
            $hasErrors = true;
        } else {
            $this->line("  <fg=green>✓</> APP_NAME correctly configured as 'Teri'");
        }

        if ($appNameAm !== 'ጠሪ') {
            $this->error("  ✖ APP_NAME_AM must be 'ጠሪ' (found: '{$appNameAm}')");
            $hasErrors = true;
        } else {
            $this->line("  <fg=green>✓</> APP_NAME_AM correctly configured as 'ጠሪ'");
        }

        if ($sloganAm !== 'ጠሪ አክባሪ') {
            $this->warn("  ⚠ APP_SLOGAN_AM expected 'ጠሪ አክባሪ' (found: '{$sloganAm}')");
            $hasWarnings = true;
        } else {
            $this->line("  <fg=green>✓</> APP_SLOGAN_AM correctly configured as 'ጠሪ አክባሪ'");
        }

        if ($smsSender !== 'Teri') {
            $this->warn("  ⚠ SMS Sender ID should be 'Teri' (found: '{$smsSender}')");
            $hasWarnings = true;
        } else {
            $this->line("  <fg=green>✓</> Default SMS sender ID correctly set to 'Teri'");
        }

        // 2. Asset Integrity Check
        $this->newLine();
        $this->info('2. Brand Assets & Visual Exports');
        $this->line('----------------------------------------------------');

        $missingAssets = 0;
        foreach ($this->requiredAssets as $relPath => $label) {
            $fullPath = base_path($relPath);
            if (File::exists($fullPath)) {
                $size = File::size($fullPath);
                $sizeStr = $size > 1024 ? round($size / 1024, 1) . ' KB' : "{$size} bytes";
                $this->line("  <fg=green>✓</> {$label} (<fg=gray>{$relPath}</> - {$sizeStr})");
            } else {
                $this->error("  ✖ Missing asset: {$label} ({$relPath})");
                $missingAssets++;
            }
        }

        if ($missingAssets > 0) {
            $hasErrors = true;
        }

        // 3. Stale Brand Reference Detection
        $this->newLine();
        $this->info('3. Stale Brand Name Scan (detecting old placeholder branding)');
        $this->line('----------------------------------------------------');

        $staleHits = $this->scanForStaleBrandStrings();

        if (empty($staleHits)) {
            $this->line("  <fg=green>✓</> Zero stale brand references detected across views, configs, layouts, and notifications.");
        } else {
            $this->warn("  Found potential stale brand occurrences:");
            foreach ($staleHits as $hit) {
                $this->line("    • <fg=yellow>{$hit['file']}:{$hit['line']}</> {$hit['snippet']}");
            }
            $hasWarnings = true;
        }

        // Summary
        $this->newLine();
        $this->line('====================================================');
        if ($hasErrors) {
            $this->error('Brand Check: FAILED with errors.');
            return Command::FAILURE;
        }

        if ($hasWarnings && $this->option('strict')) {
            $this->warn('Brand Check: Completed with warnings (strict mode enabled).');
            return Command::FAILURE;
        }

        $this->info('Brand Check: ALL PASS (Teri brand identity fully operational).');
        return Command::SUCCESS;
    }

    /**
     * Scan designated files for stale occurrences of "Invite" used as brand name.
     */
    protected function scanForStaleBrandStrings(): array
    {
        $hits = [];

        // Patterns that indicate stale brand usage
        $stalePatterns = [
            '/\bInvite\s+Control\s+Panel\b/i',
            '/\bInvite\s+Editor\b/i',
            '/\bInvite\s+Scanner\b/i',
            '/\bPowered\s+by\s+(?:<[^>]+>)?Invite\b/i',
            '/\bSent\s+via\s+Invite\b/i',
            '/\bCreated\s+with\s+Invite\b/i',
            '/\bYour\s+Invite\s+verification\b/i',
            '/\[Invite\]/',
            '/\bdefault:\s*[\'"]Invite[\'"]/',
            '/\bpackage\s+on\s+Invite\b/i',
        ];

        foreach ($this->scanDirectories as $target) {
            $path = base_path($target);

            if (File::isFile($path)) {
                $files = [new \SplFileInfo($path)];
            } elseif (File::isDirectory($path)) {
                $files = File::allFiles($path);
            } else {
                continue;
            }

            foreach ($files as $file) {
                $content = @file_get_contents($file->getRealPath());
                if ($content === false) continue;

                $lines = explode("\n", $content);
                foreach ($lines as $lineNum => $line) {
                    foreach ($stalePatterns as $pattern) {
                        if (preg_match($pattern, $line)) {
                            $hits[] = [
                                'file' => str_replace(base_path() . '/', '', $file->getRealPath()),
                                'line' => $lineNum + 1,
                                'snippet' => trim($line),
                            ];
                            break;
                        }
                    }
                }
            }
        }

        return $hits;
    }
}
