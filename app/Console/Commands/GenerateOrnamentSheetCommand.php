<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class GenerateOrnamentSheetCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ornaments:sheet {set=all : The ornament set name (wedding, anniversary, funeral, faith-symbols, religious-islamic, religious-ethiopian, birthday, baby-shower, graduation, corporate, housewarming, shared, or all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Render high-resolution contact sheet PNGs for ornaments in storage/app/ornament-sheets/{set}.png';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $set = (string) $this->argument('set');

        $this->info("Rendering ornament contact sheet for set: {$set}...");

        $scriptPath = base_path('scripts/render_ornament_sheets.cjs');

        if (! file_exists($scriptPath)) {
            $this->error("Script not found at: {$scriptPath}");

            return self::FAILURE;
        }

        $process = new Process(['node', $scriptPath, $set]);
        $process->setTimeout(300);

        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $this->error('Failed to generate ornament contact sheets.');

            return self::FAILURE;
        }

        $this->info('Contact sheet generation completed successfully.');

        return self::SUCCESS;
    }
}
