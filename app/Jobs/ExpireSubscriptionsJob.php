<?php

namespace App\Jobs;

use App\Services\QuotaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireSubscriptionsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(QuotaService $quotaService): void
    {
        $quotaService->expireOverdueSubscriptions();
    }
}
