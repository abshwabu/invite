<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\AuditLogService;
use Illuminate\Console\Command;

class CloseConcludedEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:close-ended';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically mark events whose end time has passed as closed';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = now();

        // Events that have ends_at <= now, or starts_at + 8 hours <= now when ends_at is null
        $events = Event::whereIn('status', ['published', 'draft'])
            ->where(function ($query) use ($now) {
                $query->whereNotNull('ends_at')
                    ->where('ends_at', '<=', $now)
                    ->orWhere(function ($fallbackQuery) use ($now) {
                        $fallbackQuery->whereNull('ends_at')
                            ->whereNotNull('starts_at')
                            ->where('starts_at', '<=', $now->copy()->subHours(8));
                    });
            })
            ->get();

        $this->info("Found {$events->count()} event(s) eligible for closing.");

        $closedCount = 0;
        foreach ($events as $event) {
            $prevStatus = $event->status;
            $event->update(['status' => 'closed']);
            $closedCount++;

            AuditLogService::log(
                action: 'event.closed_automatically',
                actor: null,
                subject: $event,
                meta: [
                    'previous_status' => $prevStatus,
                    'new_status' => 'closed',
                    'reason' => 'Event ended at ' . ($event->ends_at ?: $event->starts_at),
                ]
            );

            $this->line("Closed event #{$event->id}: '{$event->title}'");
        }

        $this->info("Successfully closed {$closedCount} concluded event(s).");

        return Command::SUCCESS;
    }
}
