<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Guest;
use App\Models\Setting;
use App\Services\AuditLogService;
use Illuminate\Console\Command;

class PurgeGuestDataRetention extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'guests:purge-retention {--days= : Override retention days threshold}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge and anonymize guest PII for events concluded past the retention threshold';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) Setting::get('guest_data_retention_days', config('invite.privacy.guest_data_retention_days', 30));

        $cutoff = now()->subDays($days);

        $this->info("Checking for concluded events past retention period ({$days} days, cutoff: {$cutoff->toDateTimeString()})...");

        // Find events concluded before the cutoff
        $concludedEventIds = Event::query()
            ->where(function ($query) use ($cutoff, $days) {
                $query->whereNotNull('ends_at')
                    ->where('ends_at', '<=', $cutoff)
                    ->orWhere(function ($sub) use ($days) {
                        $sub->whereNull('ends_at')
                            ->where('starts_at', '<=', now()->subDays($days + 1));
                    });
            })
            ->pluck('id');

        if ($concludedEventIds->isEmpty()) {
            $this->info('No concluded events found beyond the retention window.');
            return Command::SUCCESS;
        }

        // Target guests requiring anonymization
        $query = Guest::query()
            ->whereIn('event_id', $concludedEventIds)
            ->where(function ($q) {
                $q->whereNotNull('phone')
                    ->orWhereNotNull('email')
                    ->orWhereNotNull('rsvp_note')
                    ->orWhere('name', '!=', 'Anonymized Guest');
            });

        $count = $query->count();

        if ($count === 0) {
            $this->info("Concluded events checked ({$concludedEventIds->count()}), but all guest records are already anonymized.");
            return Command::SUCCESS;
        }

        $query->update([
            'name' => 'Anonymized Guest',
            'phone' => null,
            'email' => null,
            'rsvp_note' => null,
        ]);

        AuditLogService::log(
            action: 'privacy.guest_data_purged',
            actor: null,
            subject: null,
            meta: [
                'retention_days' => $days,
                'events_checked' => $concludedEventIds->count(),
                'guests_anonymized' => $count,
                'purged_at' => now()->toIso8601String(),
            ]
        );

        $this->info("Successfully anonymized {$count} guest records across {$concludedEventIds->count()} concluded events.");

        return Command::SUCCESS;
    }
}
