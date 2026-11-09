<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use App\Services\ReminderService;
use Illuminate\Console\Command;

class DispatchEventReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:dispatch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch pending event reminders that have reached their scheduled time';

    /**
     * Execute the console command.
     */
    public function handle(ReminderService $reminderService): int
    {
        $dueReminders = Reminder::with(['event.user'])
            ->where('status', 'pending')
            ->where('scheduled_at', '<=', now())
            ->get();

        $this->info("Found {$dueReminders->count()} due reminder(s) to process.");

        $dispatched = 0;
        $failed = 0;

        foreach ($dueReminders as $reminder) {
            $result = $reminderService->dispatch($reminder);

            if ($result['success']) {
                $dispatched++;
                $this->line("Dispatched reminder #{$reminder->id} for event '{$reminder->event?->title}'");
            } else {
                $failed++;
                $this->warn("Failed reminder #{$reminder->id}: " . ($result['error'] ?? 'Unknown error'));
            }
        }

        $this->info("Reminders processing complete. Dispatched: {$dispatched}, Failed: {$failed}.");

        return Command::SUCCESS;
    }
}
