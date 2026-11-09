<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Str;

class UrlSafetyService
{
    /**
     * Common third-party URL shortener services.
     */
    protected const URL_SHORTENERS = [
        'bit.ly',
        'tinyurl.com',
        't.co',
        'goo.gl',
        'ow.ly',
        'is.gd',
        'buff.ly',
        'adf.ly',
        'cutt.ly',
        'rb.gy',
        'shorturl.at',
        'rebrand.ly',
        'qr.ae',
        'shorte.st',
        'trib.al',
        'v.gd',
    ];

    /**
     * Default high-risk phishing / brand spoofing keywords in hostnames.
     */
    protected const SUSPICIOUS_HOST_PATTERNS = [
        'telebirr-login',
        'telebirr-verify',
        'cbe-online',
        'cbebirr-login',
        'cbe-secure',
        'bankofabyssinia-login',
        'awash-online',
        'paypal-login',
        'paypal-verify',
        'appleid-verify',
        'google-verify',
        'account-verification',
    ];

    /**
     * Validate a custom URL for safety.
     *
     * @return array{valid: bool, error: ?string, flagged: bool, host: ?string}
     */
    public function validate(string $url): array
    {
        $url = trim($url);

        // 1. HTTPS only
        if (! str_starts_with(strtolower($url), 'https://')) {
            return [
                'valid' => false,
                'error' => 'Custom links must use secure HTTPS (https:// only).',
                'flagged' => false,
                'host' => null,
            ];
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return [
                'valid' => false,
                'error' => 'Please provide a valid URL format.',
                'flagged' => false,
                'host' => null,
            ];
        }

        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');

        if (empty($host)) {
            return [
                'valid' => false,
                'error' => 'Unable to determine the host domain of the URL.',
                'flagged' => false,
                'host' => null,
            ];
        }

        // 2. Block IP addresses
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [
                'valid' => false,
                'error' => 'IP-address URLs are prohibited for safety and anti-phishing reasons.',
                'flagged' => false,
                'host' => $host,
            ];
        }

        // 3. Block third-party URL shorteners
        foreach (self::URL_SHORTENERS as $shortener) {
            if ($host === $shortener || str_ends_with($host, '.' . $shortener)) {
                return [
                    'valid' => false,
                    'error' => 'Third-party URL shorteners are prohibited to protect guests from opaque links.',
                    'flagged' => false,
                    'host' => $host,
                ];
            }
        }

        // 4. Check domain blocklist from settings / config
        $blockedDomains = $this->getBlockedDomains();
        foreach ($blockedDomains as $blocked) {
            $blocked = strtolower(trim($blocked));
            if (! empty($blocked) && ($host === $blocked || str_ends_with($host, '.' . $blocked))) {
                return [
                    'valid' => false,
                    'error' => "The domain '{$host}' is on the safety blocklist.",
                    'flagged' => false,
                    'host' => $host,
                ];
            }
        }

        // 5. Check lookalike / phishing patterns
        if (str_starts_with($host, 'xn--')) {
            return [
                'valid' => false,
                'error' => 'Internationalized Punycode domain names are not permitted in custom links.',
                'flagged' => false,
                'host' => $host,
            ];
        }

        foreach (self::SUSPICIOUS_HOST_PATTERNS as $pattern) {
            if (str_contains($host, $pattern)) {
                return [
                    'valid' => false,
                    'error' => 'The destination domain contains prohibited or suspicious lookalike patterns.',
                    'flagged' => false,
                    'host' => $host,
                ];
            }
        }

        // 6. Check if domain is flagged for interstitial warning
        $flaggedDomains = $this->getFlaggedDomains();
        $isFlagged = false;
        foreach ($flaggedDomains as $flagged) {
            $flagged = strtolower(trim($flagged));
            if (! empty($flagged) && ($host === $flagged || str_ends_with($host, '.' . $flagged))) {
                $isFlagged = true;
                break;
            }
        }

        return [
            'valid' => true,
            'error' => null,
            'flagged' => $isFlagged,
            'host' => $host,
        ];
    }

    /**
     * Get list of blocked domains from settings or config.
     *
     * @return array<string>
     */
    public function getBlockedDomains(): array
    {
        $setting = Setting::where('key', 'blocked_domains')->first();
        if ($setting && is_array($setting->value)) {
            return $setting->value;
        }

        return config('invite.safety.blocked_domains', [
            'malware.com',
            'phishing.net',
            'scam-portal.xyz',
            'free-crypto-giveaway.com',
        ]);
    }

    /**
     * Get list of admin-flagged domains that trigger an interstitial warning.
     *
     * @return array<string>
     */
    public function getFlaggedDomains(): array
    {
        $setting = Setting::where('key', 'flagged_domains')->first();
        if ($setting && is_array($setting->value)) {
            return $setting->value;
        }

        return config('invite.safety.flagged_domains', [
            'suspicious-external.com',
            'unverified-portal.org',
        ]);
    }
}
