<?php

namespace App\Services;

use App\Jobs\SendDeliveryJob;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Reminder;
use App\Notifications\HostAlertNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class ReminderService
{
    public function __construct(
        protected QuotaService $quotaService,
        protected EntitlementService $entitlementService,
    ) {}

    /**
     * Calculate scheduled_at datetime for a given preset.
     */
    public function calculateScheduledAt(Event $event, string $preset, ?string $customTime = null): Carbon
    {
        $timezone = $event->timezone ?: 'Africa/Addis_Ababa';
        $eventStart = $event->starts_at ? $event->starts_at->copy()->timezone($timezone) : now()->timezone($timezone);

        return match ($preset) {
            '3_days_before' => $eventStart->copy()->subDays(3)->setTime(9, 0),
            '1_day_before' => $eventStart->copy()->subDay()->setTime(9, 0),
            'morning_of' => (function () use ($eventStart) {
                // If event starts at or before 9:00 AM, send 3 hours earlier
                if ($eventStart->hour <= 9) {
                    return $eventStart->copy()->subHours(3);
                }
                return $eventStart->copy()->setTime(8, 0);
            })(),
            'custom' => $customTime ? Carbon::parse($customTime, $timezone) : now()->timezone($timezone),
            default => Carbon::parse($customTime ?: now(), $timezone),
        };
    }

    /**
     * Get non-opted-out eligible guests for the reminder.
     *
     * @return \Illuminate\Support\Collection<int, Guest>
     */
    public function getEligibleGuests(Reminder $reminder): \Illuminate\Support\Collection
    {
        $event = $reminder->event;
        if (! $event) {
            return collect();
        }

        $query = $event->guests()->where('opted_out', false);

        // Filter by target audience
        if ($reminder->target_audience === 'rsvp_pending') {
            $query->where('rsvp_status', 'pending');
        } elseif ($reminder->target_audience === 'rsvp_yes') {
            $query->where('rsvp_status', 'yes');
        }

        // Filter by channel capability
        if ($reminder->channel === 'sms') {
            $query->whereNotNull('phone')->where('phone', '!=', '');
        } elseif ($reminder->channel === 'email') {
            $query->whereNotNull('email')->where('email', '!=', '');
        } elseif ($reminder->channel === 'both') {
            $query->where(function ($q) {
                $q->where(fn ($sq) => $sq->whereNotNull('phone')->where('phone', '!=', ''))
                    ->orWhere(fn ($sq) => $sq->whereNotNull('email')->where('email', '!=', ''));
            });
        }

        return $query->get();
    }

    /**
     * Estimate card cost for a reminder.
     */
    public function estimateCost(Reminder $reminder, ?int $recipientCount = null): int
    {
        $event = $reminder->event;
        $user = $event?->user;
        if (! $user) {
            return 0;
        }

        if ($recipientCount === null) {
            $recipientCount = $this->getEligibleGuests($reminder)->count();
        }

        return $this->quotaService->calculateCampaignCost($user, $reminder->channel, $recipientCount);
    }

    /**
     * Dispatch a due reminder.
     *
     * @return array{success: bool, error?: string, campaign_id?: int}
     */
    public function dispatch(Reminder $reminder): array
    {
        if ($reminder->status !== 'pending') {
            return ['success' => false, 'error' => "Reminder #{$reminder->id} is not in pending status."];
        }

        $event = $reminder->event;
        if (! $event || in_array($event->status, ['cancelled', 'suspended'], true)) {
            $reminder->update([
                'status' => 'cancelled',
                'error' => 'Event is inactive or cancelled.',
            ]);
            return ['success' => false, 'error' => 'Event is inactive or cancelled.'];
        }

        $user = $event->user;
        if (! $user) {
            $reminder->update(['status' => 'failed', 'error' => 'Event has no owner.']);
            return ['success' => false, 'error' => 'Event has no owner.'];
        }

        // Check feature entitlement
        if (! $this->entitlementService->can($user, 'reminders')) {
            $reminder->update([
                'status' => 'failed',
                'error' => 'Your subscription tier does not include automated reminders.',
            ]);
            return ['success' => false, 'error' => 'Feature not entitled.'];
        }

        $eligibleGuests = $this->getEligibleGuests($reminder);
        $recipientCount = $eligibleGuests->count();

        if ($recipientCount === 0) {
            $reminder->update([
                'status' => 'sent',
                'error' => 'No eligible guests matched target criteria.',
            ]);
            return ['success' => true];
        }

        $totalCost = $this->estimateCost($reminder, $recipientCount);
        $availableQuota = $this->quotaService->available($user);

        // Check if user has sufficient quota
        if (! $this->quotaService->hasQuota($user, $totalCost)) {
            $reminder->update([
                'status' => 'failed',
                'error' => "Insufficient card quota. Needed {$totalCost} cards, but only {$availableQuota} available.",
            ]);

            // Notify event host
            $user->notify(new HostAlertNotification(
                type: 'reminder_failed',
                title: __('Reminder Failed: Insufficient Quota'),
                message: __("Scheduled reminder for event ':title' could not be sent because you need :needed cards, but have :available available. Please upgrade your plan.", [
                    'title' => $event->title,
                    'needed' => $totalCost,
                    'available' => $availableQuota,
                ]),
                meta: [
                    'reminder_id' => $reminder->id,
                    'event_id' => $event->id,
                    'needed_cards' => $totalCost,
                    'available_cards' => $availableQuota,
                ],
                actionUrl: route('plan.index')
            ));

            return ['success' => false, 'error' => 'insufficient_quota'];
        }

        // Reserve quota
        $reservation = $this->quotaService->reserve($user, $totalCost, [
            'event_id' => $event->id,
            'kind' => 'reminder',
            'channel' => $reminder->channel,
        ]);

        if (! $reservation->success) {
            $reminder->update([
                'status' => 'failed',
                'error' => $reservation->message ?? 'Failed to reserve quota.',
            ]);
            return ['success' => false, 'error' => $reservation->message ?? 'Failed to reserve quota.'];
        }

        // Create campaign
        $defaultSubject = "Reminder: {$event->title}";
        $defaultBody = "Reminder for {guest_name}: {event_title} is taking place on {date} at {time}. Venue: {venue}. View details: {link}";

        $campaign = Campaign::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'type' => 'reminder',
            'channel' => $reminder->channel,
            'subject' => $reminder->subject ?: $defaultSubject,
            'body' => $reminder->message_body ?: $defaultBody,
            'link_mode' => 'guest_invitation',
            'audience' => [
                'type' => $reminder->target_audience,
                'preset' => $reminder->preset,
            ],
            'audience_count' => $recipientCount,
            'status' => 'sending',
            'estimated_cards' => $totalCost,
        ]);

        if (! empty($reservation->ledgerIds)) {
            \App\Models\UsageLedger::whereIn('id', $reservation->ledgerIds)->update(['campaign_id' => $campaign->id]);
        }

        // Calculate unit cost per recipient
        $unitCost = $recipientCount > 0 ? (int) ceil($totalCost / $recipientCount) : 0;

        foreach ($eligibleGuests as $guest) {
            $channelsToSend = match ($reminder->channel) {
                'sms' => ['sms'],
                'email' => ['email'],
                'both' => array_filter([
                    ! empty($guest->phone) ? 'sms' : null,
                    ! empty($guest->email) ? 'email' : null,
                ]),
                default => ['sms'],
            };

            foreach ($channelsToSend as $ch) {
                $delivery = Delivery::create([
                    'campaign_id' => $campaign->id,
                    'guest_id' => $guest->id,
                    'channel' => $ch,
                    'status' => 'queued',
                    'quota_charged' => $unitCost,
                ]);

                SendDeliveryJob::dispatch($delivery->id);
            }
        }

        $reminder->update([
            'status' => 'sent',
            'campaign_id' => $campaign->id,
            'error' => null,
        ]);

        return [
            'success' => true,
            'campaign_id' => $campaign->id,
        ];
    }
}
