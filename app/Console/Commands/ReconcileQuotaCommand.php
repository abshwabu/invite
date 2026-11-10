<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\UsageLedger;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Console\Command;

class ReconcileQuotaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quota:reconcile
                            {--user= : Target user ID to reconcile}
                            {--subscription= : Target subscription ID to reconcile}
                            {--fix : Automatically write adjustment ledger rows and sync subscription cards_used}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recomputes quota cards_used from usage_ledger vs subscriptions.cards_used and reports/fixes mismatches';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = $this->option('user');
        $subId = $this->option('subscription');
        $fix = (bool) $this->option('fix');

        $query = Subscription::with('user', 'tier');

        if ($subId) {
            $query->where('id', $subId);
        }

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $subscriptions = $query->get();

        if ($subscriptions->isEmpty()) {
            $this->info('No subscriptions found matching criteria.');
            return Command::SUCCESS;
        }

        $this->info("Reconciling {$subscriptions->count()} subscriptions...");

        $tableRows = [];
        $discrepancyCount = 0;

        foreach ($subscriptions as $sub) {
            $ledgerTotal = (int) UsageLedger::where('subscription_id', $sub->id)->sum('quantity');
            $subCardsUsed = (int) $sub->cards_used;
            $diff = $ledgerTotal - $subCardsUsed;

            $status = $diff === 0 ? '✓ Balanced' : '⚠ Mismatch';

            if ($diff !== 0) {
                $discrepancyCount++;
            }

            $tableRows[] = [
                'Sub ID' => $sub->id,
                'User' => $sub->user ? "{$sub->user->name} (#{$sub->user_id})" : "User #{$sub->user_id}",
                'Tier' => $sub->tier?->name_en ?? '-',
                'Total Quota' => $sub->cards_total,
                'Sub Used' => $subCardsUsed,
                'Ledger Sum' => $ledgerTotal,
                'Diff' => $diff > 0 ? "+{$diff}" : (string) $diff,
                'Status' => $status,
            ];

            if ($diff !== 0 && $fix) {
                // If diff != 0, bring subscription.cards_used in sync with ledger total
                $correctedCardsUsed = max(0, min($sub->cards_total, $ledgerTotal));

                // Record audited adjustment ledger row if needed to document the fix
                UsageLedger::create([
                    'user_id' => $sub->user_id,
                    'subscription_id' => $sub->id,
                    'event_id' => null,
                    'guest_id' => null,
                    'campaign_id' => null,
                    'kind' => 'reconciliation_adjustment',
                    'channel' => 'system',
                    'quantity' => 0, // Ledger is already the ground truth; record zero-delta checkpoint
                    'created_at' => now(),
                ]);

                $sub->update([
                    'cards_used' => $correctedCardsUsed,
                    'status' => $correctedCardsUsed >= $sub->cards_total ? 'exhausted' : ($sub->ends_at && $sub->ends_at->isPast() ? 'expired' : 'active'),
                ]);

                AuditLogService::log(
                    action: 'quota.reconciled',
                    actor: null,
                    subject: $sub,
                    meta: [
                        'subscription_id' => $sub->id,
                        'user_id' => $sub->user_id,
                        'previous_sub_cards_used' => $subCardsUsed,
                        'ledger_total' => $ledgerTotal,
                        'corrected_cards_used' => $correctedCardsUsed,
                        'fixed_at' => now()->toIso8601String(),
                    ]
                );
            }
        }

        $this->table(
            ['Sub ID', 'User', 'Tier', 'Total Quota', 'Sub Used', 'Ledger Sum', 'Diff', 'Status'],
            $tableRows
        );

        if ($discrepancyCount > 0) {
            if ($fix) {
                $this->info("✓ Reconciled and fixed {$discrepancyCount} subscription discrepancies.");
            } else {
                $this->warn("Found {$discrepancyCount} subscriptions with discrepancies. Run with --fix to synchronize.");
            }
        } else {
            $this->info('All subscription quotas match usage ledger records perfectly.');
        }

        return Command::SUCCESS;
    }
}
