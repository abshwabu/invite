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
    protected $signature = 'templates:render-previews {--all : Render all templates including user templates} {--force : Force re-rendering all previews bypassing cache}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Render high-quality preview PNGs and WebP thumbnails in English and Amharic using Node.js canvas';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = Template::query();
        if (! $this->option('all')) {
            $query->where('is_system', true);
        }

        $templates = $query->get(['id', 'name', 'kind', 'suite_key', 'preview_path', 'preview_path_am', 'spec', 'design_json', 'width', 'height']);

        if ($templates->isEmpty()) {
            $this->warn('No templates found to render.');

            return self::SUCCESS;
        }

        $this->info("Found {$templates->count()} templates. Preparing preview render (force=" . ($this->option('force') ? 'true' : 'false') . ')...');

        $tempFile = storage_path('app/temp_templates_render.json');
        file_put_contents($tempFile, $templates->toJson());

        $scriptPath = base_path('scripts/render_previews.js');
        $args = ['node', $scriptPath, $tempFile];
        if ($this->option('force')) {
            $args[] = '--force';
        }

        $process = new Process($args);
        $process->setTimeout(null);

        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        $outputFile = storage_path('app/temp_templates_render_output.json');
        if (file_exists($outputFile)) {
            $outputData = json_decode(file_get_contents($outputFile), true);
            if (is_array($outputData)) {
                foreach ($outputData as $tmplId => $paths) {
                    $tmpl = Template::find($tmplId);
                    if ($tmpl) {
                        $spec = $tmpl->spec ?? [];
                        $spec['preview_path'] = $paths['preview_path'] ?? $tmpl->preview_path;
                        $spec['preview_path_am'] = $paths['preview_path_am'] ?? null;

                        $tmpl->updateQuietly([
                            'preview_path' => $paths['preview_path'] ?? $tmpl->preview_path,
                            'preview_path_am' => $paths['preview_path_am'] ?? null,
                            'spec' => $spec,
                        ]);
                    }
                }
            }
            @unlink($outputFile);
        }

        @unlink($tempFile);

        if (! $process->isSuccessful()) {
            $this->error('Failed to render some template previews.');

            return self::FAILURE;
        }

        $this->info('Template preview rendering finished successfully.');

        return self::SUCCESS;
    }
}
