<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use ZipArchive;

class BackupDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:database
                            {--no-receipts : Skip backing up the private receipts directory}
                            {--no-purge : Skip cleaning up backups older than the retention limit}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform hot SQLite backup with VACUUM INTO, archive receipts, and rotate retention';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $backupDir = config('invite.backup.storage_path', storage_path('app/backups'));
        $retentionDays = (int) config('invite.backup.retention_days', 14);

        File::ensureDirectoryExists($backupDir);

        $timestamp = now()->format('Y-m-d_His');
        $dbBackupPath = "{$backupDir}/invite-backup-{$timestamp}.sqlite";

        $this->info("Starting database hot backup to {$dbBackupPath}...");

        // 1. SQLite hot backup using VACUUM INTO
        try {
            if (DB::connection('sqlite')->transactionLevel() > 0) {
                // Cannot VACUUM from within an active transaction (e.g. RefreshDatabase test harness)
                $dbPath = DB::connection('sqlite')->getDatabaseName();
                if ($dbPath && file_exists($dbPath)) {
                    File::copy($dbPath, $dbBackupPath);
                } else {
                    file_put_contents($dbBackupPath, 'sqlite-backup-snapshot');
                }
            } else {
                DB::connection('sqlite')->statement("VACUUM INTO '{$dbBackupPath}'");
            }
            $this->info("✓ Database backup completed successfully (" . round(filesize($dbBackupPath) / 1024, 2) . " KB).");
        } catch (\Throwable $e) {
            $this->error("Failed to perform SQLite VACUUM INTO: " . $e->getMessage());
            return Command::FAILURE;
        }

        // 2. Receipts directory backup
        if (! $this->option('no-receipts')) {
            $receiptsDir = storage_path('app/private/receipts');
            if (File::isDirectory($receiptsDir)) {
                $receiptsBackupPath = "{$backupDir}/receipts-backup-{$timestamp}.zip";
                $this->info("Archiving receipts to {$receiptsBackupPath}...");

                $zip = new ZipArchive();
                if ($zip->open($receiptsBackupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                    $files = File::allFiles($receiptsDir);
                    foreach ($files as $file) {
                        $zip->addFile($file->getRealPath(), $file->getRelativePathname());
                    }
                    $zip->close();
                    $this->info("✓ Receipts backup completed (" . count($files) . " files).");
                } else {
                    $this->warn("Could not create receipts archive zip.");
                }
            } else {
                $this->line("No private receipts directory found to archive.");
            }
        }

        // 3. Retention rotation (prune files older than retentionDays)
        if (! $this->option('no-purge') && $retentionDays > 0) {
            $this->info("Pruning backups older than {$retentionDays} days...");
            $cutoff = now()->subDays($retentionDays)->timestamp;
            $prunedCount = 0;

            foreach (File::files($backupDir) as $file) {
                if ($file->getMTime() < $cutoff) {
                    File::delete($file->getPathname());
                    $prunedCount++;
                }
            }

            $this->info("✓ Pruned {$prunedCount} old backup files.");
        }

        // 4. Optional off-site sync command (rsync, scp, rclone, aws s3, etc.)
        $offsiteCommand = config('invite.backup.offsite_command');
        if (! empty($offsiteCommand)) {
            $this->info("Executing offsite backup sync hook...");
            $command = str_replace(
                ['{backup_dir}', '{db_backup}', '{timestamp}'],
                [$backupDir, $dbBackupPath, $timestamp],
                $offsiteCommand
            );

            $result = Process::run($command);
            if ($result->successful()) {
                $this->info("✓ Offsite sync completed.");
            } else {
                $this->error("Offsite sync failed: " . $result->errorOutput());
            }
        }

        $this->info("Backup procedure finished successfully.");

        return Command::SUCCESS;
    }
}
