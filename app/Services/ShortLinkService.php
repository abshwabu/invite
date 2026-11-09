<?php

namespace App\Services;

use App\Models\ShortLink;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class ShortLinkService
{
    /**
     * Create a new short link for the target URL.
     */
    public function create(string $targetUrl, ?CarbonInterface $expiresAt = null, int $length = 8): ShortLink
    {
        $code = $this->generateUniqueCode($length);

        return ShortLink::create([
            'code' => $code,
            'target_url' => $targetUrl,
            'clicks' => 0,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Resolve a short link by code, increment clicks atomically, and return the model.
     * Returns null if not found or expired.
     */
    public function resolve(string $code): ?ShortLink
    {
        $shortLink = ShortLink::where('code', $code)->first();

        if (! $shortLink) {
            return null;
        }

        if ($shortLink->expires_at && $shortLink->expires_at->isPast()) {
            return null;
        }

        $shortLink->increment('clicks');

        return $shortLink;
    }

    /**
     * Generate a unique 8-character code.
     */
    public function generateUniqueCode(int $length = 8): string
    {
        $charset = '23456789abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
        $charsetLen = strlen($charset);

        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $charset[random_int(0, $charsetLen - 1)];
            }
        } while (ShortLink::where('code', $code)->exists());

        return $code;
    }
}
