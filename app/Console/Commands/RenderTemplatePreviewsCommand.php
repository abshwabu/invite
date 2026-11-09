<?php

namespace App\Console\Commands;

use App\Models\Template;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class RenderTemplatePreviewsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'templates:render-previews {--all : Render all templates including user templates}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Render high-quality preview PNGs for system templates using Node.js canvas';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = Template::query();
        if (! $this->option('all')) {
            $query->where('is_system', true);
        }

        $templates = $query->get(['id', 'name', 'preview_path', 'design_json', 'width', 'height']);

        if ($templates->isEmpty()) {
            $this->warn('No templates found to render.');

            return self::SUCCESS;
        }

        $this->info("Found {$templates->count()} templates. Preparing preview render...");

        $tempFile = storage_path('app/temp_templates_render.json');
        file_put_contents($tempFile, $templates->toJson());

        $scriptPath = base_path('scripts/render_previews.js');
        $process = new Process(['node', $scriptPath, $tempFile]);
        $process->setTimeout(300);

        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        @unlink($tempFile);

        if (! $process->isSuccessful()) {
            $this->error('Failed to render some template previews.');

            return self::FAILURE;
        }

        $this->info('Template preview rendering finished successfully.');

        return self::SUCCESS;
    }
}
