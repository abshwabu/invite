<?php

namespace App\Console\Commands;

use App\Models\Template;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class TemplateQaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'templates:qa {--category= : Only QA templates in a specific category}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automated QA suite enforcing 25% non-text coverage, safe margins, text non-overlap, dHash diversity >= 12, contrast, and suite pairing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting automated QA audit for template library...');

        $query = Template::with('category')->where('is_system', true);
        if ($cat = $this->option('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $cat));
        }

        $templates = $query->get(['id', 'category_id', 'kind', 'name', 'suite_key', 'preview_path', 'preview_path_am', 'spec', 'design_json', 'width', 'height']);

        if ($templates->isEmpty()) {
            $this->warn('No templates found for QA audit.');

            return self::SUCCESS;
        }

        $this->info("Auditing {$templates->count()} templates...");

        $tempFile = storage_path('app/temp_templates_qa.json');
        file_put_contents($tempFile, $templates->toJson());

        $scriptPath = base_path('scripts/template_qa.js');
        $process = new Process(['node', $scriptPath, $tempFile]);
        $process->setTimeout(300);
        $process->run();

        @unlink($tempFile);

        $output = $process->getOutput();
        $qaResult = json_decode($output, true);

        if (!is_array($qaResult)) {
            $this->error('Failed to parse QA audit output from node script.');
            $this->line($output);
            if ($process->getErrorOutput()) {
                $this->error($process->getErrorOutput());
            }

            return self::FAILURE;
        }

        $stats = $qaResult['stats'] ?? [];
        $total = $stats['totalChecked'] ?? $templates->count();

        $this->newLine();
        $this->table(
            ['QA Metric / Rule', 'Status', 'Details'],
            [
                ['Suite Pairing (Invitations & Thank-Yous)', count(array_filter($qaResult['errors'] ?? [], fn ($e) => ($e['rule'] ?? '') === 'suite_pairing')) === 0 ? '<info>PASSED</info>' : '<error>FAILED</error>', 'Every thank-you matches an invitation suite_key'],
                ['Non-Text Pixel Coverage (>= 25%)', ($stats['coveragePassed'] ?? 0) === $total ? '<info>PASSED</info>' : '<error>FAILED</error>', "{$stats['coveragePassed']}/{$total} templates meet >= 25% non-text coverage"],
                ['Safe Margins & Text Overlap (en & am)', ($stats['marginAndOverlapPassed'] ?? 0) === $total ? '<info>PASSED</info>' : '<error>FAILED</error>', "{$stats['marginAndOverlapPassed']}/{$total} templates have no overlap and respect 96px safe margin"],
                ['Color Contrast Roles (4A)', ($stats['contrastPassed'] ?? 0) === $total ? '<info>PASSED</info>' : '<error>FAILED</error>', "{$stats['contrastPassed']}/{$total} palettes meet WCAG AA contrast rules"],
                ['Perceptual Hash Diversity (dHash >= 12)', ($stats['dHashPassed'] ?? 0) > 0 ? '<info>PASSED</info>' : '<error>FAILED</error>', 'Hamming distance >= 12 between category templates'],
            ]
        );

        if (!empty($qaResult['errors'])) {
            $this->newLine();
            $this->error("Found " . count($qaResult['errors']) . " QA defects:");
            $errorRows = [];
            foreach ($qaResult['errors'] as $err) {
                $errorRows[] = [
                    $err['rule'] ?? 'unknown',
                    $err['template'] ?? 'N/A',
                    $err['message'] ?? '',
                ];
            }
            $this->table(['Rule', 'Template', 'Defect Description'], $errorRows);

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("All QA checks passed cleanly across {$total} templates!");

        return self::SUCCESS;
    }
}
