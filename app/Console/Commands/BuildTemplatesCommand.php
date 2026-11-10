<?php

namespace App\Console\Commands;

use App\Models\Template;
use Database\Seeders\InvitationTemplateSeeder;
use Database\Seeders\ThankYouTemplateSeeder;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class BuildTemplatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'templates:build {--force : Force re-rendering all previews bypassing cache}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Master build pipeline: seeds invitations + thank-you suites, renders previews (PNG + WebP in en and am), generates QA contact sheets, and runs automated QA';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('====================================================');
        $this->info('  Invite Template Build & QA Pipeline');
        $this->info('====================================================');

        // Step 1: Seed Invitation Templates
        $this->newLine();
        $this->info('1/5 Seeding 84 invitation templates from specs...');
        $this->call(InvitationTemplateSeeder::class);

        // Step 2: Seed Thank-You Suites
        $this->newLine();
        $this->info('2/5 Seeding 50 matching thank-you suite templates...');
        $this->call(ThankYouTemplateSeeder::class);

        // Step 3: Render Previews (PNG, 640 WebP, 320 WebP for en and am)
        $this->newLine();
        $this->info('3/5 Rendering previews (PNG, 640 WebP, 320 WebP in en & am)...');
        $previewExitCode = $this->call('templates:render-previews', [
            '--force' => $this->option('force'),
        ]);

        if ($previewExitCode !== self::SUCCESS) {
            $this->error('Preview rendering failed.');

            return self::FAILURE;
        }

        // Step 4: Render QA Contact Sheets
        $this->newLine();
        $this->info('4/5 Generating QA contact sheets in storage/app/template-qa/...');
        $templates = Template::with('category')->where('is_system', true)->get();
        $tempFile = storage_path('app/temp_templates_contact.json');
        file_put_contents($tempFile, $templates->toJson());

        $contactProcess = new Process(['node', base_path('scripts/render_contact_sheets.js'), $tempFile]);
        $contactProcess->setTimeout(300);
        $contactProcess->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        @unlink($tempFile);

        if (!$contactProcess->isSuccessful()) {
            $this->warn('Contact sheet rendering had warnings/errors.');
        }

        // Step 5: Automated QA
        $this->newLine();
        $this->info('5/5 Running automated QA suite...');
        $qaExitCode = $this->call('templates:qa');

        if ($qaExitCode !== self::SUCCESS) {
            $this->error('Template QA suite detected defects. Pipeline halted.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('====================================================');
        $this->info('  Build & QA Pipeline Completed Successfully!');
        $this->info('====================================================');

        return self::SUCCESS;
    }
}
