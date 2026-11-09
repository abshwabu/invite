<?php

namespace App\Console\Commands;

use App\Jobs\SendDeliveryJob;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Services\AudienceService;
use App\Services\EntitlementService;
use App\Services\QuotaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DispatchScheduledCampaigns extends Command
{
    protected $signature = 'campaigns:dispatch-scheduled';
    protected $description = 'Dispatch campaigns scheduled to run at or before the current time';

    public function handle(
        AudienceService $audienceService,
        EntitlementService $entitlementService,
        QuotaService $quotaService
    ): int {
        $now = now();
        $scheduledCampaigns = Campaign::with(['event.guests', 'user'])
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', $now)
            ->get();

        if ($scheduledCampaigns->isEmpty()) {
            return Command::SUCCESS;
        }

        $this->info("Found {$scheduledCampaigns->count()} scheduled campaign(s) ready to dispatch.");

        foreach ($scheduledCampaigns as $campaign) {
            $event = $campaign->event;
            $user = $campaign->user;

            if (! $event || ! $user) {
                $campaign->update(['status' => 'failed']);
                continue;
            }

            $audienceDef = $campaign->audience ?? [];
            $audienceType = $audienceDef['type'] ?? 'all';
            $guestIds = $audienceDef['guest_ids'] ?? [];

            $resolved = $audienceService->resolve($event, $audienceType, $guestIds, $campaign->channel);
            $eligibleGuests = $resolved['eligible_guests'];

            if ($eligibleGuests->isEmpty()) {
                $campaign->update(['status' => 'failed']);
                $this->warn("Campaign #{$campaign->id} has no eligible recipients at dispatch time.");
                continue;
            }

            $tier = $entitlementService->getHighestTier($user);
            $smsWeight = $tier ? max(1, (int) $tier->sms_weight) : 1;
            $emailWeight = $tier ? max(1, (int) $tier->email_weight) : 1;

            $plannedDeliveries = [];
            $totalQuotaNeeded = 0;

            foreach ($eligibleGuests as $guest) {
                if ($campaign->channel === 'sms' || ($campaign->channel === 'both' && ! empty($guest->phone))) {
                    $plannedDeliveries[] = ['guest_id' => $guest->id, 'channel' => 'sms', 'cost' => $smsWeight];
                    $totalQuotaNeeded += $smsWeight;
                }
                if ($campaign->channel === 'email' || ($campaign->channel === 'both' && ! empty($guest->email))) {
                    $plannedDeliveries[] = ['guest_id' => $guest->id, 'channel' => 'email', 'cost' => $emailWeight];
                    $totalQuotaNeeded += $emailWeight;
                }
            }

            // Reserve quota at send time
            $reservation = $quotaService->reserve($user, $totalQuotaNeeded, [
                'event_id' => $event->id,
                'campaign_id' => $campaign->id,
                'kind' => $campaign->type,
                'channel' => $campaign->channel,
            ]);

            if (! $reservation->isSuccessful()) {
                $campaign->update(['status' => 'failed']);
                Log::warning("Scheduled campaign #{$campaign->id} failed due to insufficient quota at dispatch time.");
                $this->error("Campaign #{$campaign->id} failed: insufficient quota.");
                continue;
            }

            // Dispatch deliveries
            DB::transaction(function () use ($campaign, $totalQuotaNeeded, $plannedDeliveries) {
                $campaign->update([
                    'status' => 'sending',
                    'estimated_cards' => $totalQuotaNeeded,
                    'audience_count' => count($plannedDeliveries),
                ]);

                foreach ($plannedDeliveries as $item) {
                    $delivery = Delivery::create([
                        'campaign_id' => $campaign->id,
                        'guest_id' => $item['guest_id'],
                        'channel' => $item['channel'],
                        'status' => 'queued',
                        'quota_charged' => $item['cost'],
                    ]);

                    SendDeliveryJob::dispatch($delivery->id);
                }
            });

            $this->info("Dispatched campaign #{$campaign->id} to " . count($plannedDeliveries) . " recipients.");
        }

        return Command::SUCCESS;
    }
}
