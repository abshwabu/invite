<?php

namespace App\Services;

use App\Models\Tier;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class EntitlementService
{
    /**
     * Retrieve all active and unexpired subscriptions for the user.
     *
     * @return Collection
     */
    public function getActiveSubscriptions(User $user): Collection
    {
        return $user->subscriptions()
            ->with('tier')
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get();
    }

    /**
     * Determine whether the user is entitled to a specific feature flag.
     * The highest tier among active subscriptions wins.
     */
    public function can(User $user, string $feature): bool
    {
        $subscriptions = $this->getActiveSubscriptions($user);

        if ($subscriptions->isEmpty()) {
            return false;
        }

        foreach ($subscriptions as $subscription) {
            $tier = $subscription->tier;
            if (! $tier) {
                continue;
            }

            $features = $tier->features ?? [];
            if (! empty($features[$feature])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve a numerical or structural limit for the user.
     * null indicates unlimited.
     */
    public function limit(User $user, string $key): mixed
    {
        $subscriptions = $this->getActiveSubscriptions($user);

        if ($subscriptions->isEmpty()) {
            return 0;
        }

        $values = [];
        $hasUnlimited = false;

        foreach ($subscriptions as $subscription) {
            $tier = $subscription->tier;
            if (! $tier) {
                continue;
            }

            $val = $tier->{$key} ?? null;
            if ($val === null) {
                $hasUnlimited = true;
            } else {
                $values[] = $val;
            }
        }

        // If any active tier grants unlimited (null), the user is unlimited
        if ($hasUnlimited) {
            return null;
        }

        if (empty($values)) {
            return 0;
        }

        // For cost/weight keys, the lowest number is most advantageous to the user
        if (in_array($key, ['sms_weight', 'email_weight'])) {
            return min($values);
        }

        return max($values);
    }

    /**
     * Get the highest tier among the user's active subscriptions.
     */
    public function getHighestTier(User $user): ?Tier
    {
        $subscriptions = $this->getActiveSubscriptions($user);

        if ($subscriptions->isEmpty()) {
            return null;
        }

        return $subscriptions
            ->pluck('tier')
            ->filter()
            ->sortByDesc('sort')
            ->first();
    }

    /**
     * Get a consolidated map of all known feature flags for the user.
     */
    public function allFeatures(User $user): array
    {
        $featureKeys = [
            'sms_channel',
            'email_channel',
            'thank_you',
            'custom_messages',
            'custom_links',
            'qr_checkin',
            'reminders',
            'upload_templates',
            'co_hosts',
            'remove_branding',
            'csv_import',
        ];

        $map = [];
        foreach ($featureKeys as $feature) {
            $map[$feature] = $this->can($user, $feature);
        }

        return $map;
    }
}
