<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\UsageLedger;
use App\Models\User;

class ContentSafeguardService
{
    /**
     * Default prohibited keywords for SMS / email invitations and custom messages.
     */
    protected const DEFAULT_PROHIBITED_KEYWORDS = [
        'crypto giveaway',
        'free bitcoin',
        'lottery winner',
        'you won cash',
        'wire transfer',
        'western union transfer',
        'telebirr pin',
        'cbe password',
        'verify your bank password',
        'send your otp',
        'casino bonus',
    ];

    /**
     * Check if text contains prohibited spam or phishing keywords.
     *
     * @return string|null Returns matched keyword or null if clean.
     */
    public function checkProhibitedContent(string $text): ?string
    {
        $lower = strtolower($text);
        $keywords = $this->getProhibitedKeywords();

        foreach ($keywords as $kw) {
            $kw = strtolower(trim($kw));
            if (! empty($kw) && str_contains($lower, $kw)) {
                return $kw;
            }
        }

        return null;
    }

    /**
     * Check if a user is subject to the new account daily send cap and whether the proposed
     * card consumption would exceed it.
     *
     * @return string|null Error message if exceeded, or null if permitted.
     */
    public function checkDailySendCap(User $user, int $cardsToReserve): ?string
    {
        $newAccountDays = (int) config('invite.safety.new_account_days', 3);
        $dailyCap = (int) config('invite.safety.new_account_daily_cap', 50);

        // Check settings table override
        $settingCap = Setting::where('key', 'new_account_daily_cap')->first();
        if ($settingCap && is_numeric($settingCap->value)) {
            $dailyCap = (int) $settingCap->value;
        }

        $accountAgeDays = $user->created_at ? $user->created_at->diffInDays(now()) : 0;

        if ($accountAgeDays < $newAccountDays) {
            $cardsUsedToday = (int) UsageLedger::where('user_id', $user->id)
                ->where('created_at', '>=', now()->startOfDay())
                ->where('quantity', '>', 0)
                ->sum('quantity');

            if (($cardsUsedToday + $cardsToReserve) > $dailyCap) {
                return "New accounts are limited to a daily cap of {$dailyCap} cards during the first {$newAccountDays} days. You have used {$cardsUsedToday} cards today and requested {$cardsToReserve}.";
            }
        }

        return null;
    }

    /**
     * Determine if an account is considered new and requires administrative review
     * when including custom external links.
     */
    public function isNewAccountRequiringReview(User $user): bool
    {
        $reviewDays = (int) config('invite.safety.new_account_review_days', 3);

        $settingDays = Setting::where('key', 'new_account_review_days')->first();
        if ($settingDays && is_numeric($settingDays->value)) {
            $reviewDays = (int) $settingDays->value;
        }

        $accountAgeDays = $user->created_at ? $user->created_at->diffInDays(now()) : 0;

        return $accountAgeDays < $reviewDays;
    }

    /**
     * Ensure host name transparency is present in the SMS message body.
     */
    public function ensureHostNameInSms(string $body, ?string $hostNames): string
    {
        $hostNames = trim((string) $hostNames);
        if (empty($hostNames)) {
            return $body;
        }

        if (str_contains($body, '{host_names}') || str_contains($body, '{{host_names}}') || str_contains($body, $hostNames)) {
            return $body;
        }

        return rtrim($body) . " — {$hostNames}";
    }

    /**
     * Retrieve prohibited keywords list from settings or config.
     *
     * @return array<string>
     */
    public function getProhibitedKeywords(): array
    {
        $setting = Setting::where('key', 'prohibited_keywords')->first();
        if ($setting && is_array($setting->value)) {
            return $setting->value;
        }

        return config('invite.safety.prohibited_keywords', self::DEFAULT_PROHIBITED_KEYWORDS);
    }
}
