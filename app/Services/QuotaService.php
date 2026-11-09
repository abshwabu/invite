<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Tier;
use App\Models\UsageLedger;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class QuotaService
{
    /**
     * Get the total number of available cards for the user across active unexpired subscriptions.
     */
    public function available(User $user): int
    {
        return (int) $user->subscriptions()
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->selectRaw('COALESCE(SUM(cards_total - cards_used), 0) as available_cards')
            ->value('available_cards');
    }

    /**
     * Get total cards allocated across active unexpired subscriptions.
     */
    public function totalCards(User $user): int
    {
        return (int) $user->subscriptions()
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->sum('cards_total');
    }

    /**
     * Get total cards used across active unexpired subscriptions.
     */
    public function usedCards(User $user): int
    {
        return (int) $user->subscriptions()
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->sum('cards_used');
    }

    /**
     * Atomically reserve cards for a user across active subscriptions, consuming
     * from the subscription that expires soonest first.
     */
    public function reserve(User $user, int $cards, array $context = []): ReservationResult
    {
        if ($cards <= 0) {
            return ReservationResult::success(0, 0);
        }

        return DB::transaction(function () use ($user, $cards, $context) {
            // Find active, unexpired subscriptions ordered by earliest expiry first (NULL expires last)
            $subscriptions = Subscription::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END ASC, expires_at ASC, id ASC')
                ->lockForUpdate()
                ->get();

            $totalAvailable = 0;
            foreach ($subscriptions as $sub) {
                $totalAvailable += max(0, $sub->cards_total - $sub->cards_used);
            }

            if ($totalAvailable < $cards) {
                return ReservationResult::failure(
                    "Insufficient quota: requested {$cards} cards, but only {$totalAvailable} available.",
                    $cards,
                    0
                );
            }

            $needed = $cards;
            $reserved = 0;
            $ledgerIds = [];

            foreach ($subscriptions as $sub) {
                if ($needed <= 0) {
                    break;
                }

                $availableInSub = max(0, $sub->cards_total - $sub->cards_used);
                if ($availableInSub <= 0) {
                    continue;
                }

                $toTake = min($needed, $availableInSub);

                // Race-safe guarded atomic update on SQLite
                $affected = DB::update(
                    "UPDATE subscriptions
                     SET cards_used = cards_used + :take,
                         status = CASE WHEN cards_used + :take >= cards_total THEN 'exhausted' ELSE status END,
                         updated_at = :now
                     WHERE id = :sub_id
                       AND (cards_used + :take) <= cards_total
                       AND status = 'active'",
                    [
                        'take' => $toTake,
                        'now' => now()->toDateTimeString(),
                        'sub_id' => $sub->id,
                    ]
                );

                if ($affected === 0) {
                    // Row was modified concurrently or condition not met, rollback
                    throw new Exception("Concurrent quota conflict on subscription #{$sub->id}");
                }

                $ledger = UsageLedger::create([
                    'user_id' => $user->id,
                    'subscription_id' => $sub->id,
                    'event_id' => $context['event_id'] ?? null,
                    'guest_id' => $context['guest_id'] ?? null,
                    'campaign_id' => $context['campaign_id'] ?? null,
                    'kind' => $context['kind'] ?? 'invitation',
                    'channel' => $context['channel'] ?? 'none',
                    'quantity' => $toTake,
                    'created_at' => now(),
                ]);

                $ledgerIds[] = $ledger->id;
                $reserved += $toTake;
                $needed -= $toTake;
            }

            if ($needed > 0) {
                throw new Exception("Unable to fulfill complete reservation for {$cards} cards.");
            }

            return ReservationResult::success($cards, $reserved, $ledgerIds);
        });
    }

    /**
     * Refund cards to a subscription and record a negative ledger entry.
     */
    public function refund(UsageLedger|array $context, int $cards): ?UsageLedger
    {
        if ($cards <= 0) {
            return null;
        }

        return DB::transaction(function () use ($context, $cards) {
            $subId = $context instanceof UsageLedger ? $context->subscription_id : ($context['subscription_id'] ?? null);
            $userId = $context instanceof UsageLedger ? $context->user_id : ($context['user_id'] ?? null);
            $eventId = $context instanceof UsageLedger ? $context->event_id : ($context['event_id'] ?? null);
            $guestId = $context instanceof UsageLedger ? $context->guest_id : ($context['guest_id'] ?? null);
            $campaignId = $context instanceof UsageLedger ? $context->campaign_id : ($context['campaign_id'] ?? null);
            $channel = $context instanceof UsageLedger ? $context->channel : ($context['channel'] ?? 'none');

            if (! $subId && $userId) {
                // Find most recent subscription for user
                $subId = Subscription::where('user_id', $userId)->latest('id')->value('id');
            }

            if ($subId) {
                // Atomically decrement cards_used and revive exhausted status if applicable
                DB::update(
                    "UPDATE subscriptions
                     SET cards_used = CASE WHEN cards_used - :cards < 0 THEN 0 ELSE cards_used - :cards END,
                         status = CASE WHEN status = 'exhausted' THEN 'active' ELSE status END,
                         updated_at = :now
                     WHERE id = :sub_id",
                    [
                        'cards' => $cards,
                        'now' => now()->toDateTimeString(),
                        'sub_id' => $subId,
                    ]
                );
            }

            return UsageLedger::create([
                'user_id' => $userId,
                'subscription_id' => $subId,
                'event_id' => $eventId,
                'guest_id' => $guestId,
                'campaign_id' => $campaignId,
                'kind' => 'refund',
                'channel' => $channel,
                'quantity' => -1 * abs($cards),
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Calculate cost in cards based on tier weights and card kind.
     */
    public function calculateRequiredCards(User|Tier $userOrTier, string $channel, string $kind = 'invitation', int $count = 1): int
    {
        $tier = $userOrTier instanceof Tier
            ? $userOrTier
            : app(EntitlementService::class)->getHighestTier($userOrTier);

        if (! $tier) {
            return $count;
        }

        // Thank-you cards cost 0 unless thank_you_consumes_quota is true
        if ($kind === 'thank_you' && ! $tier->thank_you_consumes_quota) {
            return 0;
        }

        $smsWeight = max(1, (int) $tier->sms_weight);
        $emailWeight = max(1, (int) $tier->email_weight);

        return match ($channel) {
            'sms' => $count * $smsWeight,
            'email' => $count * $emailWeight,
            'both' => $count * ($smsWeight + $emailWeight),
            default => $count,
        };
    }

    /**
     * Mark active subscriptions that have passed expires_at as expired.
     */
    public function expireOverdueSubscriptions(): int
    {
        return Subscription::query()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update([
                'status' => 'expired',
                'updated_at' => now(),
            ]);
    }
}
