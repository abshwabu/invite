<?php

namespace App\Console\Commands;

use App\Services\QuotaService;
use Illuminate\Console\Command;

class ExpireSubscriptionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire overdue subscriptions that have reached their expiration date';

    /**
     * Execute the console command.
     */
    public function handle(QuotaService $quotaService): int
    {
        $count = $quotaService->expireOverdueSubscriptions();
        $this->info("Successfully expired {$count} overdue subscription(s).");

        return self::SUCCESS;
    }
}
