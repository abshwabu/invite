<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TurnstileService
{
    protected const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * Check if Cloudflare Turnstile keys are configured.
     */
    public static function isConfigured(): bool
    {
        return ! empty(config('invite.turnstile.secret_key'));
    }

    /**
     * Verify a Turnstile response token.
     */
    public static function verify(?string $token, ?string $ip = null): bool
    {
        // Graceful fallback: If secret key is not configured or in testing environment, bypass
        if (! static::isConfigured() || app()->environment('testing')) {
            return true;
        }

        if (empty($token)) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(5)->post(self::VERIFY_URL, [
                'secret' => config('invite.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => $ip,
            ]);

            if ($response->successful()) {
                return (bool) $response->json('success');
            }

            Log::warning('Turnstile verification HTTP error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('Turnstile connection exception: ' . $e->getMessage());
            // Fail open only if explicitly requested, otherwise fail secure
            return false;
        }
    }
}
